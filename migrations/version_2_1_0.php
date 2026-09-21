<?php
/**
 *
 * Group Subscription. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026, Steve Guidetti, https://github.com/stevotvr
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace stevotvr\groupsub\migrations;

use phpbb\db\migration\migration;

/**
 * Group Subscription migration for version 2.1.0 (Multi-Gateway Architecture).
 */
class version_2_1_0 extends migration
{
	/**
	 * @inheritDoc
	 */
	static public function depends_on()
	{
		return array('\stevotvr\groupsub\migrations\version_2_0_0');
	}

	/**
	 * @inheritDoc
	 */
	public function update_schema()
	{
		return array(
			'add_columns' => array(
				$this->table_prefix . 'groupsub_trans' => array(
					'gateway'     => array('VCHAR:32', 'paypal'),
					'status'      => array('VCHAR:32', 'completed'),
					'external_id' => array('VCHAR:255', ''),
				),
				$this->table_prefix . 'groupsub_subs' => array(
					'gateway'        => array('VCHAR:32', 'paypal'),
					'gateway_sub_id' => array('VCHAR:255', ''),
				),
			),
			'change_columns' => array(
				$this->table_prefix . 'groupsub_trans' => array(
					'trans_id'    => array('VCHAR:191', ''),
					'trans_payer' => array('VCHAR:255', ''),
				),
				$this->table_prefix . 'groupsub_subs' => array(
					'paypal_sub_id' => array('VCHAR:255', ''),
				),
			),
			'add_keys' => array(
				$this->table_prefix . 'groupsub_trans' => array(
					'gw_t' => array('INDEX', array('gateway', 'trans_id')),
					'gw_e' => array('INDEX', array('gateway', 'external_id')),
				),
			),
		);
	}

	/**
	 * @inheritDoc
	 */
	public function revert_schema()
	{
		return array(
			'drop_keys' => array(
				$this->table_prefix . 'groupsub_trans' => array(
					'gw_t',
					'gw_e',
				),
			),
			'drop_columns' => array(
				$this->table_prefix . 'groupsub_trans' => array(
					'gateway',
					'status',
					'external_id',
				),
				$this->table_prefix . 'groupsub_subs' => array(
					'gateway',
					'gateway_sub_id',
				),
			),
		);
	}

	/**
	 * @inheritDoc
	 */
	public function update_data()
	{
		return array(
			array('config.add', array('stevotvr_groupsub_gw_paypal_enabled', 1)),
			array('config.add', array('stevotvr_groupsub_gw_pix_enabled', 0)),
			array('config.add', array('stevotvr_groupsub_gw_stripe_enabled', 0)),
			array('config.add', array('stevotvr_groupsub_gw_btcpay_enabled', 0)),

			// Add gateways module mode after settings
			array('module.add', array(
				'acp',
				'ACP_GROUPSUB_TITLE',
				array(
					'module_basename'	=> '\stevotvr\groupsub\acp\main_module',
					'module_langname'	=> 'ACP_GROUPSUB_GATEWAYS',
					'module_mode'		=> 'gateways',
					'module_auth'		=> 'ext_stevotvr/groupsub && acl_a_groupsub_settings',
					'after'				=> array('settings', 'ACP_GROUPSUB_SETTINGS'),
				),
			)),
		);
	}

	/**
	 * @inheritDoc
	 */
	public function revert_data()
	{
		return array(
			array('config.remove', array('stevotvr_groupsub_gw_paypal_enabled')),
			array('config.remove', array('stevotvr_groupsub_gw_pix_enabled')),
			array('config.remove', array('stevotvr_groupsub_gw_stripe_enabled')),
			array('config.remove', array('stevotvr_groupsub_gw_btcpay_enabled')),

			array('module.remove', array(
				'acp',
				'ACP_GROUPSUB_TITLE',
				'ACP_GROUPSUB_GATEWAYS',
			)),
		);
	}

	/**
	 * @inheritDoc
	 */
	public function effectively_installed()
	{
		return isset($this->config['stevotvr_groupsub_gw_paypal_enabled']);
	}
}
