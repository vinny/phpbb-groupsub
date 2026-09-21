<?php
/**
 *
 * Group Subscription. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026, Steve Guidetti, https://github.com/stevotvr
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace stevotvr\groupsub\controller;

use phpbb\auth\auth;
use phpbb\config\config;
use phpbb\controller\helper;
use phpbb\language\language;
use phpbb\request\request_interface;
use phpbb\user;
use stevotvr\groupsub\operator\currency_interface;
use stevotvr\groupsub\operator\package_interface;
use stevotvr\groupsub\operator\subscription_interface;
use stevotvr\groupsub\operator\transaction_interface;
use stevotvr\groupsub\operator\unit_helper_interface;
use stevotvr\groupsub\payment\provider_manager_interface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Controller for handling gateway checkout redirects (Stripe, BTCPay Server, etc.).
 */
class checkout_controller
{
	/**
	 * @var auth
	 */
	protected auth $auth;

	/**
	 * @var config
	 */
	protected config $config;

	/**
	 * @var helper
	 */
	protected helper $helper;

	/**
	 * @var language
	 */
	protected language $language;

	/**
	 * @var request_interface
	 */
	protected request_interface $request;

	/**
	 * @var package_interface
	 */
	protected package_interface $pkg_operator;

	/**
	 * @var subscription_interface
	 */
	protected subscription_interface $sub_operator;

	/**
	 * @var transaction_interface
	 */
	protected transaction_interface $trans_operator;

	/**
	 * @var currency_interface
	 */
	protected currency_interface $currency;

	/**
	 * @var unit_helper_interface
	 */
	protected unit_helper_interface $unit_helper;

	/**
	 * @var provider_manager_interface
	 */
	protected provider_manager_interface $provider_manager;

	/**
	 * @var user
	 */
	protected user $user;

	/**
	 * Constructor.
	 *
	 * @param auth                       $auth
	 * @param config                     $config
	 * @param helper                     $helper
	 * @param language                   $language
	 * @param request_interface          $request
	 * @param package_interface          $pkg_operator
	 * @param subscription_interface     $sub_operator
	 * @param transaction_interface      $trans_operator
	 * @param currency_interface         $currency
	 * @param unit_helper_interface      $unit_helper
	 * @param provider_manager_interface $provider_manager
	 * @param user                       $user
	 */
	public function __construct(
		auth $auth,
		config $config,
		helper $helper,
		language $language,
		request_interface $request,
		package_interface $pkg_operator,
		subscription_interface $sub_operator,
		transaction_interface $trans_operator,
		currency_interface $currency,
		unit_helper_interface $unit_helper,
		provider_manager_interface $provider_manager,
		user $user
	)
	{
		$this->auth = $auth;
		$this->config = $config;
		$this->helper = $helper;
		$this->language = $language;
		$this->request = $request;
		$this->pkg_operator = $pkg_operator;
		$this->sub_operator = $sub_operator;
		$this->trans_operator = $trans_operator;
		$this->currency = $currency;
		$this->unit_helper = $unit_helper;
		$this->provider_manager = $provider_manager;
		$this->user = $user;
	}

