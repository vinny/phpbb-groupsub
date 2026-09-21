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
use stevotvr\groupsub\payment\provider_manager_interface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Controller for handling Pix payment asynchronous requests (QR Code generation & polling).
 */
class pix_controller
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
	 * @var currency_interface
	 */
	protected currency_interface $currency;

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
	 * @var transaction_interface
	 */
	protected transaction_interface $trans_operator;

	/**
	 * @var subscription_interface
	 */
	protected subscription_interface $sub_operator;

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
	 * @param auth                      $auth
	 * @param config                    $config
	 * @param currency_interface        $currency
	 * @param helper                    $helper
	 * @param language                  $language
	 * @param request_interface         $request
	 * @param package_interface         $pkg_operator
	 * @param transaction_interface     $trans_operator
	 * @param subscription_interface    $sub_operator
	 * @param provider_manager_interface $provider_manager
	 * @param user                      $user
	 */
	public function __construct(
		auth $auth,
		config $config,
		currency_interface $currency,
		helper $helper,
		language $language,
		request_interface $request,
		package_interface $pkg_operator,
		transaction_interface $trans_operator,
		subscription_interface $sub_operator,
		provider_manager_interface $provider_manager,
		user $user
	)
	{
		$this->auth = $auth;
		$this->config = $config;
		$this->currency = $currency;
		$this->helper = $helper;
		$this->language = $language;
		$this->request = $request;
		$this->pkg_operator = $pkg_operator;
		$this->trans_operator = $trans_operator;
		$this->sub_operator = $sub_operator;
		$this->provider_manager = $provider_manager;
		$this->user = $user;
	}

	/**
	 * Generate Pix QR code and payment data for a given term.
	 *
	 * @param int $term_id
	 *
	 * @return JsonResponse
	 */
	public function create_payment(int $term_id): JsonResponse
	{
		$this->language->add_lang('common', 'stevotvr/groupsub');

		if ($this->user->data['user_id'] == ANONYMOUS)
		{
			return new JsonResponse(array('error' => $this->language->lang('LOGIN_REQUIRED')), 403);
		}

		$pix_provider = $this->provider_manager->get_provider('pix');
		if (!$pix_provider || !$pix_provider->is_enabled())
		{
			return new JsonResponse(array('error' => $this->language->lang('GROUPSUB_ERROR_PIX_CREDENTIALS')), 400);
		}

		if ((!$this->config['stevotvr_groupsub_active'] || $pix_provider->is_sandbox()) && !$this->auth->acl_get('a_'))
		{
			return new JsonResponse(array('error' => $this->language->lang('PAGE_NOT_FOUND')), 404);
		}

		$term_data = $this->pkg_operator->get_package_term($term_id);
		if (!$term_data)
		{
			return new JsonResponse(array('error' => $this->language->lang('GROUPSUB_ERROR_INVALID_TERM')), 400);
		}

		$term = $term_data['term'];

		$webhook_url = $this->helper->route(
			'stevotvr_groupsub_gateway_webhook',
			array('gateway' => 'pix'),
			false,
			false,
			UrlGeneratorInterface::ABSOLUTE_URL
		);

		$context = array(
			'user_email'  => $this->user->data['user_email'],
			'username'    => $this->user->data['username'],
			'webhook_url' => $webhook_url,
		);

		$result = $pix_provider->create_payment($term, (int) $this->user->data['user_id'], $context);

		if (!$result->is_success())
		{
			return new JsonResponse(array('error' => $result->get_error_message()), 400);
		}

		$data = $result->get_data();
		return new JsonResponse(array(
			'success'        => true,
			'payment_id'     => $result->get_transaction_id(),
			'qr_code'        => isset($data['qr_code']) ? $data['qr_code'] : '',
			'qr_code_base64' => isset($data['qr_code_base64']) ? $data['qr_code_base64'] : '',
			'expires_at'     => isset($data['expires_at']) ? $data['expires_at'] : '',
		));
	}

	/**
	 * Check payment status for Pix real-time polling.
	 *
	 * @param string $payment_id
	 *
	 * @return JsonResponse
	 */
	public function check_status(string $payment_id): JsonResponse
	{
		$this->language->add_lang('common', 'stevotvr/groupsub');

		if ($this->user->data['user_id'] == ANONYMOUS || $payment_id === '')
		{
			return new JsonResponse(array('error' => $this->language->lang('LOGIN_REQUIRED')), 403);
		}

		$pix_provider = $this->provider_manager->get_provider('pix');
		if (!$pix_provider)
		{
			return new JsonResponse(array('status' => 'error'), 400);
		}

		$check = method_exists($pix_provider, 'get_payment_status')
			? $pix_provider->get_payment_status($payment_id)
			: array('status' => 'pending');

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
					$trans_id = (string) $payment_id;

					// Check if already processed
					$sub_id = $this->sub_operator->create_subscription($term, $target_user_id);
					$this->trans_operator->record_transaction(
						$trans_id,
						$pix_provider->is_sandbox(),
						$term->get_price(),
						$term->get_currency(),
						$target_user_id,
						$sub_id,
						$this->currency->format_value($term->get_currency(), $term->get_price(), false, false),
						$check['payer_id'],
						'pix',
						'completed',
						$trans_id
					);
				}
			}

			$return_url = $this->helper->route(
				'stevotvr_groupsub_checkout_return',
				array('gateway' => 'pix'),
				false,
				false,
				UrlGeneratorInterface::ABSOLUTE_URL
			) . '?payment_id=' . rawurlencode($payment_id);

			return new JsonResponse(array(
				'status'       => 'approved',
				'success'      => true,
				'redirect_url' => $return_url,
				'message'      => $this->language->lang('GROUPSUB_PIX_APPROVED'),
			));
		}
		else if (!empty($check['status']) && in_array($check['status'], array('cancelled', 'rejected', 'refunded', 'charged_back'), true))
		{
			return new JsonResponse(array('status' => 'cancelled'));
		}

		return new JsonResponse(array('status' => 'pending'));
	}
}
