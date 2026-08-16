<?php
/**
 *
 * Group Subscription. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026, Steve Guidetti, https://github.com/stevotvr
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace stevotvr\groupsub\operator;

use phpbb\config\config;

/**
 * Group Subscription PayPal REST API v2 client.
 */
class paypal_client implements paypal_client_interface
{
	/**
	 * Production PayPal API URL.
	 */
	const API_LIVE = 'https://api-m.paypal.com';

	/**
	 * Sandbox PayPal API URL.
	 */
	const API_SANDBOX = 'https://api-m.sandbox.paypal.com';

	/**
	 * @var config
	 */
	protected $config;

	/**
	 * @var string
	 */
	protected $client_id = '';

	/**
	 * @var string
	 */
	protected $client_secret = '';

	/**
	 * @var bool
	 */
	protected $sandbox = false;

	/**
	 * @var string|null Cached access token
	 */
	protected $access_token = null;

	/**
	 * @var int Access token expiration timestamp
	 */
	protected $token_expires_at = 0;

	/**
	 * Constructor.
	 *
	 * @param config $config
	 */
	public function __construct(config $config)
	{
		$this->config = $config;
		$this->init_from_config();
	}

	/**
	 * Initialize credentials from phpBB config.
	 *
	 * @return void
	 */
	protected function init_from_config()
	{
		$this->sandbox = !empty($this->config['stevotvr_groupsub_pp_sandbox']);
		$this->client_id = (string) (isset($this->config[$this->sandbox ? 'stevotvr_groupsub_sb_client' : 'stevotvr_groupsub_pp_client']) ? $this->config[$this->sandbox ? 'stevotvr_groupsub_sb_client' : 'stevotvr_groupsub_pp_client'] : '');
		$this->client_secret = (string) (isset($this->config[$this->sandbox ? 'stevotvr_groupsub_sb_secret' : 'stevotvr_groupsub_pp_secret']) ? $this->config[$this->sandbox ? 'stevotvr_groupsub_sb_secret' : 'stevotvr_groupsub_pp_secret'] : '');
	}

	/**
	 * @inheritDoc
	 */
	public function set_credentials($client_id, $client_secret, $sandbox = false)
	{
		$this->client_id = (string) $client_id;
		$this->client_secret = (string) $client_secret;
		$this->sandbox = (bool) $sandbox;
		$this->access_token = null;
		$this->token_expires_at = 0;

		return $this;
	}

	/**
	 * Get the PayPal API base URL for current environment.
	 *
	 * @return string
	 */
	public function get_base_url()
	{
		return $this->sandbox ? self::API_SANDBOX : self::API_LIVE;
	}

	/**
	 * @inheritDoc
	 */
	public function get_access_token()
	{
		if ($this->access_token !== null && time() < ($this->token_expires_at - 60))
		{
			return $this->access_token;
		}

		if ($this->client_id === '' || $this->client_secret === '')
		{
			return null;
		}

		$url = $this->get_base_url() . '/v1/oauth2/token';
		$auth = base64_encode($this->client_id . ':' . $this->client_secret);

		$headers = array(
			'Authorization: Basic ' . $auth,
			'Accept: application/json',
			'Accept-Language: en_US',
			'Content-Type: application/x-www-form-urlencoded',
		);

		$response = $this->http_request('POST', $url, $headers, 'grant_type=client_credentials');

		if ($response === null || empty($response['data']['access_token']))
		{
			return null;
		}

		$this->access_token = (string) $response['data']['access_token'];
		$expires_in = isset($response['data']['expires_in']) ? (int) $response['data']['expires_in'] : 3600;
		$this->token_expires_at = time() + $expires_in;

		return $this->access_token;
	}

	/**
	 * @inheritDoc
	 */
	public function create_order(array $payload)
	{
		$token = $this->get_access_token();
		if ($token === null)
		{
			return null;
		}

		$url = $this->get_base_url() . '/v2/checkout/orders';
		$headers = array(
			'Authorization: Bearer ' . $token,
			'Content-Type: application/json',
			'Prefer: return=representation',
		);

		$response = $this->http_request('POST', $url, $headers, json_encode($payload));

		if ($response === null || $response['status'] < 200 || $response['status'] >= 300)
		{
			return null;
		}

		return $response['data'];
	}

