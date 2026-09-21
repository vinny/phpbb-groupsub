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
use stevotvr\groupsub\operator\stripe_client_interface;
use stevotvr\groupsub\payment\payment_result;
use stevotvr\groupsub\payment\provider_base;
use stevotvr\groupsub\payment\webhook_result;
use Symfony\Component\HttpFoundation\Request;

/**
 * Stripe Payment Provider implementation.
 */
class stripe_provider extends provider_base
{
	/**
	 * @var stripe_client_interface
	 */
	protected stripe_client_interface $stripe_client;

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
	 * @param stripe_client_interface $stripe_client
	 * @param currency_interface      $currency
	 * @param user                    $user
	 */
	public function __construct(
		config $config,
		language $language,
		helper $helper,
		stripe_client_interface $stripe_client,
		currency_interface $currency,
		user $user
	)
	{
		parent::__construct($config, $language, $helper);
		$this->stripe_client = $stripe_client;
		$this->currency = $currency;
		$this->user = $user;
	}

	/**
	 * @inheritDoc
	 */
	public function get_id(): string
	{
		return 'stripe';
	}

	/**
	 * @inheritDoc
	 */
	public function get_name(): string
	{
		return $this->language->lang('ACP_GROUPSUB_GATEWAY_NAME_STRIPE');
	}

	/**
	 * @inheritDoc
	 */
	public function is_enabled(): bool
	{
		return !empty($this->config['stevotvr_groupsub_gw_stripe_enabled']);
	}

	/**
	 * @inheritDoc
	 */
	public function is_sandbox(): bool
	{
		return !empty($this->config['stevotvr_groupsub_gw_stripe_sandbox']);
	}

	/**
	 * @inheritDoc
	 */
	public function supports_recurring(): bool
	{
		return true;
	}

