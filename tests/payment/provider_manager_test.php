<?php
/**
 *
 * Group Subscription. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026, Steve Guidetti, https://github.com/stevotvr
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace stevotvr\groupsub\tests\payment;

class provider_manager_test extends \phpbb_test_case
{
	/**
	 * @var \stevotvr\groupsub\payment\provider_manager
	 */
	protected $manager;

	public function setUp(): void
	{
		parent::setUp();
		$this->manager = new \stevotvr\groupsub\payment\provider_manager();
	}

	public function test_register_and_get_provider()
	{
		$provider = $this->getMockBuilder('\stevotvr\groupsub\payment\provider_interface')->getMock();
		$provider->expects($this->any())->method('get_id')->willReturn('stripe');
		$provider->expects($this->any())->method('get_name')->willReturn('Stripe');
		$provider->expects($this->any())->method('is_enabled')->willReturn(true);

		$this->manager->register_provider($provider);

		$this->assertSame($provider, $this->manager->get_provider('stripe'));
		$this->assertNull($this->manager->get_provider('non_existent'));

		$all = $this->manager->get_providers();
		$this->assertCount(1, $all);
		$this->assertArrayHasKey('stripe', $all);
	}

	public function test_get_enabled_providers()
	{
		$provider_enabled = $this->getMockBuilder('\stevotvr\groupsub\payment\provider_interface')->getMock();
		$provider_enabled->expects($this->any())->method('get_id')->willReturn('paypal');
		$provider_enabled->expects($this->any())->method('is_enabled')->willReturn(true);

		$provider_disabled = $this->getMockBuilder('\stevotvr\groupsub\payment\provider_interface')->getMock();
		$provider_disabled->expects($this->any())->method('get_id')->willReturn('pix');
		$provider_disabled->expects($this->any())->method('is_enabled')->willReturn(false);

		$this->manager->register_provider($provider_enabled);
		$this->manager->register_provider($provider_disabled);

		$enabled = $this->manager->get_enabled_providers();
		$this->assertCount(1, $enabled);
		$this->assertArrayHasKey('paypal', $enabled);
		$this->assertArrayNotHasKey('pix', $enabled);
	}

	public function test_get_available_providers_currency_and_recurring()
	{
		$term_recurring = $this->getMockBuilder('\stevotvr\groupsub\entity\term_interface')->getMock();
		$term_recurring->expects($this->any())->method('get_currency')->willReturn('USD');
		$term_recurring->expects($this->any())->method('get_recurring')->willReturn(true);

		$paypal = $this->getMockBuilder('\stevotvr\groupsub\payment\provider_interface')->getMock();
		$paypal->expects($this->any())->method('get_id')->willReturn('paypal');
		$paypal->expects($this->any())->method('is_enabled')->willReturn(true);
		$paypal->expects($this->any())->method('supports_currency')->with('USD')->willReturn(true);
		$paypal->expects($this->any())->method('supports_recurring')->willReturn(true);

		$pix = $this->getMockBuilder('\stevotvr\groupsub\payment\provider_interface')->getMock();
		$pix->expects($this->any())->method('get_id')->willReturn('pix');
		$pix->expects($this->any())->method('is_enabled')->willReturn(true);
		$pix->expects($this->any())->method('supports_currency')->with('USD')->willReturn(false);
		$pix->expects($this->any())->method('supports_recurring')->willReturn(false);

		$this->manager->register_provider($paypal);
		$this->manager->register_provider($pix);

		$available = $this->manager->get_available_providers($term_recurring);
		$this->assertCount(1, $available);
		$this->assertArrayHasKey('paypal', $available);
	}
}
