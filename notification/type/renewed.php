<?php
/**
 *
 * Group Subscription. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026, Steve Guidetti, https://github.com/stevotvr
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace stevotvr\groupsub\notification\type;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Group Subscription renewed notification.
 */
class renewed extends base_type
{
	/**
	 * @inheritDoc
	 */
	static public $notification_option = array(
		'lang'	=> 'GROUPSUB_NOTIFICATION_TYPE_RENEWED',
		'group'	=> 'GROUPSUB_NOTIFICATION_GROUP',
	);

	/**
	 * @inheritDoc
	 */
	public function get_type()
	{
		return 'stevotvr.groupsub.notification.type.renewed';
	}

	/**
	 * @inheritDoc
	 */
	public function get_title()
	{
		return $this->language->lang('GROUPSUB_NOTIFICATION_RENEWED_TITLE');
	}

	/**
	 * @inheritDoc
	 */
	public function get_reference()
	{
		$date = $this->user->format_date($this->get_data('sub_expires'), '|M d|');

		return $this->language->lang('GROUPSUB_NOTIFICATION_RENEWED_REFERENCE', $this->get_data('pkg_name'), $date);
	}

	/**
	 * @inheritDoc
	 */
	public function get_email_template()
	{
		return '@stevotvr_groupsub/subscription_renewed';
	}

	/**
	 * @inheritDoc
	 */
	public function get_email_template_variables()
	{
		$params = array('name' => $this->get_data('pkg_ident'));
		$u_view_sub = $this->helper->route('stevotvr_groupsub_main', $params, false, false, UrlGeneratorInterface::ABSOLUTE_URL);

		return array(
			'SUB_NAME'		=> $this->get_data('pkg_name'),
			'EXPIRES'		=> $this->user->format_date($this->get_data('sub_expires')),
			'U_VIEW_SUB'	=> $u_view_sub,
		);
	}

	/**
	 * @inheritDoc
	 */
	public function users_to_query()
	{
		return array($this->user_id);
	}

	/**
	 * @inheritDoc
	 */
	public function create_insert_array($data, $pre_create_data = array())
	{
		$this->set_data('sub_expires', (int) $data['sub_expires']);

		parent::create_insert_array($data, $pre_create_data);
	}
}
