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

use phpbb\config\config;
use phpbb\language\language;
use phpbb\notification\manager as notification_manager;
use stevotvr\groupsub\operator\currency_interface;
use stevotvr\groupsub\operator\package_interface;
use stevotvr\groupsub\operator\paypal_client_interface;
use stevotvr\groupsub\operator\subscription_interface;
use stevotvr\groupsub\operator\transaction_interface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for handling PayPal Webhook notifications.
 */
class webhook_controller
{
	/**
	 * @var config
	 */
	protected $config;

	/**
	 * @var language
	 */
	protected $language;

	/**
	 * @var paypal_client_interface
	 */
	protected $paypal_client;

	/**
	 * @var subscription_interface
	 */
	protected $sub_operator;

	/**
	 * @var package_interface
	 */
	protected $pkg_operator;

	/**
	 * @var transaction_interface
	 */
	protected $trans_operator;

	/**
	 * @var currency_interface
	 */
	protected $currency;

	/**
	 * @var notification_manager
	 */
	protected $notification_manager;

	/**
	 * Constructor.
	 *
	 * @param config                  $config
	 * @param language                $language
	 * @param paypal_client_interface $paypal_client
	 * @param subscription_interface  $sub_operator
	 * @param package_interface       $pkg_operator
	 * @param transaction_interface   $trans_operator
	 * @param currency_interface      $currency
	 * @param notification_manager    $notification_manager
	 */
	public function __construct(
		config $config,
		language $language,
		paypal_client_interface $paypal_client,
		subscription_interface $sub_operator,
		package_interface $pkg_operator,
		transaction_interface $trans_operator,
		currency_interface $currency,
		notification_manager $notification_manager
	)
	{
		$this->config = $config;
		$this->language = $language;
		$this->paypal_client = $paypal_client;
		$this->sub_operator = $sub_operator;
		$this->pkg_operator = $pkg_operator;
		$this->trans_operator = $trans_operator;
		$this->currency = $currency;
		$this->notification_manager = $notification_manager;
	}

	/**
	 * Handle incoming PayPal webhook notifications.
	 *
	 * @param Request $request
	 *
	 * @return Response
	 */
	public function handle(Request $request)
	{
		$raw_body = (string) $request->getContent();
		if ($raw_body === '')
		{
			$raw_body = (string) @file_get_contents('php://input');
		}

		if ($raw_body === '')
		{
			return new JsonResponse(array('error' => $this->language->lang('GROUPSUB_ERROR_EMPTY_BODY')), 400);
		}

		$sandbox = !empty($this->config['stevotvr_groupsub_pp_sandbox']);
		$client_id = (string) (isset($this->config[$sandbox ? 'stevotvr_groupsub_sb_client' : 'stevotvr_groupsub_pp_client']) ? $this->config[$sandbox ? 'stevotvr_groupsub_sb_client' : 'stevotvr_groupsub_pp_client'] : '');
		$client_secret = (string) (isset($this->config[$sandbox ? 'stevotvr_groupsub_sb_secret' : 'stevotvr_groupsub_pp_secret']) ? $this->config[$sandbox ? 'stevotvr_groupsub_sb_secret' : 'stevotvr_groupsub_pp_secret'] : '');
		$webhook_id = (string) (isset($this->config[$sandbox ? 'stevotvr_groupsub_sb_webhook_id' : 'stevotvr_groupsub_webhook_id']) ? $this->config[$sandbox ? 'stevotvr_groupsub_sb_webhook_id' : 'stevotvr_groupsub_webhook_id'] : '');

		if ($client_id === '' || $client_secret === '')
		{
			return new JsonResponse(array('error' => $this->language->lang('GROUPSUB_ERROR_PP_CREDENTIALS')), 503);
		}

		$this->paypal_client->set_credentials($client_id, $client_secret, $sandbox);

		// Verify signature if Webhook ID is configured
		if ($webhook_id !== '')
		{
			$headers = $request->headers->all();
			$is_valid = $this->paypal_client->verify_webhook_signature($headers, $raw_body, $webhook_id);
			if (!$is_valid)
			{
				return new JsonResponse(array('error' => $this->language->lang('GROUPSUB_ERROR_INVALID_WEBHOOK_SIG')), 400);
			}
		}

		$event = json_decode($raw_body, true);
		if (!is_array($event) || empty($event['event_type']))
		{
			return new JsonResponse(array('error' => $this->language->lang('GROUPSUB_ERROR_INVALID_JSON_EVENT')), 400);
		}

		$event_type = (string) $event['event_type'];
		$resource = isset($event['resource']) && is_array($event['resource']) ? $event['resource'] : array();

		switch ($event_type)
		{
			case 'PAYMENT.SALE.COMPLETED':
				$this->handle_payment_sale_completed($resource, $sandbox);
				break;

			case 'BILLING.SUBSCRIPTION.CANCELLED':
			case 'BILLING.SUBSCRIPTION.SUSPENDED':
				$this->handle_subscription_cancelled($resource);
				break;

			case 'BILLING.SUBSCRIPTION.EXPIRED':
				$this->handle_subscription_expired($resource);
				break;
		}

		return new JsonResponse(array('status' => 'success'), 200);
	}

