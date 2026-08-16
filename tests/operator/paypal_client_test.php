<?php
/**
 *
 * Group Subscription. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026, Steve Guidetti, https://github.com/stevotvr
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace stevotvr\groupsub\tests\operator;

class paypal_client_test extends \phpbb_test_case
{
	/**
	 * @var \stevotvr\groupsub\operator\paypal_client
	 */
	protected $client;

	/**
	 * @var \phpbb\config\config|\PHPUnit\Framework\MockObject\MockObject
	 */
	protected $config;

	public function setUp(): void
	{
		parent::setUp();

		$this->config = $this->getMockBuilder('\phpbb\config\config')
			->disableOriginalConstructor()
			->getMock();

		$this->client = new \stevotvr\groupsub\operator\paypal_client($this->config);
	}

	public function test_base_url_live_and_sandbox()
	{
		$this->client->set_credentials('live_client_id', 'live_secret', false);
		$this->assertEquals(\stevotvr\groupsub\operator\paypal_client::API_LIVE, $this->client->get_base_url());

		$this->client->set_credentials('sandbox_client_id', 'sandbox_secret', true);
		$this->assertEquals(\stevotvr\groupsub\operator\paypal_client::API_SANDBOX, $this->client->get_base_url());
	}

	public function test_get_access_token_empty_credentials()
	{
		$this->client->set_credentials('', '', false);
		$token = $this->client->get_access_token();
		$this->assertNull($token);
	}
}
