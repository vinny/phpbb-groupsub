<?php
/**
 *
 * Group Subscription. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026, Steve Guidetti, https://github.com/stevotvr
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace stevotvr\groupsub\payment\providers;

use phpbb\config\config;
use phpbb\controller\helper;
use phpbb\language\language;
use phpbb\user;
use stevotvr\groupsub\entity\term_interface;
use stevotvr\groupsub\operator\currency_interface;
use stevotvr\groupsub\operator\mercadopago_client_interface;
use stevotvr\groupsub\payment\payment_result;
use stevotvr\groupsub\payment\provider_base;
use stevotvr\groupsub\payment\webhook_result;
use Symfony\Component\HttpFoundation\Request;

/**
 * Pix (Mercado Pago) Payment Provider implementation.
 */
class pix_provider extends provider_base
{
	/**
	 * @var mercadopago_client_interface
	 */
	protected mercadopago_client_interface $mp_client;

	/**
	 * @var currency_interface
	 */
	protected currency_interface $currency;

	/**
	 * @var user
	 */
	protected user $user;

	/**
	 * Constructor.
	 *
	 * @param config                       $config
	 * @param language                     $language
	 * @param helper                       $helper
	 * @param mercadopago_client_interface $mp_client
	 * @param currency_interface           $currency
	 * @param user                         $user
	 */
	public function __construct(
		config $config,
		language $language,
		helper $helper,
		mercadopago_client_interface $mp_client,
		currency_interface $currency,
		user $user
	)
	{
		parent::__construct($config, $language, $helper);
		$this->mp_client = $mp_client;
		$this->currency = $currency;
		$this->user = $user;
	}

	/**
	 * @inheritDoc
	 */
	public function get_id(): string
	{
		return 'pix';
	}

	/**
	 * @inheritDoc
	 */
	public function get_name(): string
	{
		return $this->language->lang('ACP_GROUPSUB_GATEWAY_NAME_PIX');
	}

	/**
	 * @inheritDoc
	 */
	public function is_enabled(): bool
	{
		return !empty($this->config['stevotvr_groupsub_gw_pix_enabled']);
	}

	/**
	 * @inheritDoc
	 */
	public function is_sandbox(): bool
	{
		return !empty($this->config['stevotvr_groupsub_gw_pix_sandbox']);
	}

	/**
	 * @inheritDoc
	 */
	public function supports_recurring(): bool
	{
		return false;
	}

	/**
	 * @inheritDoc
	 */
	public function supports_currency(string $currency): bool
	{
		return strtoupper($currency) === 'BRL';
	}

	/**
	 * @inheritDoc
	 */
	public function get_supported_currencies(): array
	{
		return array('BRL');
	}