	/**
	 * Handle PAYMENT.SALE.COMPLETED webhook event.
	 *
	 * @param array $resource
	 * @param bool  $sandbox
	 */
	protected function handle_payment_sale_completed(array $resource, $sandbox)
	{
		$subscription_id = isset($resource['billing_agreement_id']) ? (string) $resource['billing_agreement_id'] : '';
		if ($subscription_id === '' && isset($resource['subscription_id']))
		{
			$subscription_id = (string) $resource['subscription_id'];
		}

		if ($subscription_id === '')
		{
			return;
		}

		$subscription = $this->sub_operator->get_subscription_by_paypal_id($subscription_id);
		if (!$subscription)
		{
			return;
		}

		$terms = $this->pkg_operator->get_terms($subscription->get_package());
		$term_days = 30; // Default fallback

		if (isset($terms[$subscription->get_package()]))
		{
			foreach ($terms[$subscription->get_package()] as $term)
			{
				if ($term->get_recurring() && (int) $term->get_length() > 0)
				{
					$term_days = (int) $term->get_length();
					break;
				}
			}
		}

		// Extend subscription expiration
		$length_seconds = $term_days * 86400;
		$current_expire = (int) $subscription->get_expire();
		$new_expire = max(time(), $current_expire) + $length_seconds;

		$subscription->set_expire($new_expire)
			->set_auto_renew(true)
			->save();

		// Record the recurring transaction
		$trans_id = isset($resource['id']) ? (string) $resource['id'] : '';
		$amount_val = isset($resource['amount']['total']) ? (string) $resource['amount']['total'] : (isset($resource['amount']['value']) ? (string) $resource['amount']['value'] : '0.00');
		$currency_code = isset($resource['amount']['currency']) ? (string) $resource['amount']['currency'] : (isset($resource['amount']['currency_code']) ? (string) $resource['amount']['currency_code'] : 'USD');
		$payer_id = isset($resource['payer']['payer_id']) ? (string) $resource['payer']['payer_id'] : '';

		if ($trans_id !== '')
		{
			$amount = $this->currency->parse_value($currency_code, $amount_val);
			$this->trans_operator->record_transaction(
				$trans_id,
				$sandbox,
				$amount,
				$currency_code,
				$subscription->get_user(),
				$subscription->get_id(),
				$amount_val,
				$payer_id
			);
		}

		// Dispatch renewed notification
		$pkg_ident = '';
		$pkg_name = '';
		$packages = $this->pkg_operator->get_packages(false, false);
		if (isset($packages[$subscription->get_package()]))
		{
			$pkg_ident = $packages[$subscription->get_package()]['package']->get_ident();
			$pkg_name = $packages[$subscription->get_package()]['package']->get_name();
		}

		$notification_data = array(
			'sub_id'		=> $subscription->get_id(),
			'user_id'		=> $subscription->get_user(),
			'pkg_id'		=> $subscription->get_package(),
			'pkg_ident'		=> $pkg_ident,
			'pkg_name'		=> $pkg_name,
			'sub_expires'	=> $new_expire,
		);

		$this->notification_manager->add_notifications('stevotvr.groupsub.notification.type.renewed', $notification_data);
	}

	/**
	 * Handle BILLING.SUBSCRIPTION.CANCELLED & BILLING.SUBSCRIPTION.SUSPENDED.
	 *
	 * @param array $resource
	 */
	protected function handle_subscription_cancelled(array $resource)
	{
		$subscription_id = isset($resource['id']) ? (string) $resource['id'] : '';
		if ($subscription_id === '')
		{
			return;
		}

		$subscription = $this->sub_operator->get_subscription_by_paypal_id($subscription_id);
		if ($subscription)
		{
			$subscription->set_auto_renew(false)->save();
		}
	}

	/**
	 * Handle BILLING.SUBSCRIPTION.EXPIRED.
	 *
	 * @param array $resource
	 */
	protected function handle_subscription_expired(array $resource)
	{
		$subscription_id = isset($resource['id']) ? (string) $resource['id'] : '';
		if ($subscription_id === '')
		{
			return;
		}

		$subscription = $this->sub_operator->get_subscription_by_paypal_id($subscription_id);
		if ($subscription)
		{
			$this->sub_operator->delete_subscription($subscription->get_id());
		}
	}
}
