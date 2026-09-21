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
use stevotvr\groupsub\operator\subscription_interface;
use stevotvr\groupsub\operator\transaction_interface;
use stevotvr\groupsub\payment\provider_manager_interface;
use stevotvr\groupsub\payment\webhook_result;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for handling incoming Webhook notifications for all payment gateways.
 */
class webhook_controller
{
	/**
	 * @var config
	 */
	protected config $config;

	/**
	 * @var language
	 */
	protected language $language;

	/**
	 * @var subscription_interface
	 */
	protected subscription_interface $sub_operator;

	/**
	 * @var package_interface
	 */
	protected package_interface $pkg_operator;

	/**
	 * @var transaction_interface
	 */
	protected transaction_interface $trans_operator;

	/**
	 * @var currency_interface
	 */
	protected currency_interface $currency;

	/**
	 * @var notification_manager
	 */
	protected notification_manager $notification_manager;

	/**
	 * @var provider_manager_interface
	 */
	protected provider_manager_interface $provider_manager;

	/**
	 * Constructor.
	 *
	 * @param config                     $config
	 * @param language                   $language
	 * @param subscription_interface     $sub_operator
	 * @param package_interface          $pkg_operator
	 * @param transaction_interface      $trans_operator
	 * @param currency_interface         $currency
	 * @param notification_manager       $notification_manager
	 * @param provider_manager_interface $provider_manager
	 */
	public function __construct(
		config $config,
		language $language,
		subscription_interface $sub_operator,
		package_interface $pkg_operator,
		transaction_interface $trans_operator,
		currency_interface $currency,
		notification_manager $notification_manager,
		provider_manager_interface $provider_manager
	)
	{
		$this->config = $config;
		$this->language = $language;
		$this->sub_operator = $sub_operator;
		$this->pkg_operator = $pkg_operator;
		$this->trans_operator = $trans_operator;
		$this->currency = $currency;
		$this->notification_manager = $notification_manager;
		$this->provider_manager = $provider_manager;
	}

	/**
	 * Handle incoming webhook notifications.
	 *
	 * @param Request $request
	 * @param string  $gateway
	 *
	 * @return Response
	 */
	public function handle(Request $request, string $gateway = 'paypal'): Response
	{
		$this->language->add_lang('common', 'stevotvr/groupsub');

		$provider = $this->provider_manager->get_provider($gateway);
		if (!$provider)
		{
			return new JsonResponse(array('error' => $this->language->lang('GROUPSUB_ERROR_INVALID_ACTION')), 400);
		}

		$result = $provider->handle_webhook($request);

		if (!$result->is_valid())
		{
			return new JsonResponse(array('error' => $result->get_message()), 400);
		}

		$status = $result->get_status();
		$sandbox = $provider->is_sandbox();

		if ($status === 'completed')
		{
			$sub_id = $result->get_subscription_id();
			if ($sub_id !== '')
			{
				$this->handle_subscription_renewed($sub_id, $result, $gateway, $sandbox);
			}
		}
		else if (in_array($status, array('cancelled', 'suspended'), true))
		{
			$sub_id = $result->get_subscription_id();
			if ($sub_id !== '')
			{
				$this->handle_subscription_cancelled($sub_id);
			}
		}
		else if ($status === 'expired')
		{
			$sub_id = $result->get_subscription_id();
			if ($sub_id !== '')
			{
				$this->handle_subscription_expired($sub_id);
			}
		}

		return new JsonResponse(array('status' => 'success'), 200);
	}

	/**
	 * Handle subscription renewal upon recurring payment.
	 *
	 * @param string         $subscription_id
	 * @param webhook_result $result
	 * @param string         $gateway
	 * @param bool           $sandbox
	 */
	protected function handle_subscription_renewed(string $subscription_id, webhook_result $result, string $gateway, bool $sandbox): void
	{
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
		$trans_id = $result->get_transaction_id();
		$amount_val = $result->get_amount();
		$currency_code = $result->get_currency() !== '' ? $result->get_currency() : 'USD';
		$payer_id = $result->get_payer_id();

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
				$payer_id,
				$gateway,
				'completed',
				$result->get_external_id()
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
			'sub_id'      => $subscription->get_id(),
			'user_id'     => $subscription->get_user(),
			'pkg_id'      => $subscription->get_package(),
			'pkg_ident'   => $pkg_ident,
			'pkg_name'    => $pkg_name,
			'sub_expires' => $new_expire,
		);

		$this->notification_manager->add_notifications('stevotvr.groupsub.notification.type.renewed', $notification_data);
	}

	/**
	 * Handle cancellation/suspension of a subscription.
	 *
	 * @param string $subscription_id
	 */
	protected function handle_subscription_cancelled(string $subscription_id): void
	{
		$subscription = $this->sub_operator->get_subscription_by_paypal_id($subscription_id);
		if ($subscription)
		{
			$subscription->set_auto_renew(false)->save();
		}
	}

	/**
	 * Handle expiration of a subscription.
	 *
	 * @param string $subscription_id
	 */
	protected function handle_subscription_expired(string $subscription_id): void
	{
		$subscription = $this->sub_operator->get_subscription_by_paypal_id($subscription_id);
		if ($subscription)
		{
			$this->sub_operator->delete_subscription($subscription->get_id());
		}
	}
}