	/**
	 * Setup credentials on the mercadopago_client.
	 */
	protected function init_client(): bool
	{
		$sandbox = $this->is_sandbox();
		$access_token = (string) ($sandbox
			? (isset($this->config['stevotvr_groupsub_gw_pix_sb_access_token']) ? $this->config['stevotvr_groupsub_gw_pix_sb_access_token'] : '')
			: (isset($this->config['stevotvr_groupsub_gw_pix_access_token']) ? $this->config['stevotvr_groupsub_gw_pix_access_token'] : ''));

		if ($access_token === '')
		{
			return false;
		}

		$this->mp_client->set_access_token($access_token);
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function create_payment(term_interface $term, int $user_id, array $context = array()): payment_result
	{
		if (!$this->supports_currency($term->get_currency()))
		{
			return new payment_result(false, 'error', '', '', '', array(), $this->language->lang('GROUPSUB_ERROR_PIX_CURRENCY'));
		}

		if (!$this->init_client())
		{
			return new payment_result(false, 'error', '', '', '', array(), $this->language->lang('GROUPSUB_ERROR_PIX_CREDENTIALS'));
		}

		$amount = (float) $this->currency->format_price_raw($term->get_currency(), $term->get_price());
		$email = isset($context['user_email']) && $context['user_email'] !== '' ? (string) $context['user_email'] : (string) $this->user->data['user_email'];
		$payer_name = isset($context['username']) && $context['username'] !== '' ? (string) $context['username'] : (string) $this->user->data['username'];
		$description = $this->language->lang('GROUPSUB_SUBSCRIPTION');
		$external_reference = sprintf('groupsub_term_%d_user_%d_%d', $term->get_id(), $user_id, time());
		$notification_url = $this->get_webhook_url();

		$response = $this->mp_client->create_pix_payment(
			$amount,
			$description,
			$email,
			$payer_name,
			$external_reference,
			$notification_url
		);

		if (empty($response['id']))
		{
			$err_detail = '';
			if (!empty($response['message']))
			{
				$err_detail = (string) $response['message'];
				if (stripos($err_detail, 'communication_error') !== false)
				{
					$err_detail = $this->language->lang('GROUPSUB_ERROR_PIX_KEY_REQUIRED');
				}
			}
			else if (!empty($response['cause'][0]['description']))
			{
				$err_detail = (string) $response['cause'][0]['description'];
			}

			$error_msg = $err_detail !== '' ? $err_detail : $this->language->lang('GROUPSUB_ERROR_PIX_CREATION');
			return new payment_result(false, 'error', '', '', '', array(), $error_msg);
		}

		$payment_id = (string) $response['id'];
		$poi = isset($response['point_of_interaction']['transaction_data']) ? $response['point_of_interaction']['transaction_data'] : array();

		$qr_code = isset($poi['qr_code']) ? (string) $poi['qr_code'] : '';
		$qr_code_base64 = isset($poi['qr_code_base64']) ? (string) $poi['qr_code_base64'] : '';
		$ticket_url = isset($poi['ticket_url']) ? (string) $poi['ticket_url'] : '';
		$exp_date = isset($response['date_of_expiration']) ? (string) $response['date_of_expiration'] : '';

		return new payment_result(
			true,
			'pending',
			$payment_id,
			$payment_id,
			'',
			array(
				'payment_id'         => $payment_id,
				'qr_code'            => $qr_code,
				'qr_code_base64'     => $qr_code_base64,
				'ticket_url'         => $ticket_url,
				'date_of_expiration' => $exp_date,
				'expires_at'         => $exp_date,
			)
		);
	}

	/**
	 * Get payment status directly by ID (for polling).
	 *
	 * @param string $payment_id
	 *
	 * @return array
	 */
	public function get_payment_status(string $payment_id): array
	{
		if (!$this->init_client())
		{
			return array('success' => false, 'status' => 'error');
		}

		$payment = $this->mp_client->get_payment($payment_id);
		if (empty($payment['id']))
		{
			return array('success' => false, 'status' => 'not_found');
		}

		$status = isset($payment['status']) ? (string) $payment['status'] : 'pending';
		return array(
			'success'            => true,
			'status'             => $status,
			'payment'            => $payment,
			'external_reference' => isset($payment['external_reference']) ? (string) $payment['external_reference'] : '',
			'transaction_amount' => isset($payment['transaction_amount']) ? (float) $payment['transaction_amount'] : 0.00,
			'payer_id'           => isset($payment['payer']['id']) ? (string) $payment['payer']['id'] : '',
			'payment_id'         => (string) $payment['id'],
		);
	}

	/**
	 * @inheritDoc
	 */
	public function handle_webhook(Request $request): webhook_result
	{
		if (!$this->init_client())
		{
			return new webhook_result(false, '', 'error', '', '', '', '0.00', '', '', array(), $this->language->lang('GROUPSUB_ERROR_PIX_CREDENTIALS'));
		}

		$raw_body = (string) $request->getContent();
		if ($raw_body === '')
		{
			$raw_body = (string) @file_get_contents('php://input');
		}

		$body = json_decode($raw_body, true);
		$data_id = '';
		$action = '';

		if (is_array($body))
		{
			$action = isset($body['action']) ? (string) $body['action'] : (isset($body['type']) ? (string) $body['type'] : '');
			if (isset($body['data']['id']))
			{
				$data_id = (string) $body['data']['id'];
			}
		}

		if ($data_id === '')
		{
			$data_id = (string) $request->query->get('data_id', $request->query->get('id', ''));
		}

		if ($data_id === '')
		{
			return new webhook_result(false, '', 'error', '', '', '', '0.00', '', '', array(), $this->language->lang('GROUPSUB_ERROR_EMPTY_BODY'));
		}

		// Verify signature if secret configured
		$sandbox = $this->is_sandbox();
		$webhook_secret = (string) ($sandbox
			? (isset($this->config['stevotvr_groupsub_gw_pix_sb_webhook_secret']) ? $this->config['stevotvr_groupsub_gw_pix_sb_webhook_secret'] : '')
			: (isset($this->config['stevotvr_groupsub_gw_pix_webhook_secret']) ? $this->config['stevotvr_groupsub_gw_pix_webhook_secret'] : ''));

		if ($webhook_secret !== '')
		{
			$headers = $request->headers->all();
			$is_valid = $this->mp_client->verify_webhook_signature($headers, $data_id, $webhook_secret);
			if (!$is_valid)
			{
				return new webhook_result(false, '', 'error', '', '', '', '0.00', '', '', array(), $this->language->lang('GROUPSUB_ERROR_INVALID_WEBHOOK_SIG'));
			}
		}

		// Fetch the authoritative payment status from Mercado Pago API
		$payment = $this->mp_client->get_payment($data_id);
		if (empty($payment['id']))
		{
			return new webhook_result(false, $action, 'error', '', '', '', '0.00', '', '', array(), $this->language->lang('GROUPSUB_ERROR_INVALID_JSON_EVENT'));
		}

		$mp_status = isset($payment['status']) ? (string) $payment['status'] : '';
		$amount = isset($payment['transaction_amount']) ? (string) number_format((float) $payment['transaction_amount'], 2, '.', '') : '0.00';
		$payer_id = isset($payment['payer']['id']) ? (string) $payment['payer']['id'] : '';

		if ($mp_status === 'approved')
		{
			return new webhook_result(true, 'payment.approved', 'completed', $data_id, $data_id, '', $amount, 'BRL', $payer_id, $payment);
		}
		else if (in_array($mp_status, array('cancelled', 'rejected', 'refunded'), true))
		{
			return new webhook_result(true, 'payment.' . $mp_status, 'cancelled', $data_id, $data_id, '', $amount, 'BRL', $payer_id, $payment);
		}

		return new webhook_result(true, 'payment.pending', 'pending', $data_id, $data_id, '', $amount, 'BRL', $payer_id, $payment);
	}

	/**
	 * @inheritDoc
	 */
	public function get_acp_fieldsets(): array
	{
		$webhook_url = $this->get_webhook_url();
		$name = $this->get_name();

		return array(
			array(
				'legend' => 'ACP_GROUPSUB_SETTINGS_GENERAL',
				'fields' => array(
					'enabled' => array(
						'type'    => 'radio',
						'title'   => 'ACP_GROUPSUB_GW_ENABLE',
						'explain' => 'ACP_GROUPSUB_GW_ENABLE_EXPLAIN',
						'value'   => $this->is_enabled(),
					),
					'sandbox' => array(
						'type'    => 'radio',
						'title'   => 'ACP_GROUPSUB_GW_SANDBOX_MODE',
						'explain' => $this->language->lang('ACP_GROUPSUB_GW_SANDBOX_MODE_EXPLAIN', $name),
						'value'   => $this->is_sandbox(),
					),
				),
			),
			array(
				'legend'  => $this->language->lang('ACP_GROUPSUB_SETTINGS_LIVE_API', $name),
				'explain' => $this->language->lang('ACP_GROUPSUB_GW_PIX_API_EXPLAIN', $this->language->lang('ACP_GROUPSUB_LIVE')),
				'fields'  => array(
					'public_key' => array(
						'type'    => 'text',
						'title'   => 'ACP_GROUPSUB_GW_PIX_PUBLIC_KEY',
						'value'   => (string) (isset($this->config['stevotvr_groupsub_gw_pix_public_key']) ? $this->config['stevotvr_groupsub_gw_pix_public_key'] : ''),
					),
					'access_token' => array(
						'type'    => 'password',
						'title'   => 'ACP_GROUPSUB_GW_PIX_ACCESS_TOKEN',
						'value'   => (string) (isset($this->config['stevotvr_groupsub_gw_pix_access_token']) ? $this->config['stevotvr_groupsub_gw_pix_access_token'] : ''),
					),
					'webhook_secret' => array(
						'type'    => 'password',
						'title'   => 'ACP_GROUPSUB_GW_PIX_SECRET',
						'explain' => 'ACP_GROUPSUB_GW_PIX_SECRET_EXPLAIN',
						'value'   => (string) (isset($this->config['stevotvr_groupsub_gw_pix_webhook_secret']) ? $this->config['stevotvr_groupsub_gw_pix_webhook_secret'] : ''),
					),
				),
			),
			array(
				'legend'  => $this->language->lang('ACP_GROUPSUB_SETTINGS_SB_API', $name),
				'explain' => $this->language->lang('ACP_GROUPSUB_GW_PIX_API_EXPLAIN', $this->language->lang('ACP_GROUPSUB_SANDBOX')),
				'fields'  => array(
					'sb_public_key' => array(
						'type'    => 'text',
						'title'   => 'ACP_GROUPSUB_GW_PIX_SB_PUBLIC_KEY',
						'value'   => (string) (isset($this->config['stevotvr_groupsub_gw_pix_sb_public_key']) ? $this->config['stevotvr_groupsub_gw_pix_sb_public_key'] : ''),
					),
					'sb_access_token' => array(
						'type'    => 'password',
						'title'   => 'ACP_GROUPSUB_GW_PIX_SB_ACCESS_TOKEN',
						'value'   => (string) (isset($this->config['stevotvr_groupsub_gw_pix_sb_access_token']) ? $this->config['stevotvr_groupsub_gw_pix_sb_access_token'] : ''),
					),
					'sb_webhook_secret' => array(
						'type'    => 'password',
						'title'   => 'ACP_GROUPSUB_GW_PIX_SECRET',
						'explain' => 'ACP_GROUPSUB_GW_PIX_SECRET_EXPLAIN',
						'value'   => (string) (isset($this->config['stevotvr_groupsub_gw_pix_sb_webhook_secret']) ? $this->config['stevotvr_groupsub_gw_pix_sb_webhook_secret'] : ''),
					),
				),
			),
			array(
				'legend'      => $this->language->lang('ACP_GROUPSUB_WEBHOOK_GUIDE_TITLE', $name),
				'explain'     => $this->language->lang('ACP_GROUPSUB_WEBHOOK_GUIDE_EXPLAIN', $name),
				'webhook_url' => $webhook_url,
				'events'      => array(
					array('code' => 'payment', 'name' => $this->language->lang('ACP_GROUPSUB_EVENT_PAYMENT_SALE_COMPLETED')),
				),
			),
		);
	}

	/**
	 * @inheritDoc
	 */
	public function get_acp_fields(): array
	{
		$fields = array();
		foreach ($this->get_acp_fieldsets() as $fieldset)
		{
			if (isset($fieldset['fields']) && is_array($fieldset['fields']))
			{
				$fields = array_merge($fields, $fieldset['fields']);
			}
		}
		return $fields;
	}

	/**
	 * @inheritDoc
	 */
	public function save_acp_fields(array $data): void
	{
		if (isset($data['enabled']))
		{
			$this->config->set('stevotvr_groupsub_gw_pix_enabled', (int) $data['enabled']);
		}
		if (isset($data['sandbox']))
		{
			$this->config->set('stevotvr_groupsub_gw_pix_sandbox', (int) $data['sandbox']);
		}
		if (isset($data['public_key']))
		{
			$this->config->set('stevotvr_groupsub_gw_pix_public_key', trim($data['public_key']));
		}
		if (isset($data['access_token']))
		{
			$this->config->set('stevotvr_groupsub_gw_pix_access_token', trim($data['access_token']));
		}
		if (isset($data['webhook_secret']))
		{
			$this->config->set('stevotvr_groupsub_gw_pix_webhook_secret', trim($data['webhook_secret']));
		}
		if (isset($data['sb_public_key']))
		{
			$this->config->set('stevotvr_groupsub_gw_pix_sb_public_key', trim($data['sb_public_key']));
		}
		if (isset($data['sb_access_token']))
		{
			$this->config->set('stevotvr_groupsub_gw_pix_sb_access_token', trim($data['sb_access_token']));
		}
		if (isset($data['sb_webhook_secret']))
		{
			$this->config->set('stevotvr_groupsub_gw_pix_sb_webhook_secret', trim($data['sb_webhook_secret']));
		}
	}

	/**
	 * @inheritDoc
	 */
	public function validate_acp_fields(array $data): array
	{
		$errors = array();
		if (!empty($data['enabled']))
		{
			$is_sandbox = !empty($data['sandbox']);
			$token = $is_sandbox ? (isset($data['sb_access_token']) ? trim($data['sb_access_token']) : '') : (isset($data['access_token']) ? trim($data['access_token']) : '');

			if ($token === '')
			{
				$mode_name = $is_sandbox ? $this->language->lang('ACP_GROUPSUB_SANDBOX') : $this->language->lang('ACP_GROUPSUB_LIVE');
				$errors[] = $this->language->lang('ACP_GROUPSUB_GW_ERROR_CREDENTIALS', $this->get_name() . ' - ' . $mode_name);
			}
		}
		return $errors;
	}
}
