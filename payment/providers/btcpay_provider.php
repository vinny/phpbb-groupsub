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
use stevotvr\groupsub\operator\btcpay_client_interface;
use stevotvr\groupsub\operator\currency_interface;
use stevotvr\groupsub\payment\payment_result;
use stevotvr\groupsub\payment\provider_base;
use stevotvr\groupsub\payment\webhook_result;
use Symfony\Component\HttpFoundation\Request;

/**
 * BTCPay Server Payment Provider implementation.
 */
class btcpay_provider extends provider_base
{
	/**
	 * @var btcpay_client_interface
	 */
	protected btcpay_client_interface $btcpay_client;

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
	 * @param config                  $config
	 * @param language                $language
	 * @param helper                  $helper
	 * @param btcpay_client_interface $btcpay_client
	 * @param currency_interface      $currency
	 * @param user                    $user
	 */
	public function __construct(
		config $config,
		language $language,
		helper $helper,
		btcpay_client_interface $btcpay_client,
		currency_interface $currency,
		user $user
	)
	{
		parent::__construct($config, $language, $helper);
		$this->btcpay_client = $btcpay_client;
		$this->currency = $currency;
		$this->user = $user;
	}

	/**
	 * @inheritDoc
	 */
	public function get_id(): string
	{
		return 'btcpay';
	}

	/**
	 * @inheritDoc
	 */
	public function get_name(): string
	{
		return $this->language->lang('ACP_GROUPSUB_GATEWAY_NAME_BTCPAY');
	}

	/**
	 * @inheritDoc
	 */
	public function is_enabled(): bool
	{
		return !empty($this->config['stevotvr_groupsub_gw_btcpay_enabled']);
	}

	/**
	 * @inheritDoc
	 */
	public function is_sandbox(): bool
	{
		return !empty($this->config['stevotvr_groupsub_gw_btcpay_sandbox']);
	}

	/**
	 * @inheritDoc
	 */
	public function supports_recurring(): bool
	{
		return false;
	}

	/**
	 * Setup credentials on the BTCPay client.
	 */
	protected function init_client(): bool
	{
		$server_url = (string) (isset($this->config['stevotvr_groupsub_gw_btcpay_server_url']) ? $this->config['stevotvr_groupsub_gw_btcpay_server_url'] : '');
		$store_id = (string) (isset($this->config['stevotvr_groupsub_gw_btcpay_store_id']) ? $this->config['stevotvr_groupsub_gw_btcpay_store_id'] : '');
		$api_key = (string) (isset($this->config['stevotvr_groupsub_gw_btcpay_api_key']) ? $this->config['stevotvr_groupsub_gw_btcpay_api_key'] : '');

		if ($this->is_sandbox())
		{
			$server_url = (string) (isset($this->config['stevotvr_groupsub_gw_btcpay_sb_server_url']) ? $this->config['stevotvr_groupsub_gw_btcpay_sb_server_url'] : '');
			$store_id = (string) (isset($this->config['stevotvr_groupsub_gw_btcpay_sb_store_id']) ? $this->config['stevotvr_groupsub_gw_btcpay_sb_store_id'] : '');
			$api_key = (string) (isset($this->config['stevotvr_groupsub_gw_btcpay_sb_api_key']) ? $this->config['stevotvr_groupsub_gw_btcpay_sb_api_key'] : '');
		}

		if ($server_url === '' || $store_id === '' || $api_key === '')
		{
			return false;
		}

		$this->btcpay_client->set_credentials($server_url, $store_id, $api_key);
		return true;
	}

