<?php
/**
 *
 * Group Subscription. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026, Steve Guidetti, https://github.com/stevotvr
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace stevotvr\groupsub\operator;

/**
 * Interface for the PayPal REST API v2 client.
 */
interface paypal_client_interface
{
	/**
	 * Set the PayPal credentials and environment mode.
	 *
	 * @param string $client_id     PayPal REST Client ID
	 * @param string $client_secret PayPal REST Client Secret
	 * @param bool   $sandbox       Whether sandbox mode is enabled
	 *
	 * @return self
	 */
	public function set_credentials($client_id, $client_secret, $sandbox = false);

	/**
	 * Get the PayPal API base URL for current environment.
	 *
	 * @return string
	 */
	public function get_base_url();

	/**
	 * Get an OAuth2 access token from PayPal.
	 *
	 * @return string|null The access token, or null on failure
	 */
	public function get_access_token();

	/**
	 * Create an order via PayPal Orders v2 API.
	 *
	 * @param array $payload Order creation payload
	 *
	 * @return array|null The response array, or null on failure
	 */
	public function create_order(array $payload);

	/**
	 * Capture an authorized order via PayPal Orders v2 API.
	 *
	 * @param string $order_id The PayPal Order ID
	 *
	 * @return array|null The response array, or null on failure
	 */
	public function capture_order($order_id);

	/**
	 * Get details of an order via PayPal Orders v2 API.
	 *
	 * @param string $order_id The PayPal Order ID
	 *
	 * @return array|null The order details array, or null on failure
	 */
	public function get_order($order_id);

	/**
	 * Create a product in the PayPal Catalog.
	 *
	 * @param string $name        Product name
	 * @param string $description Product description
	 *
	 * @return array|null The response array, or null on failure
	 */
	public function create_product($name, $description = '');

	/**
	 * Create a recurring billing plan in PayPal.
	 *
	 * @param string $product_id     PayPal Product ID
	 * @param string $name           Plan name
	 * @param string $amount         Formatted price amount (e.g. "10.00")
	 * @param string $currency       ISO-4217 Currency Code
	 * @param string $interval_unit  Interval unit (DAY, WEEK, MONTH, YEAR)
	 * @param int    $interval_count Number of intervals
	 *
	 * @return array|null The response array, or null on failure
	 */
	public function create_plan($product_id, $name, $amount, $currency, $interval_unit, $interval_count = 1);

	/**
	 * Deactivate a billing plan in PayPal.
	 *
	 * @param string $plan_id The PayPal Plan ID
	 *
	 * @return bool True on success
	 */
	public function deactivate_plan($plan_id);

	/**
	 * Get details of a subscription via PayPal Subscriptions API.
	 *
	 * @param string $subscription_id The PayPal Subscription ID
	 *
	 * @return array|null The response array, or null on failure
	 */
	public function get_subscription($subscription_id);

	/**
	 * Cancel a subscription via PayPal Subscriptions API.
	 *
	 * @param string $subscription_id The PayPal Subscription ID
	 * @param string $reason          Optional cancellation reason
	 *
	 * @return bool True on success
	 */
	public function cancel_subscription($subscription_id, $reason = '');

	/**
	 * Verify a webhook notification signature with PayPal.
	 *
	 * @param array  $headers    Incoming request headers
	 * @param string $raw_body   Raw request payload string
	 * @param string $webhook_id PayPal Webhook ID configured in ACP
	 *
	 * @return bool True if authentic and verified, false otherwise
	 */
	public function verify_webhook_signature(array $headers, $raw_body, $webhook_id);
}
