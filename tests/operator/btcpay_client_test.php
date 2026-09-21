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

class btcpay_client_test extends \phpbb_test_case
{
	/**
	 * @var \stevotvr\groupsub\operator\btcpay_client
	 */
	protected $client;

	public function setUp(): void
	{
		parent::setUp();
		$this->client = new \stevotvr\groupsub\operator\btcpay_client();
	}

	public function test_credentials()
	{
		$this->client->set_credentials('https://btcpay.example.com/', 'store_123', 'key_456');
		$this->assertEquals('https://btcpay.example.com', $this->client->get_server_url());
		$this->assertEquals('store_123', $this->client->get_store_id());
		$this->assertEquals('key_456', $this->client->get_api_key());
	}

	public function test_signature_verification()
	{
		$secret = 'btcpay_wh_secret';
		$payload = '{"type":"InvoiceSettled","invoiceId":"inv_abc"}';

		$signature = 'sha256=' . hash_hmac('sha256', $payload, $secret);
		$headers = array('btcpay-sig' => array($signature));

		$this->assertTrue($this->client->verify_webhook_signature($headers, $payload, $secret));

		// Invalid signature
		$bad_headers = array('btcpay-sig' => array('sha256=invalid_hash'));
		$this->assertFalse($this->client->verify_webhook_signature($bad_headers, $payload, $secret));
	}
}
