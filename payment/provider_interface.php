<?php
/**
 *
 * Group Subscription. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026, Steve Guidetti, https://github.com/stevotvr
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace stevotvr\groupsub\payment;

use stevotvr\groupsub\entity\term_interface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Interface that all payment providers must implement.
 */
interface provider_interface
{
	/**
	 * Get the unique provider identifier (e.g. 'paypal', 'pix', 'stripe', 'btcpay').
	 *
	 * @return string
	 */
	public function get_id(): string;

	/**
	 * Get the human-readable display name of the provider.
	 *
	 * @return string
	 */
	public function get_name(): string;

	/**
	 * Check if this provider is enabled by administrator.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool;

	/**
	 * Check if this provider is running in test/sandbox mode.
	 *
	 * @return bool
	 */
	public function is_sandbox(): bool;

	/**
	 * Check if this provider supports automated recurring subscriptions.
	 *
	 * @return bool
	 */
	public function supports_recurring(): bool;

	/**
	 * Check if this provider supports the given ISO currency code.
	 *
	 * @param string $currency 3-letter currency code (e.g. USD, BRL, EUR)
	 *
	 * @return bool
	 */
	public function supports_currency(string $currency): bool;

	/**
	 * Get the list of supported ISO currency codes (empty array means all).
	 *
	 * @return array
	 */
	public function get_supported_currencies(): array;

	/**
	 * Create or initiate a payment transaction.
	 *
	 * @param term_interface $term
	 * @param int            $user_id
	 * @param array          $context
	 *
	 * @return payment_result
	 */
	public function create_payment(term_interface $term, int $user_id, array $context = array()): payment_result;

	/**
	 * Capture or confirm a created payment (e.g. PayPal Order Capture).
	 *
	 * @param string $order_id
	 * @param array  $context
	 *
	 * @return payment_result
	 */
	public function capture_payment(string $order_id, array $context = array()): payment_result;

	/**
	 * Cancel a recurring subscription at the provider.
	 *
	 * @param string $sub_id
	 * @param string $reason
	 *
	 * @return bool
	 */
	public function cancel_subscription(string $sub_id, string $reason = ''): bool;

	/**
	 * Handle incoming webhook notification from the provider.
	 *
	 * @param Request $request
	 *
	 * @return webhook_result
	 */
	public function handle_webhook(Request $request): webhook_result;

	/**
	 * Get configuration fieldsets definition for ACP gateway management.
	 *
	 * @return array
	 */
	public function get_acp_fieldsets(): array;

	/**
	 * Get configuration fields definition for ACP gateway management.
	 *
	 * @return array
	 */
	public function get_acp_fields(): array;

	/**
	 * Save configuration fields submitted in ACP gateway management.
	 *
	 * @param array $data
	 */
	public function save_acp_fields(array $data): void;

	/**
	 * Validate ACP configuration fields submitted in ACP gateway management.
	 *
	 * @param array $data
	 * @return array Array of error message strings
	 */
	public function validate_acp_fields(array $data): array;
}
