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

class stripe_client_test extends \phpbb_test_case
{
	/**
	 * @var \stevotvr\groupsub\operator\stripe_client
	 */
	protected $client;

	public function setUp(): void
	{
		parent::setUp();
		$this->client = new \stevotvr\groupsub\operator\stripe_client();
	}

	public function test_credentials()
	{
		$this->client->set_credentials('pk_test_123', 'sk_test_456');
		$this->assertEquals('pk_test_123', $this->client->get_publishable_key());
		$this->assertEquals('sk_test_456', $this->client->get_secret_key());
	}

	public function test_signature_verification()
	{
		$secret = 'whsec_test_secret';
		$payload = '{"id":"evt_test_123","type":"checkout.session.completed"}';
		$timestamp = time();

		$signed_payload = $timestamp . '.' . $payload;
		$signature = hash_hmac('sha256', $signed_payload, $secret);

		$header = sprintf('t=%d,v1=%s', $timestamp, $signature);
		$headers = array('stripe-signature' => array($header));

		$this->assertTrue($this->client->verify_webhook_signature($headers, $payload, $secret));

		// Invalid signature
		$bad_headers = array('stripe-signature' => array('t=' . $timestamp . ',v1=invalidsig'));
		$this->assertFalse($this->client->verify_webhook_signature($bad_headers, $payload, $secret));
	}
}
