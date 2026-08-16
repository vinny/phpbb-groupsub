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
	public function set_credentials(string $client_id, string $client_secret, bool $sandbox = false): self;

	/**
	 * Get an OAuth2 access token from PayPal.
	 *
	 * @return string|null The access token, or null on failure
	 */
	public function get_access_token(): ?string;

	/**
	 * Create an order via PayPal Orders v2 API.
	 *
	 * @param array $payload Order creation payload
	 *
	 * @return array|null The response array, or null on failure
	 */
	public function create_order(array $payload): ?array;

	/**
	 * Capture an authorized order via PayPal Orders v2 API.
	 *
	 * @param string $order_id The PayPal Order ID
	 *
	 * @return array|null The response array, or null on failure
	 */
	public function capture_order(string $order_id): ?array;

	/**
	 * Get details of an order via PayPal Orders v2 API.
	 *
	 * @param string $order_id The PayPal Order ID
	 *
	 * @return array|null The order details array, or null on failure
	 */
	public function get_order(string $order_id): ?array;
}
