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
 * Interface for Mercado Pago API client.
 */
interface mercadopago_client_interface
{
	/**
	 * Set the API Access Token (Bearer token).
	 *
	 * @param string $access_token
	 */
	public function set_access_token(string $access_token): void;

	/**
	 * Create a Pix payment transaction.
	 *
	 * @param float  $amount
	 * @param string $description
	 * @param string $email
	 * @param string $username
	 * @param string $external_reference
	 * @param string $notification_url
	 * @param string $idempotency_key
	 *
	 * @return array The decoded JSON response from Mercado Pago
	 */
	public function create_pix_payment(
		float $amount,
		string $description,
		string $email,
		string $username,
		string $external_reference = '',
		string $notification_url = '',
		string $idempotency_key = ''
	): array;

	/**
	 * Fetch payment details by payment ID.
	 *
	 * @param string $payment_id
	 *
	 * @return array The decoded JSON response from Mercado Pago
	 */
	public function get_payment(string $payment_id): array;

	/**
	 * Verify Mercado Pago webhook signature header (x-signature).
	 *
	 * @param array  $headers
	 * @param string $data_id
	 * @param string $secret
	 *
	 * @return bool
	 */
	public function verify_webhook_signature(array $headers, string $data_id, string $secret): bool;
}
