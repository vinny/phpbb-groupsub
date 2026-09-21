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
 * Interface for Stripe API client.
 */
interface stripe_client_interface
{
	/**
	 * Set API credentials.
	 *
	 * @param string $secret_key
	 */
	public function set_secret_key(string $secret_key): void;

	/**
	 * Create a Checkout Session for one-time payment or recurring subscription.
	 *
	 * @param array $params
	 *
	 * @return array The decoded response from Stripe
	 */
	public function create_checkout_session(array $params): array;

	/**
	 * Retrieve a Checkout Session by ID.
	 *
	 * @param string $session_id
	 *
	 * @return array
	 */
	public function get_checkout_session(string $session_id): array;

	/**
	 * Cancel a Stripe subscription.
	 *
	 * @param string $subscription_id
	 *
	 * @return array
	 */
	public function cancel_subscription(string $subscription_id): array;

	/**
	 * Verify webhook signature (Stripe-Signature header).
	 *
	 * @param array  $headers
	 * @param string $payload
	 * @param string $webhook_secret
	 * @param int    $tolerance
	 *
	 * @return bool
	 */
	public function verify_webhook_signature(array $headers, string $payload, string $webhook_secret, int $tolerance = 300): bool;
}