	/**
	 * Handle checkout initiation for redirect-based gateways.
	 *
	 * @param string $gateway
	 * @param int    $term_id
	 *
	 * @return Response
	 */
	public function handle(string $gateway, int $term_id): Response
	{
		$this->language->add_lang('common', 'stevotvr/groupsub');

		if ($this->user->data['user_id'] == ANONYMOUS)
		{
			redirect(append_sid($this->user->page['root_script_path'] . 'ucp.' . $this->php_ext, 'mode=login'));
		}

		$term_data = $this->pkg_operator->get_package_term($term_id);
		if (!$term_data)
		{
			trigger_error($this->language->lang('GROUPSUB_ERROR_INVALID_TERM'));
		}

		$term = $term_data['term'];
		$provider = $this->provider_manager->get_provider($gateway);

		if (!$provider || !$provider->is_enabled())
		{
			trigger_error($this->language->lang('GROUPSUB_ERROR_INVALID_ACTION'));
		}

		if ((!$this->config['stevotvr_groupsub_active'] || $provider->is_sandbox()) && !$this->auth->acl_get('a_'))
		{
			trigger_error($this->language->lang('GROUPSUB_ERROR_INVALID_ACTION'));
		}

		if (!$provider->supports_currency($term->get_currency()))
		{
			trigger_error($this->language->lang('GROUPSUB_ERROR_INVALID_ACTION'));
		}

		if ($term->get_recurring() && !$provider->supports_recurring())
		{
			trigger_error($this->language->lang('GROUPSUB_ERROR_INVALID_ACTION'));
		}

		$success_url = $this->helper->route('stevotvr_groupsub_checkout_return', array('gateway' => $gateway), false, false, UrlGeneratorInterface::ABSOLUTE_URL);
		if ($gateway === 'stripe')
		{
			$success_url .= (strpos($success_url, '?') !== false ? '&' : '?') . 'session_id={CHECKOUT_SESSION_ID}';
		}
		else if ($gateway === 'btcpay')
		{
			$success_url .= (strpos($success_url, '?') !== false ? '&' : '?') . 'invoice_id={InvoiceId}';
		}

		$cancel_url = $this->helper->route('stevotvr_groupsub_main', array('name' => $term_data['package']->get_ident()), false, false, UrlGeneratorInterface::ABSOLUTE_URL);

		$context = array(
			'user_email'  => $this->user->data['user_email'],
			'username'    => $this->user->data['username'],
			'success_url' => $success_url,
			'cancel_url'  => $cancel_url,
		);

		$result = $provider->create_payment($term, (int) $this->user->data['user_id'], $context);

		if (!$result->is_success())
		{
			trigger_error($result->get_error_message() . '<br><br>' . sprintf($this->language->lang('RETURN_PAGE'), '<a href="' . $cancel_url . '">', '</a>'));
		}

		$redirect_url = $result->get_redirect_url();
		if ($redirect_url !== '')
		{
			return new RedirectResponse($redirect_url);
		}

		return $this->helper->render('@stevotvr_groupsub/package_list.html', $this->language->lang('GROUPSUB_PACKAGE_LIST'));
	}

