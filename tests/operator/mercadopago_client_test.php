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

class mercadopago_client_test extends \phpbb_test_case
{
	/**
	 * @var \stevotvr\groupsub\operator\mercadopago_client
	 */
	protected $client;

	public function setUp(): void
	{
		parent::setUp();
		$this->client = new \stevotvr\groupsub\operator\mercadopago_client();
	}

	public function test_credentials()
	{
		$this->client->set_access_token('TEST_ACCESS_TOKEN');
		$this->assertEquals('TEST_ACCESS_TOKEN', $this->client->get_access_token());
	}

	public function test_signature_verification()
	{
		$secret = 'test_secret_key';
		$data_id = '123456789';
		$ts = (string) time();

		$manifest = sprintf('id:%s;request-id:req_test;ts:%s;', $data_id, $ts);
		$hash = hash_hmac('sha256', $manifest, $secret);

		$headers = array(
			'x-signature'  => array('ts=' . $ts . ',v1=' . $hash),
			'x-request-id' => array('req_test'),
		);

		$this->assertTrue($this->client->verify_webhook_signature($headers, $data_id, $secret));

		// Bad signature
		$bad_headers = array(
			'x-signature'  => array('ts=' . $ts . ',v1=bad_hash'),
			'x-request-id' => array('req_test'),
		);
		$this->assertFalse($this->client->verify_webhook_signature($bad_headers, $data_id, $secret));
	}
}
