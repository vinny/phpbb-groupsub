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
use phpbb\controller\helper;
use phpbb\request\request_interface;
use phpbb\user;
use stevotvr\groupsub\operator\currency_interface;
use stevotvr\groupsub\operator\package_interface;
use stevotvr\groupsub\operator\paypal_client_interface;
use stevotvr\groupsub\operator\transaction_interface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Group Subscription controller for the PayPal JavaScript backend.
 */
class ppjs_controller
{
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
	 * @var paypal_client_interface
	 */
	protected paypal_client_interface $paypal_client;

	/**
	 * @var user
	 */
	protected user $user;

	/**
	 * Constructor.
	 *
	 * @param config                 $config
	 * @param currency_interface     $currency
	 * @param helper                 $helper
	 * @param request_interface      $request
	 * @param package_interface      $pkg_operator
	 * @param transaction_interface  $trans_operator
	 * @param paypal_client_interface $paypal_client
	 * @param user                   $user
	 */
	public function __construct(
		config $config,
		currency_interface $currency,
		helper $helper,
		request_interface $request,
		package_interface $pkg_operator,
		transaction_interface $trans_operator,
		paypal_client_interface $paypal_client,
		user $user
	) {
		$this->config = $config;
		$this->currency = $currency;
		$this->helper = $helper;
		$this->request = $request;
		$this->pkg_operator = $pkg_operator;
		$this->trans_operator = $trans_operator;
		$this->paypal_client = $paypal_client;
		$this->user = $user;
	}

	/**
	 * Handle the /groupsub/ppjs/{action} route.
	 *
	 * @param string $action The action requested
	 *
	 * @return Response A Symfony Response object
	 */
	public function handle(string $action): Response
	{
		$sandbox = !empty($this->config['stevotvr_groupsub_pp_sandbox']);
		$client_id = (string) ($this->config[$sandbox ? 'stevotvr_groupsub_sb_client' : 'stevotvr_groupsub_pp_client'] ?? '');
		$client_secret = (string) ($this->config[$sandbox ? 'stevotvr_groupsub_sb_secret' : 'stevotvr_groupsub_pp_secret'] ?? '');

		if ($client_id === '' || $client_secret === '')
		{
			return new JsonResponse(['error' => 'PayPal credentials not configured'], 503);
		}

		$this->paypal_client->set_credentials($client_id, $client_secret, $sandbox);

		return match ($action) {
			'create'  => $this->create(),
			'capture' => $this->capture(),
			default   => new JsonResponse(['error' => 'Invalid action'], 404),
		};
	}

	/**
	 * Create a new PayPal order and return the ID.
	 *
	 * @return JsonResponse
	 */
	protected function create(): JsonResponse
	{
		$term_id = (string) $this->request->variable('term_id', '');
		$term = $this->pkg_operator->get_package_term($term_id);
		if (!$term)
		{
			return new JsonResponse(['error' => 'Invalid package term'], 404);
		}

		$price = $term['term']->get_price();
		$currency = $term['term']->get_currency();

		$payload = [
			'intent' => 'CAPTURE',
			'application_context' => [
				'shipping_preference' => 'NO_SHIPPING',
			],
			'purchase_units' => [
				[
					'reference_id' => (string) $term['term']->get_id(),
					'description'  => (string) $term['package']->get_name(),
					'custom_id'    => (string) $this->user->data['user_id'],
					'invoice_id'   => strtoupper(substr(md5((string) mt_rand()), 0, 17)),
					'amount'       => [
						'currency_code' => $currency,
						'value'         => $this->currency->format_value($currency, $price, false, false),
					],
				],
			],
		];

		$response = $this->paypal_client->create_order($payload);
		if (!$response || empty($response['id']))
		{
			return new JsonResponse(['error' => 'Order creation failed'], 400);
		}

		return new JsonResponse([
			'id'     => $response['id'],
			'status' => $response['status'] ?? 'CREATED',
		], 200);
	}

	/**
	 * Capture a PayPal order and process the transaction.
	 *
	 * @return JsonResponse
	 */
	protected function capture(): JsonResponse
	{
		$order_id = (string) $this->request->variable('order_id', '');
		if ($order_id === '')
		{
			return new JsonResponse(['error' => 'Missing order ID'], 400);
		}

		$response = $this->paypal_client->capture_order($order_id);
		if (!$response)
		{
			return new JsonResponse(['error' => 'Order capture failed'], 400);
		}

		$sandbox = !empty($this->config['stevotvr_groupsub_pp_sandbox']);
		$success = $this->trans_operator->process_transaction($response, $sandbox);

		return new JsonResponse([
			'success' => $success,
			'status'  => $response['status'] ?? '',
		], $success ? 200 : 400);
	}
}