	/**
	 * @inheritDoc
	 */
	public function capture_order($order_id)
	{
		$token = $this->get_access_token();
		if ($token === null)
		{
			return null;
		}

		$url = $this->get_base_url() . '/v2/checkout/orders/' . rawurlencode($order_id) . '/capture';
		$headers = array(
			'Authorization: Bearer ' . $token,
			'Content-Type: application/json',
			'Prefer: return=representation',
		);

		$response = $this->http_request('POST', $url, $headers, '{}');

		if ($response === null || $response['status'] < 200 || $response['status'] >= 300)
		{
			return null;
		}

		return $response['data'];
	}

	/**
	 * @inheritDoc
	 */
	public function get_order($order_id)
	{
		$token = $this->get_access_token();
		if ($token === null)
		{
			return null;
		}

		$url = $this->get_base_url() . '/v2/checkout/orders/' . rawurlencode($order_id);
		$headers = array(
			'Authorization: Bearer ' . $token,
			'Content-Type: application/json',
		);

		$response = $this->http_request('GET', $url, $headers);

		if ($response === null || $response['status'] < 200 || $response['status'] >= 300)
		{
			return null;
		}

		return $response['data'];
	}

	/**
	 * Execute an HTTP request.
	 *
	 * @param string      $method  HTTP method (GET, POST, etc.)
	 * @param string      $url     Endpoint URL
	 * @param array       $headers HTTP headers
	 * @param string|null $body    Request body
	 *
	 * @return array|null Array with 'status' (int) and 'data' (array), or null on failure
	 */
	protected function http_request($method, $url, array $headers = array(), $body = null)
	{
		if (function_exists('curl_init'))
		{
			return $this->http_curl($method, $url, $headers, $body);
		}

		return $this->http_stream($method, $url, $headers, $body);
	}

	/**
	 * Execute an HTTP request via cURL.
	 *
	 * @param string      $method  HTTP method
	 * @param string      $url     Endpoint URL
	 * @param array       $headers HTTP headers
	 * @param string|null $body    Request body
	 *
	 * @return array|null Array with 'status' and 'data', or null on failure
	 */
	protected function http_curl($method, $url, array $headers, $body)
	{
		$ch = curl_init($url);
		if ($ch === false)
		{
			return null;
		}

		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);

		if ($body !== null)
		{
			curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
		}

		$response_raw = curl_exec($ch);
		$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($response_raw === false)
		{
			return null;
		}

		$data = json_decode((string) $response_raw, true);

		return array(
			'status' => $status,
			'data'   => is_array($data) ? $data : array(),
		);
	}

	/**
	 * Execute an HTTP request via PHP stream context (fallback).
	 *
	 * @param string      $method  HTTP method
	 * @param string      $url     Endpoint URL
	 * @param array       $headers HTTP headers
	 * @param string|null $body    Request body
	 *
	 * @return array|null Array with 'status' and 'data', or null on failure
	 */
	protected function http_stream($method, $url, array $headers, $body)
	{
		$header_lines = implode("\r\n", $headers);

		$options = array(
			'http' => array(
				'method'           => $method,
				'header'           => $header_lines,
				'content'          => $body !== null ? $body : '',
				'protocol_version' => 1.1,
				'timeout'          => 30.0,
				'ignore_errors'    => true,
			),
			'ssl' => array(
				'verify_peer'      => true,
				'verify_peer_name' => true,
			),
		);

		$context = stream_context_create($options);
		$fp = @fopen($url, 'r', false, $context);

		if ($fp === false)
		{
			return null;
		}

		$meta = stream_get_meta_data($fp);
		$response_raw = stream_get_contents($fp);
		fclose($fp);

		$status = 0;
		if (isset($meta['wrapper_data'][0]))
		{
			if (preg_match('#HTTP/\S+\s+(\d{3})#i', $meta['wrapper_data'][0], $matches))
			{
				$status = (int) $matches[1];
			}
		}

		$data = json_decode((string) $response_raw, true);

		return array(
			'status' => $status,
			'data'   => is_array($data) ? $data : array(),
		);
	}
}
