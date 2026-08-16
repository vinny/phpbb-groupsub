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
 * Group Subscription migration for version 2.0.0.
 */
class version_2_0_0 extends migration
{
	/**
	 * @inheritDoc
	 */
	static public function depends_on()
	{
		return array('\stevotvr\groupsub\migrations\version_1_3_0');
	}

	/**
	 * @inheritDoc
	 */
	public function update_schema()
	{
		return array(
			'add_columns' => array(
				$this->table_prefix . 'groupsub_terms' => array(
					'term_recurring' => array('BOOL', 0),
					'paypal_plan_id' => array('VCHAR:50', ''),
				),
				$this->table_prefix . 'groupsub_subs' => array(
					'paypal_sub_id'  => array('VCHAR:50', ''),
					'sub_auto_renew' => array('BOOL', 0),
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
			'drop_columns' => array(
				$this->table_prefix . 'groupsub_terms' => array(
					'term_recurring',
					'paypal_plan_id',
				),
				$this->table_prefix . 'groupsub_subs' => array(
					'paypal_sub_id',
					'sub_auto_renew',
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
			array('config.add', array('stevotvr_groupsub_webhook_id', '')),
			array('config.add', array('stevotvr_groupsub_sb_webhook_id', '')),
		);
	}

	/**
	 * @inheritDoc
	 */
	public function effectively_installed()
	{
		return isset($this->config['stevotvr_groupsub_webhook_id']);
	}
}
