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
 * Service managing payment providers and routing requests.
 */
class provider_manager implements provider_manager_interface
{
	/**
	 * @var array<string, provider_interface>
	 */
	protected array $providers = array();

	/**
	 * @inheritDoc
	 */
	public function register_provider(provider_interface $provider): void
	{
		$this->providers[$provider->get_id()] = $provider;
	}

	/**
	 * @inheritDoc
	 */
	public function get_provider(string $id): ?provider_interface
	{
		return isset($this->providers[$id]) ? $this->providers[$id] : null;
	}

	/**
	 * @inheritDoc
	 */
	public function get_all_providers(): array
	{
		return $this->providers;
	}

	/**
	 * @inheritDoc
	 */
	public function get_enabled_providers(): array
	{
		return array_filter($this->providers, function (provider_interface $provider) {
			return $provider->is_enabled();
		});
	}

	/**
	 * @inheritDoc
	 */
	public function get_available_providers_for_term(term_interface $term): array
	{
		$currency = $term->get_currency();
		$is_recurring = (bool) $term->get_recurring();

		return array_filter($this->get_enabled_providers(), function (provider_interface $provider) use ($currency, $is_recurring) {
			if (!$provider->supports_currency($currency))
			{
				return false;
			}

			if ($is_recurring && !$provider->supports_recurring())
			{
				return false;
			}

			return true;
		});
	}
}
