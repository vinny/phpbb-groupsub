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

/**
 * Interface for Payment Provider Manager.
 */
interface provider_manager_interface
{
	/**
	 * Register a payment provider.
	 *
	 * @param provider_interface $provider
	 */
	public function register_provider(provider_interface $provider): void;

	/**
	 * Get a provider by its unique identifier.
	 *
	 * @param string $id
	 *
	 * @return provider_interface|null
	 */
	public function get_provider(string $id): ?provider_interface;

	/**
	 * Get all registered providers.
	 *
	 * @return array<string, provider_interface>
	 */
	public function get_all_providers(): array;

	/**
	 * Get all currently enabled providers.
	 *
	 * @return array<string, provider_interface>
	 */
	public function get_enabled_providers(): array;

	/**
	 * Get providers available for a specific term (filtering by currency & recurring support).
	 *
	 * @param term_interface $term
	 *
	 * @return array<string, provider_interface>
	 */
	public function get_available_providers_for_term(term_interface $term): array;
}
