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

/**
 * Implementation of BTCPay Server Greenfield API client.
 */
class btcpay_client implements btcpay_client_interface
{
	/**
	 * @var string
	 */
	protected string $server_url = '';

	/**
	 * @var string
	 */
	protected string $store_id = '';

	/**
	 * @var string
	 */
	protected string $api_key = '';

	/**
	 * @inheritDoc
	 */
	public function set_credentials(string $server_url, string $store_id, string $api_key): void
	{
		$this->server_url = rtrim(trim($server_url), '/');
		$this->store_id = trim($store_id);
		$this->api_key = trim($api_key);
	}

	/**
	 * @inheritDoc
	 */
	public function create_invoice(array $params): array
	{
		if ($this->server_url === '' || $this->store_id === '')
		{
			return array();
		}

		$path = '/api/v1/stores/' . rawurlencode($this->store_id) . '/invoices';
		return $this->request('POST', $path, $params);
	}

	/**
	 * @inheritDoc
	 */
	public function get_invoice(string $invoice_id): array
	{
		if ($this->server_url === '' || $this->store_id === '')
		{
			return array();
		}

		$path = '/api/v1/stores/' . rawurlencode($this->store_id) . '/invoices/' . rawurlencode($invoice_id);
		return $this->request('GET', $path);
	}

	/**
	 * @inheritDoc
	 */
	public function verify_webhook_signature(array $headers, string $payload, string $webhook_secret): bool
	{
		if ($webhook_secret === '' || $payload === '')
		{
			return false;
		}

		$norm_headers = array();
		foreach ($headers as $name => $values)
		{
			$val = is_array($values) ? reset($values) : $values;
			$norm_headers[strtolower((string) $name)] = (string) $val;
		}

		if (empty($norm_headers['btcpay-sig']))
		{
			return false;
		}

		$sig = $norm_headers['btcpay-sig'];
		if (str_starts_with($sig, 'sha256='))
		{
			$sig = substr($sig, 7);
		}

		$expected_sig = hash_hmac('sha256', $payload, $webhook_secret);
		return hash_equals($expected_sig, $sig);
	}

	/**
	 * Perform an HTTP request to BTCPay Greenfield API using cURL.
	 *
	 * @param string $method
	 * @param string $path
	 * @param array  $data
	 *
	 * @return array
	 */
	protected function request(string $method, string $path, array $data = array()): array
	{
		$url = $this->server_url . $path;

		$headers = array(
			'Authorization: token ' . $this->api_key,
			'Content-Type: application/json',
			'Accept: application/json',
			'User-Agent: phpBB-GroupSub/2.1.0 (BTCPay)',
		);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

		if ($method === 'POST')
		{
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
		}
		else if ($method !== 'GET')
		{
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
			if (!empty($data))
			{
				curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
			}
		}

		$response = curl_exec($ch);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($response === false || $http_code < 200 || $http_code >= 300)
		{
			$decoded = is_string($response) ? json_decode($response, true) : array();
			return is_array($decoded) ? $decoded : array();
		}

		$decoded = json_decode($response, true);
		return is_array($decoded) ? $decoded : array();
	}
}