	/**
	 * Handle user return from hosted checkout gateways (Stripe, BTCPay, etc.).
	 *
	 * @param string $gateway
	 *
	 * @return Response
	 */
	public function return_handle(string $gateway): Response
	{
		$this->language->add_lang('common', 'stevotvr/groupsub');

		if ($this->user->data['user_id'] == ANONYMOUS)
		{
			redirect(append_sid($this->user->page['root_script_path'] . 'ucp.' . $this->php_ext, 'mode=login'));
		}

		$provider = $this->provider_manager->get_provider($gateway);
		if (!$provider)
		{
			trigger_error($this->language->lang('GROUPSUB_ERROR_INVALID_ACTION'));
		}

		if ($gateway === 'stripe')
		{
			$session_id = (string) $this->request->variable('session_id', '');
			if ($session_id !== '' && method_exists($provider, 'verify_session'))
			{
				$check = $provider->verify_session($session_id);
				if (!empty($check['valid']))
				{
					$term_id = (int) $check['term_id'];
					$user_id = $check['user_id'] > 0 ? (int) $check['user_id'] : (int) $this->user->data['user_id'];
					$term_data = $this->pkg_operator->get_package_term($term_id);

					if ($term_data)
					{
						$term = $term_data['term'];
						$package = $term_data['package'];

						$sub_id = $this->sub_operator->create_subscription($term, $user_id);
						if (!empty($check['subscription_id']))
						{
							$sub = $this->sub_operator->get_subscription_by_id($sub_id);
							if ($sub)
							{
								$sub->set_paypal_id($check['subscription_id'])
									->set_auto_renew(true)
									->save();
							}
						}

						$this->trans_operator->record_transaction(
							$session_id,
							$provider->is_sandbox(),
							$term->get_price(),
							$term->get_currency(),
							$user_id,
							$sub_id,
							$this->currency->format_value($term->get_currency(), $term->get_price(), false, false),
							$check['customer_id'],
							'stripe',
							'completed',
							$check['payment_intent'] !== '' ? $check['payment_intent'] : $session_id
						);

						$pkg_name = $package->get_name();
						$length_str = $term->get_length() ? $this->unit_helper->get_formatted_timespan($term->get_length()) : $this->language->lang('GROUPSUB_LENGTH_UNLIMITED');
						$return_msg = sprintf($this->language->lang('GROUPSUB_RETURN_MESSAGE'), $pkg_name, $length_str);

						trigger_error($return_msg . '<br><br>' . sprintf($this->language->lang('RETURN_PAGE'), '<a href="' . $this->helper->route('stevotvr_groupsub_main') . '">', '</a>'));
					}
				}
			}
		}
		else if ($gateway === 'pix')
		{
			$payment_id = (string) $this->request->variable('payment_id', '');
			if ($payment_id !== '' && method_exists($provider, 'get_payment_status'))
			{
				$check = $provider->get_payment_status($payment_id);
				if (!empty($check['success']) && $check['status'] === 'approved')
				{
					$external_ref = isset($check['external_reference']) ? (string) $check['external_reference'] : '';
					$target_user_id = (int) $this->user->data['user_id'];
					$target_term_id = 0;

					if (preg_match('/groupsub_term_(\d+)_user_(\d+)/', $external_ref, $matches))
					{
						$target_term_id = (int) $matches[1];
						$target_user_id = (int) $matches[2];
					}

					if ($target_term_id > 0)
					{
						$term_data = $this->pkg_operator->get_package_term($target_term_id);
						if ($term_data)
						{
							$term = $term_data['term'];
							$package = $term_data['package'];

							$sub_id = $this->sub_operator->create_subscription($term, $target_user_id);
							$this->trans_operator->record_transaction(
								$payment_id,
								$provider->is_sandbox(),
								$term->get_price(),
								$term->get_currency(),
								$target_user_id,
								$sub_id,
								$this->currency->format_value($term->get_currency(), $term->get_price(), false, false),
								$check['payer_id'],
								'pix',
								'completed',
								$payment_id
							);

							$pkg_name = $package->get_name();
							$length_str = $term->get_length() ? $this->unit_helper->get_formatted_timespan($term->get_length()) : $this->language->lang('GROUPSUB_LENGTH_UNLIMITED');
							$return_msg = sprintf($this->language->lang('GROUPSUB_RETURN_MESSAGE'), $pkg_name, $length_str);

							trigger_error($return_msg . '<br><br>' . sprintf($this->language->lang('RETURN_PAGE'), '<a href="' . $this->helper->route('stevotvr_groupsub_main') . '">', '</a>'));
						}
					}
				}
			}
		}
		else if ($gateway === 'btcpay')
		{
			$invoice_id = (string) $this->request->variable('invoice_id', '');
			if ($invoice_id !== '' && method_exists($provider, 'verify_invoice'))
			{
				$check = $provider->verify_invoice($invoice_id);
				if (!empty($check['valid']))
				{
					$term_id = (int) $check['term_id'];
					$user_id = $check['user_id'] > 0 ? (int) $check['user_id'] : (int) $this->user->data['user_id'];
					$term_data = $this->pkg_operator->get_package_term($term_id);

					if ($term_data)
					{
						$term = $term_data['term'];
						$package = $term_data['package'];

						$sub_id = $this->sub_operator->create_subscription($term, $user_id);
						$this->trans_operator->record_transaction(
							$invoice_id,
							$provider->is_sandbox(),
							$term->get_price(),
							$term->get_currency(),
							$user_id,
							$sub_id,
							$this->currency->format_value($term->get_currency(), $term->get_price(), false, false),
							$check['buyer_email'] !== '' ? $check['buyer_email'] : 'btcpay_user',
							'btcpay',
							'completed',
							$invoice_id
						);

						$pkg_name = $package->get_name();
						$length_str = $term->get_length() ? $this->unit_helper->get_formatted_timespan($term->get_length()) : $this->language->lang('GROUPSUB_LENGTH_UNLIMITED');
						$return_msg = sprintf($this->language->lang('GROUPSUB_RETURN_MESSAGE'), $pkg_name, $length_str);

						trigger_error($return_msg . '<br><br>' . sprintf($this->language->lang('RETURN_PAGE'), '<a href="' . $this->helper->route('stevotvr_groupsub_main') . '">', '</a>'));
					}
				}
			}
		}

		return new RedirectResponse($this->helper->route('stevotvr_groupsub_main'));
	}
}
