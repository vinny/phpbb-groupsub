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
use stevotvr\groupsub\entity\term_interface;
use stevotvr\groupsub\operator\currency_interface;
use stevotvr\groupsub\operator\paypal_client_interface;
use stevotvr\groupsub\payment\payment_result;
use stevotvr\groupsub\payment\provider_base;
use stevotvr\groupsub\payment\webhook_result;
use Symfony\Component\HttpFoundation\Request;

/**
 * PayPal Payment Provider implementation.
 */
class paypal_provider extends provider_base
{
	/**
	 * @var paypal_client_interface
	 */
	protected paypal_client_interface $paypal_client;

	/**
	 * @var currency_interface
	 */
	protected currency_interface $currency;

	/**
	 * Constructor.
	 *
	 * @param config                  $config
	 * @param language                $language
	 * @param helper                  $helper
	 * @param paypal_client_interface $paypal_client
	 * @param currency_interface      $currency
	 */
	public function __construct(
		config $config,
		language $language,
		helper $helper,
		paypal_client_interface $paypal_client,
		currency_interface $currency
	)
	{
		parent::__construct($config, $language, $helper);
		$this->paypal_client = $paypal_client;
		$this->currency = $currency;
	}

	/**
	 * @inheritDoc
	 */
	public function get_id(): string
	{
		return 'paypal';
	}

	/**
	 * @inheritDoc
	 */
	public function get_name(): string
	{
		return $this->language->lang('ACP_GROUPSUB_GATEWAY_NAME_PAYPAL');
	}

	/**
	 * @inheritDoc
	 */
	public function is_enabled(): bool
	{
		// Default to enabled or check gw setting
		return isset($this->config['stevotvr_groupsub_gw_paypal_enabled'])
			? !empty($this->config['stevotvr_groupsub_gw_paypal_enabled'])
			: !empty($this->config['stevotvr_groupsub_active']);
	}

	/**
	 * @inheritDoc
	 */
	public function is_sandbox(): bool
	{
		return !empty($this->config['stevotvr_groupsub_pp_sandbox']);
	}

	/**
	 * @inheritDoc
	 */
	public function supports_recurring(): bool
	{
		return true;
	}

