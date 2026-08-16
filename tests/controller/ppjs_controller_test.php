<?php
/**
 *
 * Group Subscription. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026, Steve Guidetti, https://github.com/stevotvr
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace stevotvr\groupsub\tests\controller;

class ppjs_controller_test extends \phpbb_test_case
{
	/**
	 * @var \stevotvr\groupsub\controller\ppjs_controller
	 */
	protected $controller;

	protected $config;
	protected $currency;
	protected $helper;
	protected $request;
	protected $pkg_operator;
	protected $trans_operator;
	protected $paypal_client;
	protected $user;

	public function setUp(): void
	{
		parent::setUp();

		$this->config = $this->getMockBuilder('\phpbb\config\config')
			->disableOriginalConstructor()
			->getMock();

		$this->currency = $this->getMockBuilder('\stevotvr\groupsub\operator\currency_interface')
			->getMock();

		$this->helper = $this->getMockBuilder('\phpbb\controller\helper')
			->disableOriginalConstructor()
			->getMock();

		$this->request = $this->getMockBuilder('\phpbb\request\request_interface')
			->getMock();

		$this->pkg_operator = $this->getMockBuilder('\stevotvr\groupsub\operator\package_interface')
			->getMock();

		$this->trans_operator = $this->getMockBuilder('\stevotvr\groupsub\operator\transaction_interface')
			->getMock();

		$this->paypal_client = $this->getMockBuilder('\stevotvr\groupsub\operator\paypal_client_interface')
			->getMock();

		$this->user = $this->getMockBuilder('\phpbb\user')
			->disableOriginalConstructor()
			->getMock();

		$this->user->data = array(
			'user_id' => 2,
		);

		$this->controller = new \stevotvr\groupsub\controller\ppjs_controller(
			$this->config,
			$this->currency,
			$this->helper,
			$this->request,
			$this->pkg_operator,
			$this->trans_operator,
			$this->paypal_client,
			$this->user
		);
	}

	public function test_handle_unconfigured_credentials()
	{
		$this->config->expects($this->any())
			->method('offsetGet')
			->willReturnCallback(function ($key) {
				return match ($key) {
					'stevotvr_groupsub_pp_sandbox' => 0,
					'stevotvr_groupsub_pp_client'  => '',
					'stevotvr_groupsub_pp_secret'  => '',
					default                        => '',
				};
			});

		$response = $this->controller->handle('create');
		$this->assertEquals(503, $response->getStatusCode());
	}

	public function test_handle_invalid_action()
	{
		$this->config->expects($this->any())
			->method('offsetGet')
			->willReturnCallback(function ($key) {
				return match ($key) {
					'stevotvr_groupsub_pp_sandbox' => 0,
					'stevotvr_groupsub_pp_client'  => 'valid_client',
					'stevotvr_groupsub_pp_secret'  => 'valid_secret',
					default                        => '',
				};
			});

		$this->paypal_client->expects($this->once())
			->method('set_credentials')
			->with('valid_client', 'valid_secret', false)
			->willReturnSelf();

		$response = $this->controller->handle('unknown_action');
		$this->assertEquals(404, $response->getStatusCode());
	}

	public function test_handle_capture_missing_order_id()
	{
		$this->config->expects($this->any())
			->method('offsetGet')
			->willReturnCallback(function ($key) {
				return match ($key) {
					'stevotvr_groupsub_pp_sandbox' => 0,
					'stevotvr_groupsub_pp_client'  => 'valid_client',
					'stevotvr_groupsub_pp_secret'  => 'valid_secret',
					default                        => '',
				};
			});

		$this->request->expects($this->once())
			->method('variable')
			->with('order_id', '')
			->willReturn('');

		$response = $this->controller->handle('capture');
		$this->assertEquals(400, $response->getStatusCode());
	}
}
