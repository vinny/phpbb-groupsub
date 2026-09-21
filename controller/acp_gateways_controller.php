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

use stevotvr\groupsub\payment\provider_manager_interface;

/**
 * Controller for ACP Payment Gateways mode.
 */
class acp_gateways_controller extends acp_base_controller
{
	/**
	 * @var provider_manager_interface
	 */
	protected provider_manager_interface $provider_manager;

	/**
	 * Setup controller dependencies.
	 *
	 * @param provider_manager_interface $provider_manager
	 */
	public function setup(provider_manager_interface $provider_manager)
	{
		$this->provider_manager = $provider_manager;
	}

	/**
	 * @inheritDoc
	 */
	public function add_lang()
	{
		parent::add_lang();

		$this->language->add_lang('acp_settings', 'stevotvr/groupsub');
		$this->language->add_lang('acp_gateways', 'stevotvr/groupsub');
	}

	/**
	 * Display the list of gateways.
	 */
	public function display()
	{
		$this->add_lang();

		$providers = $this->provider_manager->get_all_providers();

		foreach ($providers as $provider)
		{
			$currencies = $provider->get_supported_currencies();
			$currency_str = empty($currencies)
				? $this->language->lang('ACP_GROUPSUB_GW_ALL_CURRENCIES')
				: implode(', ', $currencies);

			$this->template->assign_block_vars('gateways', array(
				'ID'          => $provider->get_id(),
				'NAME'        => $provider->get_name(),
				'S_ENABLED'   => $provider->is_enabled(),
				'S_SANDBOX'   => $provider->is_sandbox(),
				'S_RECURRING' => $provider->supports_recurring(),
				'CURRENCIES'  => $currency_str,

				'U_EDIT'      => $this->u_action . '&amp;action=edit&amp;gw=' . $provider->get_id(),
			));
		}

		$this->template->assign_vars(array(
			'U_ACTION' => $this->u_action,
		));
	}

	/**
	 * Edit a specific gateway configuration.
	 *
	 * @param string $gateway_id
	 */
	public function edit(string $gateway_id)
	{
		$this->add_lang();

		$provider = $this->provider_manager->get_provider($gateway_id);
		if (!$provider)
		{
			trigger_error($this->language->lang('ACP_GROUPSUB_GW_UNKNOWN') . adm_back_link($this->u_action), E_USER_WARNING);
		}

		add_form_key('acp_gateways');

		if ($this->request->is_set_post('submit'))
		{
			if (!check_form_key('acp_gateways'))
			{
				trigger_error($this->language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
			}

			$fields = $provider->get_acp_fields();
			$data = array();
			foreach ($fields as $key => $field)
			{
				$default = isset($field['value']) ? $field['value'] : '';
				if (isset($field['type']) && $field['type'] === 'radio')
				{
					$data[$key] = $this->request->variable($key, 0);
				}
				else
				{
					$data[$key] = $this->request->variable($key, (string) $default, true);
				}
			}

			$errors = $provider->validate_acp_fields($data);
			if (!empty($errors))
			{
				trigger_error(implode('<br>', $errors) . adm_back_link($this->u_action . '&amp;action=edit&amp;gw=' . $gateway_id), E_USER_WARNING);
			}

			$provider->save_acp_fields($data);

			trigger_error($this->language->lang('ACP_GROUPSUB_GW_SAVED') . adm_back_link($this->u_action));
		}

		$fieldsets = $provider->get_acp_fieldsets();
		foreach ($fieldsets as $fieldset)
		{
			$this->template->assign_block_vars('fieldsets', array(
				'LEGEND'        => $this->language->lang($fieldset['legend']),
				'EXPLAIN'       => isset($fieldset['explain']) ? $fieldset['explain'] : '',
				'U_WEBHOOK_URL' => isset($fieldset['webhook_url']) ? $fieldset['webhook_url'] : '',
				'S_HAS_EVENTS'  => !empty($fieldset['events']),
			));

			if (isset($fieldset['fields']) && is_array($fieldset['fields']))
			{
				foreach ($fieldset['fields'] as $key => $field)
				{
					$this->template->assign_block_vars('fieldsets.fields', array(
						'KEY'     => $key,
						'TITLE'   => $this->language->lang($field['title']),
						'EXPLAIN' => isset($field['explain']) ? $this->language->lang($field['explain']) : '',
						'TYPE'    => isset($field['type']) ? $field['type'] : 'text',
						'VALUE'   => $field['value'],
					));
				}
			}

			if (isset($fieldset['events']) && is_array($fieldset['events']))
			{
				foreach ($fieldset['events'] as $event)
				{
					$this->template->assign_block_vars('fieldsets.events', array(
						'CODE' => $event['code'],
						'NAME' => $event['name'],
					));
				}
			}
		}

		$this->template->assign_vars(array(
			'S_EDIT_GATEWAY' => true,
			'GW_NAME'        => $provider->get_name(),
			'U_ACTION'       => $this->u_action . '&amp;action=edit&amp;gw=' . $gateway_id,
			'U_BACK'         => $this->u_action,
		));
	}
}