	/**
	 * Verify an invoice status directly with BTCPay Greenfield API.
	 *
	 * @param string $invoice_id
	 *
	 * @return array
	 */
	public function verify_invoice(string $invoice_id): array
	{
		if (!$this->init_client())
		{
			return array('valid' => false, 'status' => 'error', 'error' => $this->language->lang('GROUPSUB_ERROR_BTCPAY_CREDENTIALS'));
		}

		$invoice = $this->btcpay_client->get_invoice($invoice_id);
		if (empty($invoice['id']))
		{
			return array('valid' => false, 'status' => 'not_found');
		}

		$status = isset($invoice['status']) ? (string) $invoice['status'] : '';
		$metadata = isset($invoice['metadata']) && is_array($invoice['metadata']) ? $invoice['metadata'] : array();
		$term_id = isset($metadata['termId']) ? (int) $metadata['termId'] : 0;
		$user_id = isset($metadata['userId']) ? (int) $metadata['userId'] : 0;

		$is_paid = in_array(strtolower($status), array('settled', 'complete', 'processing'), true);

		return array(
			'valid'       => $is_paid,
			'status'      => strtolower($status),
			'invoice_id'  => (string) $invoice['id'],
			'term_id'     => $term_id,
			'user_id'     => $user_id,
			'amount'      => isset($invoice['amount']) ? (string) $invoice['amount'] : '0.00',
			'currency'    => isset($invoice['currency']) ? (string) $invoice['currency'] : '',
			'buyer_email' => isset($metadata['buyerEmail']) ? (string) $metadata['buyerEmail'] : '',
			'invoice'     => $invoice,
		);
	}

	/**
	 * @inheritDoc
	 */
	public function create_payment(term_interface $term, int $user_id, array $context = array()): payment_result
	{
		if (!$this->init_client())
		{
			return new payment_result(false, 'error', '', '', '', array(), $this->language->lang('GROUPSUB_ERROR_BTCPAY_CREDENTIALS'));
		}

		$currency_code = strtoupper($term->get_currency());
		$price_val = $this->currency->format_value($currency_code, $term->get_price(), false, false);
		$email = isset($context['user_email']) && $context['user_email'] !== '' ? $context['user_email'] : $this->user->data['user_email'];
		$success_url = isset($context['success_url']) ? $context['success_url'] : '';

		$params = array(
			'amount'   => $price_val,
			'currency' => $currency_code,
			'metadata' => array(
				'orderId'    => 'groupsub_' . $user_id . '_' . $term->get_id() . '_' . time(),
				'buyerEmail' => $email,
				'userId'     => (string) $user_id,
				'termId'     => (string) $term->get_id(),
				'pkgId'      => (string) $term->get_package(),
			),
			'checkout' => array(
				'redirectURL'           => $success_url,
				'redirectAutomatically' => true,
			),
		);

		$invoice = $this->btcpay_client->create_invoice($params);

		if (empty($invoice['id']) || empty($invoice['checkoutLink']))
		{
			return new payment_result(false, 'error', '', '', '', array(), $this->language->lang('GROUPSUB_ERROR_BTCPAY_CREATION'));
		}

		return new payment_result(
			true,
			'redirect',
			(string) $invoice['id'],
			(string) $invoice['id'],
			(string) $invoice['checkoutLink'],
			$invoice
		);
	}

