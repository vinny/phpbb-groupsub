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
 * Interface for BTCPay Server Greenfield API client.
 */
interface btcpay_client_interface
{
	/**
	 * Set the server configuration and API credentials.
	 *
	 * @param string $server_url
	 * @param string $store_id
	 * @param string $api_key
	 */
	public function set_credentials(string $server_url, string $store_id, string $api_key): void;

	/**
	 * Create an invoice on BTCPay Server.
	 *
	 * @param array $params
	 *
	 * @return array
	 */
	public function create_invoice(array $params): array;

	/**
	 * Retrieve invoice details by invoice ID.
	 *
	 * @param string $invoice_id
	 *
	 * @return array
	 */
	public function get_invoice(string $invoice_id): array;

	/**
	 * Verify webhook signature (BTCPay-Sig header).
	 *
	 * @param array  $headers
	 * @param string $payload
	 * @param string $webhook_secret
	 *
	 * @return bool
	 */
	public function verify_webhook_signature(array $headers, string $payload, string $webhook_secret): bool;
}
