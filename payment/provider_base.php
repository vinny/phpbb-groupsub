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

use phpbb\config\config;
use phpbb\controller\helper;
use phpbb\language\language;
use stevotvr\groupsub\entity\term_interface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Base class providing common functionality for payment providers.
 */
abstract class provider_base implements provider_interface
{
	/**
	 * @var config
	 */
	protected config $config;

	/**
	 * @var language
	 */
	protected language $language;

	/**
	 * @var helper
	 */
	protected helper $helper;

	/**
	 * Constructor.
	 *
	 * @param config   $config
	 * @param language $language
	 * @param helper   $helper
	 */
	public function __construct(config $config, language $language, helper $helper)
	{
		$this->config = $config;
		$this->language = $language;
		$this->helper = $helper;
	}

	/**
	 * Get the absolute webhook route URL for this provider.
	 *
	 * @return string
	 */
	public function get_webhook_url(): string
	{
		$route = $this->helper->route(
			'stevotvr_groupsub_gateway_webhook',
			array('gateway' => $this->get_id()),
			false,
			false
		);

		// Ensure no session ID is ever present in the webhook URL
		$route = preg_replace('/([?&])sid=[a-zA-Z0-9]+(&|$)/', '$1', $route);
		$route = rtrim($route, '?&');

		if (strpos($route, 'http://') === 0 || strpos($route, 'https://') === 0)
		{
			return $route;
		}

		return generate_board_url(true) . $route;
	}

	/**
	 * @inheritDoc
	 */
	public function is_enabled(): bool
	{
		$key = 'stevotvr_groupsub_gw_' . $this->get_id() . '_enabled';
		return !empty($this->config[$key]);
	}

	/**
	 * @inheritDoc
	 */
	public function is_sandbox(): bool
	{
		$key = 'stevotvr_groupsub_gw_' . $this->get_id() . '_sandbox';
		return !empty($this->config[$key]);
	}

	/**
	 * @inheritDoc
	 */
	public function supports_currency(string $currency): bool
	{
		$currencies = $this->get_supported_currencies();
		return empty($currencies) || in_array(strtoupper($currency), $currencies, true);
	}

	/**
	 * @inheritDoc
	 */
	public function get_supported_currencies(): array
	{
		return array(); // By default supports all
	}

	/**
	 * @inheritDoc
	 */
	public function capture_payment(string $order_id, array $context = array()): payment_result
	{
		return new payment_result(false, 'error', '', '', '', array(), $this->language->lang('GROUPSUB_ERROR_CAPTURE_NOT_SUPPORTED'));
	}

	/**
	 * @inheritDoc
	 */
	public function cancel_subscription(string $sub_id, string $reason = ''): bool
	{
		return false;
	}

	/**
	 * @inheritDoc
	 */
	public function handle_webhook(Request $request): webhook_result
	{
		return new webhook_result(false, '', 'ignored', '', '', '', '0.00', '', '', array(), $this->language->lang('GROUPSUB_ERROR_WEBHOOK_NOT_IMPLEMENTED'));
	}

	/**
	 * @inheritDoc
	 */
	public function get_acp_fieldsets(): array
	{
		return array(
			array(
				'legend' => 'ACP_GROUPSUB_SETTINGS_GENERAL',
				'fields' => $this->get_acp_fields(),
			),
		);
	}

	/**
	 * @inheritDoc
	 */
	public function get_acp_fields(): array
	{
		return array();
	}

	/**
	 * @inheritDoc
	 */
	public function save_acp_fields(array $data): void
	{
	}

	/**
	 * @inheritDoc
	 */
	public function validate_acp_fields(array $data): array
	{
		return array();
	}

	/**
	 * Helper to get a gateway specific config value.
	 *
	 * @param string $key
	 * @param mixed  $default
	 *
	 * @return mixed
	 */
	protected function get_config(string $key, mixed $default = '')
	{
		$full_key = 'stevotvr_groupsub_gw_' . $this->get_id() . '_' . $key;
		return isset($this->config[$full_key]) ? $this->config[$full_key] : $default;
	}

	/**
	 * Helper to set a gateway specific config value.
	 *
	 * @param string $key
	 * @param mixed  $value
	 */
	protected function set_config(string $key, mixed $value): void
	{
		$full_key = 'stevotvr_groupsub_gw_' . $this->get_id() . '_' . $key;
		$this->config->set($full_key, $value);
	}
}