	/**
	 * @inheritDoc
	 */
	public function handle_webhook(Request $request): webhook_result
	{
		if (!$this->init_client())
		{
			return new webhook_result(false, '', 'error', '', '', '', '0.00', '', '', array(), $this->language->lang('GROUPSUB_ERROR_BTCPAY_CREDENTIALS'));
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

		$webhook_secret = (string) ($this->is_sandbox()
			? (isset($this->config['stevotvr_groupsub_gw_btcpay_sb_webhook_secret']) ? $this->config['stevotvr_groupsub_gw_btcpay_sb_webhook_secret'] : '')
			: (isset($this->config['stevotvr_groupsub_gw_btcpay_webhook_secret']) ? $this->config['stevotvr_groupsub_gw_btcpay_webhook_secret'] : ''));

		if ($webhook_secret !== '')
		{
			$headers = $request->headers->all();
			$is_valid = $this->btcpay_client->verify_webhook_signature($headers, $raw_body, $webhook_secret);
			if (!$is_valid)
			{
				return new webhook_result(false, '', 'error', '', '', '', '0.00', '', '', array(), $this->language->lang('GROUPSUB_ERROR_INVALID_WEBHOOK_SIG'));
			}
		}

		$event = json_decode($raw_body, true);
		if (!is_array($event) || empty($event['type']))
		{
			return new webhook_result(false, '', 'error', '', '', '', '0.00', '', '', array(), $this->language->lang('GROUPSUB_ERROR_INVALID_JSON_EVENT'));
		}

		$event_type = (string) $event['type'];
		$invoice_id = isset($event['invoiceId']) ? (string) $event['invoiceId'] : '';

		if ($invoice_id === '')
		{
			return new webhook_result(false, $event_type, 'error', '', '', '', '0.00', '', '', array(), $this->language->lang('GROUPSUB_ERROR_INVALID_JSON_EVENT'));
		}

		// Fetch authoritative invoice details
		$invoice = $this->btcpay_client->get_invoice($invoice_id);
		$amount = isset($invoice['amount']) ? (string) $invoice['amount'] : '0.00';
		$currency = isset($invoice['currency']) ? strtoupper((string) $invoice['currency']) : 'USD';

		switch ($event_type)
		{
			case 'InvoiceSettled':
			case 'InvoicePaymentSettled':
			case 'InvoiceProcessing':
				return new webhook_result(true, $event_type, 'completed', $invoice_id, $invoice_id, '', $amount, $currency, '', $invoice);

			case 'InvoiceExpired':
			case 'InvoiceInvalid':
				return new webhook_result(true, $event_type, 'cancelled', $invoice_id, $invoice_id, '', $amount, $currency, '', $invoice);

			default:
				return new webhook_result(true, $event_type, 'ignored', $invoice_id, $invoice_id, '', $amount, $currency, '', $invoice);
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
				'explain' => $this->language->lang('ACP_GROUPSUB_GW_BTCPAY_API_EXPLAIN', $this->language->lang('ACP_GROUPSUB_LIVE')),
				'fields'  => array(
					'server_url' => array(
						'type'    => 'text',
						'title'   => 'ACP_GROUPSUB_GW_BTCPAY_SERVER_URL',
						'explain' => 'ACP_GROUPSUB_GW_BTCPAY_SERVER_URL_EXPLAIN',
						'value'   => (string) (isset($this->config['stevotvr_groupsub_gw_btcpay_server_url']) ? $this->config['stevotvr_groupsub_gw_btcpay_server_url'] : ''),
					),
					'store_id' => array(
						'type'    => 'text',
						'title'   => 'ACP_GROUPSUB_GW_BTCPAY_STORE_ID',
						'value'   => (string) (isset($this->config['stevotvr_groupsub_gw_btcpay_store_id']) ? $this->config['stevotvr_groupsub_gw_btcpay_store_id'] : ''),
					),
					'api_key' => array(
						'type'    => 'password',
						'title'   => 'ACP_GROUPSUB_GW_BTCPAY_API_KEY',
						'value'   => (string) (isset($this->config['stevotvr_groupsub_gw_btcpay_api_key']) ? $this->config['stevotvr_groupsub_gw_btcpay_api_key'] : ''),
					),
					'webhook_secret' => array(
						'type'    => 'password',
						'title'   => 'ACP_GROUPSUB_GW_BTCPAY_WEBHOOK_SECRET',
						'explain' => 'ACP_GROUPSUB_GW_BTCPAY_WEBHOOK_SECRET_EXPLAIN',
						'value'   => (string) (isset($this->config['stevotvr_groupsub_gw_btcpay_webhook_secret']) ? $this->config['stevotvr_groupsub_gw_btcpay_webhook_secret'] : ''),
					),
				),
			),
			array(
				'legend'  => $this->language->lang('ACP_GROUPSUB_SETTINGS_SB_API', $name),
				'explain' => $this->language->lang('ACP_GROUPSUB_GW_BTCPAY_API_EXPLAIN', $this->language->lang('ACP_GROUPSUB_SANDBOX')),
				'fields'  => array(
					'sb_server_url' => array(
						'type'    => 'text',
						'title'   => 'ACP_GROUPSUB_GW_BTCPAY_SB_SERVER_URL',
						'explain' => 'ACP_GROUPSUB_GW_BTCPAY_SERVER_URL_EXPLAIN',
						'value'   => (string) (isset($this->config['stevotvr_groupsub_gw_btcpay_sb_server_url']) ? $this->config['stevotvr_groupsub_gw_btcpay_sb_server_url'] : ''),
					),
					'sb_store_id' => array(
						'type'    => 'text',
						'title'   => 'ACP_GROUPSUB_GW_BTCPAY_SB_STORE_ID',
						'value'   => (string) (isset($this->config['stevotvr_groupsub_gw_btcpay_sb_store_id']) ? $this->config['stevotvr_groupsub_gw_btcpay_sb_store_id'] : ''),
					),
					'sb_api_key' => array(
						'type'    => 'password',
						'title'   => 'ACP_GROUPSUB_GW_BTCPAY_SB_API_KEY',
						'value'   => (string) (isset($this->config['stevotvr_groupsub_gw_btcpay_sb_api_key']) ? $this->config['stevotvr_groupsub_gw_btcpay_sb_api_key'] : ''),
					),
					'sb_webhook_secret' => array(
						'type'    => 'password',
						'title'   => 'ACP_GROUPSUB_GW_BTCPAY_SB_WEBHOOK_SECRET',
						'explain' => 'ACP_GROUPSUB_GW_BTCPAY_WEBHOOK_SECRET_EXPLAIN',
						'value'   => (string) (isset($this->config['stevotvr_groupsub_gw_btcpay_sb_webhook_secret']) ? $this->config['stevotvr_groupsub_gw_btcpay_sb_webhook_secret'] : ''),
					),
				),
			),
			array(
				'legend'      => $this->language->lang('ACP_GROUPSUB_WEBHOOK_GUIDE_TITLE', $name),
				'explain'     => $this->language->lang('ACP_GROUPSUB_WEBHOOK_GUIDE_EXPLAIN', $name),
				'webhook_url' => $webhook_url,
				'events'      => array(
					array('code' => 'InvoiceSettled', 'name' => $this->language->lang('ACP_GROUPSUB_EVENT_PAYMENT_SALE_COMPLETED')),
					array('code' => 'InvoiceExpired', 'name' => $this->language->lang('ACP_GROUPSUB_EVENT_BILLING_SUB_EXPIRED')),
					array('code' => 'InvoiceInvalid', 'name' => $this->language->lang('ACP_GROUPSUB_EVENT_BILLING_SUB_CANCELLED')),
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
			$this->config->set('stevotvr_groupsub_gw_btcpay_enabled', (int) $data['enabled']);
		}
		if (isset($data['sandbox']))
		{
			$this->config->set('stevotvr_groupsub_gw_btcpay_sandbox', (int) $data['sandbox']);
		}
		if (isset($data['server_url']))
		{
			$this->config->set('stevotvr_groupsub_gw_btcpay_server_url', rtrim(trim($data['server_url']), '/'));
		}
		if (isset($data['store_id']))
		{
			$this->config->set('stevotvr_groupsub_gw_btcpay_store_id', trim($data['store_id']));
		}
		if (isset($data['api_key']))
		{
			$this->config->set('stevotvr_groupsub_gw_btcpay_api_key', trim($data['api_key']));
		}
		if (isset($data['webhook_secret']))
		{
			$this->config->set('stevotvr_groupsub_gw_btcpay_webhook_secret', trim($data['webhook_secret']));
		}
		if (isset($data['sb_server_url']))
		{
			$this->config->set('stevotvr_groupsub_gw_btcpay_sb_server_url', rtrim(trim($data['sb_server_url']), '/'));
		}
		if (isset($data['sb_store_id']))
		{
			$this->config->set('stevotvr_groupsub_gw_btcpay_sb_store_id', trim($data['sb_store_id']));
		}
		if (isset($data['sb_api_key']))
		{
			$this->config->set('stevotvr_groupsub_gw_btcpay_sb_api_key', trim($data['sb_api_key']));
		}
		if (isset($data['sb_webhook_secret']))
		{
			$this->config->set('stevotvr_groupsub_gw_btcpay_sb_webhook_secret', trim($data['sb_webhook_secret']));
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
			$server = $is_sandbox ? (isset($data['sb_server_url']) ? trim($data['sb_server_url']) : '') : (isset($data['server_url']) ? trim($data['server_url']) : '');
			$store = $is_sandbox ? (isset($data['sb_store_id']) ? trim($data['sb_store_id']) : '') : (isset($data['store_id']) ? trim($data['store_id']) : '');
			$api_key = $is_sandbox ? (isset($data['sb_api_key']) ? trim($data['sb_api_key']) : '') : (isset($data['api_key']) ? trim($data['api_key']) : '');

			if ($server === '' || $store === '' || $api_key === '')
			{
				$mode_name = $is_sandbox ? $this->language->lang('ACP_GROUPSUB_SANDBOX') : $this->language->lang('ACP_GROUPSUB_LIVE');
				$errors[] = $this->language->lang('ACP_GROUPSUB_GW_ERROR_CREDENTIALS', $this->get_name() . ' - ' . $mode_name);
			}
		}
		return $errors;
	}
}