	/**
	 * Setup credentials on the paypal_client.
	 */
	protected function init_client(): bool
	{
		$sandbox = $this->is_sandbox();
		$client_id = (string) ($sandbox
			? (isset($this->config['stevotvr_groupsub_sb_client']) ? $this->config['stevotvr_groupsub_sb_client'] : '')
			: (isset($this->config['stevotvr_groupsub_pp_client']) ? $this->config['stevotvr_groupsub_pp_client'] : ''));
		$client_secret = (string) ($sandbox
			? (isset($this->config['stevotvr_groupsub_sb_secret']) ? $this->config['stevotvr_groupsub_sb_secret'] : '')
			: (isset($this->config['stevotvr_groupsub_pp_secret']) ? $this->config['stevotvr_groupsub_pp_secret'] : ''));

		if ($client_id === '' || $client_secret === '')
		{
			return false;
		}

		$this->paypal_client->set_credentials($client_id, $client_secret, $sandbox);
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function create_payment(term_interface $term, int $user_id, array $context = array()): payment_result
	{
		if (!$this->init_client())
		{
			return new payment_result(false, 'error', '', '', '', array(), $this->language->lang('GROUPSUB_ERROR_PP_CREDENTIALS'));
		}

		$currency_code = $term->get_currency();
		$price_val = $this->currency->format_price_raw($currency_code, $term->get_price());

		$order_response = $this->paypal_client->create_order(
			$currency_code,
			$price_val,
			$this->language->lang('GROUPSUB_SUBSCRIPTION'),
			(string) $term->get_id()
		);

		if (empty($order_response['id']))
		{
			return new payment_result(false, 'error', '', '', '', array(), $this->language->lang('GROUPSUB_ERROR_ORDER_CREATION'));
		}

		return new payment_result(
			true,
			'pending',
			(string) $order_response['id'],
			(string) $order_response['id'],
			'',
			array('order_id' => $order_response['id'])
		);
	}

	/**
	 * @inheritDoc
	 */
	public function capture_payment(string $order_id, array $context = array()): payment_result
	{
		if (!$this->init_client())
		{
			return new payment_result(false, 'error', '', '', '', array(), $this->language->lang('GROUPSUB_ERROR_PP_CREDENTIALS'));
		}

		if ($order_id === '')
		{
			return new payment_result(false, 'error', '', '', '', array(), $this->language->lang('GROUPSUB_ERROR_MISSING_ORDER'));
		}

		$capture_response = $this->paypal_client->capture_order($order_id);
		if (empty($capture_response['id']))
		{
			return new payment_result(false, 'error', '', '', '', array(), $this->language->lang('GROUPSUB_ERROR_ORDER_CAPTURE'));
		}

		$status = isset($capture_response['status']) ? (string) $capture_response['status'] : '';
		$is_completed = ($status === 'COMPLETED');

		$purchase_units = isset($capture_response['purchase_units'][0]) ? $capture_response['purchase_units'][0] : array();
		$payments = isset($purchase_units['payments']) ? $purchase_units['payments'] : array();
		$captures = isset($payments['captures'][0]) ? $payments['captures'][0] : array();

		$capture_id = isset($captures['id']) ? (string) $captures['id'] : $capture_response['id'];
		$amount = isset($captures['amount']['value']) ? (string) $captures['amount']['value'] : '0.00';
		$currency = isset($captures['amount']['currency_code']) ? (string) $captures['amount']['currency_code'] : '';
		$payer_id = isset($capture_response['payer']['payer_id']) ? (string) $capture_response['payer']['payer_id'] : '';

		return new payment_result(
			$is_completed,
			$is_completed ? 'completed' : strtolower($status),
			$capture_id,
			$order_id,
			'',
			$capture_response,
			$is_completed ? '' : $this->language->lang('GROUPSUB_ERROR_ORDER_CAPTURE')
		);
	}

	/**
	 * @inheritDoc
	 */
	public function cancel_subscription(string $sub_id, string $reason = ''): bool
	{
		if (!$this->init_client())
		{
			return false;
		}

		return $this->paypal_client->cancel_subscription($sub_id, $reason);
	}

	/**
	 * @inheritDoc
	 */
	public function handle_webhook(Request $request): webhook_result
	{
		if (!$this->init_client())
		{
			return new webhook_result(false, '', 'error', '', '', '', '0.00', '', '', array(), $this->language->lang('GROUPSUB_ERROR_PP_CREDENTIALS'));
		}

		$raw_body = (string) $request->getContent();
		if ($raw_body === '')
		{
			$raw_body = (string) @file_get_contents('php://input');
		}

		if ($raw_body === '')
		{
			return new webhook_result(false, '', 'error', '', '', '', '0.00', '', '', array(), $this->language->lang('GROUPSUB_ERROR_EMPTY_BODY'));
		}

		$headers = $request->headers->all();
		$sandbox = $this->is_sandbox();
		$webhook_id = (string) ($sandbox
			? (isset($this->config['stevotvr_groupsub_sb_webhook_id']) ? $this->config['stevotvr_groupsub_sb_webhook_id'] : '')
			: (isset($this->config['stevotvr_groupsub_webhook_id']) ? $this->config['stevotvr_groupsub_webhook_id'] : ''));

		if ($webhook_id !== '')
		{
			$is_valid = $this->paypal_client->verify_webhook_signature($headers, $raw_body, $webhook_id);
			if (!$is_valid)
			{
				return new webhook_result(false, '', 'error', '', '', '', '0.00', '', '', array(), $this->language->lang('GROUPSUB_ERROR_INVALID_WEBHOOK_SIG'));
			}
		}

		$event = json_decode($raw_body, true);
		if (!is_array($event) || empty($event['event_type']))
		{
			return new webhook_result(false, '', 'error', '', '', '', '0.00', '', '', array(), $this->language->lang('GROUPSUB_ERROR_INVALID_JSON_EVENT'));
		}

		$event_type = (string) $event['event_type'];
		$resource = isset($event['resource']) && is_array($event['resource']) ? $event['resource'] : array();

		switch ($event_type)
		{
			case 'PAYMENT.SALE.COMPLETED':
				$sub_id = isset($resource['billing_agreement_id']) ? (string) $resource['billing_agreement_id'] : '';
				$trans_id = isset($resource['id']) ? (string) $resource['id'] : '';
				$amount = isset($resource['amount']['total']) ? (string) $resource['amount']['total'] : '0.00';
				$currency = isset($resource['amount']['currency']) ? (string) $resource['amount']['currency'] : '';

				return new webhook_result(true, $event_type, 'completed', $trans_id, '', $sub_id, $amount, $currency, '', $event);

			case 'BILLING.SUBSCRIPTION.CANCELLED':
			case 'BILLING.SUBSCRIPTION.EXPIRED':
			case 'BILLING.SUBSCRIPTION.SUSPENDED':
				$sub_id = isset($resource['id']) ? (string) $resource['id'] : '';
				return new webhook_result(true, $event_type, 'cancelled', '', '', $sub_id, '0.00', '', '', $event);

			default:
				return new webhook_result(true, $event_type, 'ignored', '', '', '', '0.00', '', '', $event);
		}
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
				'explain' => $this->language->lang('ACP_GROUPSUB_PP_API_EXPLAIN', $this->language->lang('ACP_GROUPSUB_LIVE')),
				'fields'  => array(
					'client_id' => array(
						'type'    => 'text',
						'title'   => 'ACP_GROUPSUB_PP_CLIENT',
						'value'   => (string) (isset($this->config['stevotvr_groupsub_pp_client']) ? $this->config['stevotvr_groupsub_pp_client'] : ''),
					),
					'client_secret' => array(
						'type'    => 'password',
						'title'   => 'ACP_GROUPSUB_PP_SECRET',
						'value'   => (string) (isset($this->config['stevotvr_groupsub_pp_secret']) ? $this->config['stevotvr_groupsub_pp_secret'] : ''),
					),
					'webhook_id' => array(
						'type'    => 'text',
						'title'   => 'ACP_GROUPSUB_WEBHOOK_ID',
						'explain' => 'ACP_GROUPSUB_WEBHOOK_ID_EXPLAIN',
						'value'   => (string) (isset($this->config['stevotvr_groupsub_webhook_id']) ? $this->config['stevotvr_groupsub_webhook_id'] : ''),
					),
				),
			),
			array(
				'legend'  => $this->language->lang('ACP_GROUPSUB_SETTINGS_SB_API', $name),
				'explain' => $this->language->lang('ACP_GROUPSUB_PP_API_EXPLAIN', $this->language->lang('ACP_GROUPSUB_SANDBOX')),
				'fields'  => array(
					'sb_client_id' => array(
						'type'    => 'text',
						'title'   => 'ACP_GROUPSUB_PP_CLIENT',
						'value'   => (string) (isset($this->config['stevotvr_groupsub_sb_client']) ? $this->config['stevotvr_groupsub_sb_client'] : ''),
					),
					'sb_client_secret' => array(
						'type'    => 'password',
						'title'   => 'ACP_GROUPSUB_PP_SECRET',
						'value'   => (string) (isset($this->config['stevotvr_groupsub_sb_secret']) ? $this->config['stevotvr_groupsub_sb_secret'] : ''),
					),
					'sb_webhook_id' => array(
						'type'    => 'text',
						'title'   => 'ACP_GROUPSUB_WEBHOOK_ID',
						'explain' => 'ACP_GROUPSUB_WEBHOOK_ID_EXPLAIN',
						'value'   => (string) (isset($this->config['stevotvr_groupsub_sb_webhook_id']) ? $this->config['stevotvr_groupsub_sb_webhook_id'] : ''),
					),
				),
			),
			array(
				'legend'      => $this->language->lang('ACP_GROUPSUB_WEBHOOK_GUIDE_TITLE', $name),
				'explain'     => $this->language->lang('ACP_GROUPSUB_WEBHOOK_GUIDE_EXPLAIN', $name),
				'webhook_url' => $webhook_url,
				'events'      => array(
					array('code' => 'PAYMENT.SALE.COMPLETED', 'name' => $this->language->lang('ACP_GROUPSUB_EVENT_PAYMENT_SALE_COMPLETED')),
					array('code' => 'BILLING.SUBSCRIPTION.ACTIVATED', 'name' => $this->language->lang('ACP_GROUPSUB_EVENT_BILLING_SUB_ACTIVATED')),
					array('code' => 'BILLING.SUBSCRIPTION.CANCELLED', 'name' => $this->language->lang('ACP_GROUPSUB_EVENT_BILLING_SUB_CANCELLED')),
					array('code' => 'BILLING.SUBSCRIPTION.CREATED', 'name' => $this->language->lang('ACP_GROUPSUB_EVENT_BILLING_SUB_CREATED')),
					array('code' => 'BILLING.SUBSCRIPTION.EXPIRED', 'name' => $this->language->lang('ACP_GROUPSUB_EVENT_BILLING_SUB_EXPIRED')),
					array('code' => 'BILLING.SUBSCRIPTION.PAYMENT_FAILED', 'name' => $this->language->lang('ACP_GROUPSUB_EVENT_BILLING_SUB_PAYMENT_FAILED')),
					array('code' => 'BILLING.SUBSCRIPTION.RE-ACTIVATED', 'name' => $this->language->lang('ACP_GROUPSUB_EVENT_BILLING_SUB_RE_ACTIVATED')),
					array('code' => 'BILLING.SUBSCRIPTION.SUSPENDED', 'name' => $this->language->lang('ACP_GROUPSUB_EVENT_BILLING_SUB_SUSPENDED')),
					array('code' => 'BILLING.SUBSCRIPTION.UPDATED', 'name' => $this->language->lang('ACP_GROUPSUB_EVENT_BILLING_SUB_UPDATED')),
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
			$this->config->set('stevotvr_groupsub_gw_paypal_enabled', (int) $data['enabled']);
		}
		if (isset($data['sandbox']))
		{
			$this->config->set('stevotvr_groupsub_pp_sandbox', (int) $data['sandbox']);
		}
		if (isset($data['client_id']))
		{
			$this->config->set('stevotvr_groupsub_pp_client', trim($data['client_id']));
		}
		if (isset($data['client_secret']))
		{
			$this->config->set('stevotvr_groupsub_pp_secret', trim($data['client_secret']));
		}
		if (isset($data['webhook_id']))
		{
			$this->config->set('stevotvr_groupsub_webhook_id', trim($data['webhook_id']));
		}
		if (isset($data['sb_client_id']))
		{
			$this->config->set('stevotvr_groupsub_sb_client', trim($data['sb_client_id']));
		}
		if (isset($data['sb_client_secret']))
		{
			$this->config->set('stevotvr_groupsub_sb_secret', trim($data['sb_client_secret']));
		}
		if (isset($data['sb_webhook_id']))
		{
			$this->config->set('stevotvr_groupsub_sb_webhook_id', trim($data['sb_webhook_id']));
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
			$client = $is_sandbox ? (isset($data['sb_client_id']) ? trim($data['sb_client_id']) : '') : (isset($data['client_id']) ? trim($data['client_id']) : '');
			$secret = $is_sandbox ? (isset($data['sb_client_secret']) ? trim($data['sb_client_secret']) : '') : (isset($data['client_secret']) ? trim($data['client_secret']) : '');

			if ($client === '' || $secret === '')
			{
				$mode_name = $is_sandbox ? $this->language->lang('ACP_GROUPSUB_SANDBOX') : $this->language->lang('ACP_GROUPSUB_LIVE');
				$errors[] = $this->language->lang('ACP_GROUPSUB_GW_ERROR_CREDENTIALS', $this->get_name() . ' - ' . $mode_name);
			}
		}
		return $errors;
	}
}