	/**
	 * Setup credentials on the Stripe client.
	 */
	protected function init_client(): bool
	{
		$sandbox = $this->is_sandbox();
		$secret_key = (string) ($sandbox
			? (isset($this->config['stevotvr_groupsub_gw_stripe_sb_secret_key']) ? $this->config['stevotvr_groupsub_gw_stripe_sb_secret_key'] : '')
			: (isset($this->config['stevotvr_groupsub_gw_stripe_secret_key']) ? $this->config['stevotvr_groupsub_gw_stripe_secret_key'] : ''));

		if ($secret_key === '')
		{
			return false;
		}

		$this->stripe_client->set_secret_key($secret_key);
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function create_payment(term_interface $term, int $user_id, array $context = array()): payment_result
	{
		if (!$this->init_client())
		{
			return new payment_result(false, 'error', '', '', '', array(), $this->language->lang('GROUPSUB_ERROR_STRIPE_CREDENTIALS'));
		}

		$currency_code = strtolower($term->get_currency());
		$price_unit = (int) $term->get_price();
		$is_recurring = (bool) $term->get_recurring();
		$email = isset($context['user_email']) && $context['user_email'] !== '' ? $context['user_email'] : $this->user->data['user_email'];
		$success_url = isset($context['success_url']) ? $context['success_url'] : '';
		$cancel_url = isset($context['cancel_url']) ? $context['cancel_url'] : '';

		$params = array(
			'mode'                   => $is_recurring ? 'subscription' : 'payment',
			'customer_email'         => $email,
			'client_reference_id'    => (string) $user_id,
			'success_url'            => $success_url,
			'cancel_url'             => $cancel_url,
			'metadata'               => array(
				'user_id' => (string) $user_id,
				'term_id' => (string) $term->get_id(),
				'pkg_id'  => (string) $term->get_package(),
			),
			'line_items'             => array(
				array(
					'quantity'   => 1,
					'price_data' => array(
						'currency'     => $currency_code,
						'unit_amount'  => $price_unit,
						'product_data' => array(
							'name' => $this->language->lang('GROUPSUB_SUBSCRIPTION'),
						),
					),
				),
			),
		);

		if ($is_recurring)
		{
			$params['line_items'][0]['price_data']['recurring'] = array(
				'interval'       => 'day',
				'interval_count' => max(1, (int) $term->get_length()),
			);
		}

		$session = $this->stripe_client->create_checkout_session($params);

		if (empty($session['id']) || empty($session['url']))
		{
			return new payment_result(false, 'error', '', '', '', array(), $this->language->lang('GROUPSUB_ERROR_STRIPE_CREATION'));
		}

		return new payment_result(
			true,
			'redirect',
			(string) $session['id'],
			(string) $session['id'],
			(string) $session['url'],
			$session
		);
	}

	/**
	 * Verify a Stripe Checkout Session on user return.
	 *
	 * @param string $session_id
	 *
	 * @return array
	 */
	public function verify_session(string $session_id): array
	{
		if (!$this->init_client())
		{
			return array('valid' => false, 'error' => 'credentials');
		}

		$session = $this->stripe_client->get_checkout_session($session_id);
		if (empty($session['id']))
		{
			return array('valid' => false, 'error' => 'not_found');
		}

		$is_paid = (isset($session['payment_status']) && $session['payment_status'] === 'paid')
			|| (isset($session['status']) && $session['status'] === 'complete');

		$user_id = isset($session['metadata']['user_id']) ? (int) $session['metadata']['user_id'] : 0;
		if ($user_id === 0 && isset($session['client_reference_id']))
		{
			$user_id = (int) $session['client_reference_id'];
		}

		return array(
			'valid'           => $is_paid,
			'session'         => $session,
			'user_id'         => $user_id,
			'term_id'         => isset($session['metadata']['term_id']) ? (int) $session['metadata']['term_id'] : 0,
			'pkg_id'          => isset($session['metadata']['pkg_id']) ? (int) $session['metadata']['pkg_id'] : 0,
			'subscription_id' => isset($session['subscription']) ? (string) $session['subscription'] : '',
			'customer_id'     => isset($session['customer']) ? (string) $session['customer'] : '',
			'payment_intent'  => isset($session['payment_intent']) ? (string) $session['payment_intent'] : '',
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

		$res = $this->stripe_client->cancel_subscription($sub_id);
		return !empty($res['id']);
	}

	/**
	 * @inheritDoc
	 */
	public function handle_webhook(Request $request): webhook_result
	{
		if (!$this->init_client())
		{
			return new webhook_result(false, '', 'error', '', '', '', '0.00', '', '', array(), $this->language->lang('GROUPSUB_ERROR_STRIPE_CREDENTIALS'));
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

		$sandbox = $this->is_sandbox();
		$webhook_secret = (string) ($sandbox
			? (isset($this->config['stevotvr_groupsub_gw_stripe_sb_webhook_secret']) ? $this->config['stevotvr_groupsub_gw_stripe_sb_webhook_secret'] : '')
			: (isset($this->config['stevotvr_groupsub_gw_stripe_webhook_secret']) ? $this->config['stevotvr_groupsub_gw_stripe_webhook_secret'] : ''));

		if ($webhook_secret !== '')
		{
			$headers = $request->headers->all();
			$is_valid = $this->stripe_client->verify_webhook_signature($headers, $raw_body, $webhook_secret);
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
		$obj = isset($event['data']['object']) && is_array($event['data']['object']) ? $event['data']['object'] : array();

		switch ($event_type)
		{
			case 'checkout.session.completed':
				$trans_id = isset($obj['id']) ? (string) $obj['id'] : '';
				$sub_id = isset($obj['subscription']) ? (string) $obj['subscription'] : '';
				$amount = isset($obj['amount_total']) ? (string) ($obj['amount_total'] / 100) : '0.00';
				$currency = isset($obj['currency']) ? strtoupper((string) $obj['currency']) : 'USD';
				$payer_id = isset($obj['customer']) ? (string) $obj['customer'] : '';

				return new webhook_result(true, $event_type, 'completed', $trans_id, $trans_id, $sub_id, $amount, $currency, $payer_id, $obj);

			case 'invoice.payment_succeeded':
			case 'invoice.paid':
				$trans_id = isset($obj['id']) ? (string) $obj['id'] : '';
				$sub_id = isset($obj['subscription']) ? (string) $obj['subscription'] : '';
				$amount = isset($obj['amount_paid']) ? (string) ($obj['amount_paid'] / 100) : '0.00';
				$currency = isset($obj['currency']) ? strtoupper((string) $obj['currency']) : 'USD';
				$payer_id = isset($obj['customer']) ? (string) $obj['customer'] : '';

				return new webhook_result(true, $event_type, 'completed', $trans_id, $trans_id, $sub_id, $amount, $currency, $payer_id, $obj);

			case 'customer.subscription.deleted':
				$sub_id = isset($obj['id']) ? (string) $obj['id'] : '';
				return new webhook_result(true, $event_type, 'cancelled', '', '', $sub_id, '0.00', '', '', $obj);

			default:
				return new webhook_result(true, $event_type, 'ignored', '', '', '', '0.00', '', '', $obj);
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
				'explain' => $this->language->lang('ACP_GROUPSUB_GW_STRIPE_API_EXPLAIN', $this->language->lang('ACP_GROUPSUB_LIVE')),
				'fields'  => array(
					'publishable_key' => array(
						'type'    => 'text',
						'title'   => 'ACP_GROUPSUB_GW_STRIPE_PUB_KEY',
						'value'   => (string) (isset($this->config['stevotvr_groupsub_gw_stripe_pub_key']) ? $this->config['stevotvr_groupsub_gw_stripe_pub_key'] : ''),
					),
					'secret_key' => array(
						'type'    => 'password',
						'title'   => 'ACP_GROUPSUB_GW_STRIPE_SECRET_KEY',
						'value'   => (string) (isset($this->config['stevotvr_groupsub_gw_stripe_secret_key']) ? $this->config['stevotvr_groupsub_gw_stripe_secret_key'] : ''),
					),
					'webhook_secret' => array(
						'type'    => 'password',
						'title'   => 'ACP_GROUPSUB_GW_STRIPE_WEBHOOK_SECRET',
						'explain' => 'ACP_GROUPSUB_GW_STRIPE_WEBHOOK_SECRET_EXPLAIN',
						'value'   => (string) (isset($this->config['stevotvr_groupsub_gw_stripe_webhook_secret']) ? $this->config['stevotvr_groupsub_gw_stripe_webhook_secret'] : ''),
					),
				),
			),
			array(
				'legend'  => $this->language->lang('ACP_GROUPSUB_SETTINGS_SB_API', $name),
				'explain' => $this->language->lang('ACP_GROUPSUB_GW_STRIPE_API_EXPLAIN', $this->language->lang('ACP_GROUPSUB_SANDBOX')),
				'fields'  => array(
					'sb_publishable_key' => array(
						'type'    => 'text',
						'title'   => 'ACP_GROUPSUB_GW_STRIPE_SB_PUB_KEY',
						'value'   => (string) (isset($this->config['stevotvr_groupsub_gw_stripe_sb_pub_key']) ? $this->config['stevotvr_groupsub_gw_stripe_sb_pub_key'] : ''),
					),
					'sb_secret_key' => array(
						'type'    => 'password',
						'title'   => 'ACP_GROUPSUB_GW_STRIPE_SB_SECRET_KEY',
						'value'   => (string) (isset($this->config['stevotvr_groupsub_gw_stripe_sb_secret_key']) ? $this->config['stevotvr_groupsub_gw_stripe_sb_secret_key'] : ''),
					),
					'sb_webhook_secret' => array(
						'type'    => 'password',
						'title'   => 'ACP_GROUPSUB_GW_STRIPE_SB_WEBHOOK_SECRET',
						'explain' => 'ACP_GROUPSUB_GW_STRIPE_WEBHOOK_SECRET_EXPLAIN',
						'value'   => (string) (isset($this->config['stevotvr_groupsub_gw_stripe_sb_webhook_secret']) ? $this->config['stevotvr_groupsub_gw_stripe_sb_webhook_secret'] : ''),
					),
				),
			),
			array(
				'legend'      => $this->language->lang('ACP_GROUPSUB_WEBHOOK_GUIDE_TITLE', $name),
				'explain'     => $this->language->lang('ACP_GROUPSUB_WEBHOOK_GUIDE_EXPLAIN', $name),
				'webhook_url' => $webhook_url,
				'events'      => array(
					array('code' => 'checkout.session.completed', 'name' => $this->language->lang('ACP_GROUPSUB_EVENT_PAYMENT_SALE_COMPLETED')),
					array('code' => 'customer.subscription.updated', 'name' => $this->language->lang('ACP_GROUPSUB_EVENT_BILLING_SUB_UPDATED')),
					array('code' => 'customer.subscription.deleted', 'name' => $this->language->lang('ACP_GROUPSUB_EVENT_BILLING_SUB_CANCELLED')),
					array('code' => 'invoice.payment_succeeded', 'name' => $this->language->lang('ACP_GROUPSUB_EVENT_PAYMENT_SALE_COMPLETED')),
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
			$this->config->set('stevotvr_groupsub_gw_stripe_enabled', (int) $data['enabled']);
		}
		if (isset($data['sandbox']))
		{
			$this->config->set('stevotvr_groupsub_gw_stripe_sandbox', (int) $data['sandbox']);
		}
		if (isset($data['publishable_key']))
		{
			$this->config->set('stevotvr_groupsub_gw_stripe_pub_key', trim($data['publishable_key']));
		}
		if (isset($data['secret_key']))
		{
			$this->config->set('stevotvr_groupsub_gw_stripe_secret_key', trim($data['secret_key']));
		}
		if (isset($data['webhook_secret']))
		{
			$this->config->set('stevotvr_groupsub_gw_stripe_webhook_secret', trim($data['webhook_secret']));
		}
		if (isset($data['sb_publishable_key']))
		{
			$this->config->set('stevotvr_groupsub_gw_stripe_sb_pub_key', trim($data['sb_publishable_key']));
		}
		if (isset($data['sb_secret_key']))
		{
			$this->config->set('stevotvr_groupsub_gw_stripe_sb_secret_key', trim($data['sb_secret_key']));
		}
		if (isset($data['sb_webhook_secret']))
		{
			$this->config->set('stevotvr_groupsub_gw_stripe_sb_webhook_secret', trim($data['sb_webhook_secret']));
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
			$secret = $is_sandbox ? (isset($data['sb_secret_key']) ? trim($data['sb_secret_key']) : '') : (isset($data['secret_key']) ? trim($data['secret_key']) : '');

			if ($secret === '')
			{
				$mode_name = $is_sandbox ? $this->language->lang('ACP_GROUPSUB_SANDBOX') : $this->language->lang('ACP_GROUPSUB_LIVE');
				$errors[] = $this->language->lang('ACP_GROUPSUB_GW_ERROR_CREDENTIALS', $this->get_name() . ' - ' . $mode_name);
			}
		}
		return $errors;
	}
}
