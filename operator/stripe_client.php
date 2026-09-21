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
 * Implementation of Stripe API client.
 */
class stripe_client implements stripe_client_interface
{
	/**
	 * @var string
	 */
	protected string $secret_key = '';

	/**
	 * @var string
	 */
	protected string $api_base_url = 'https://api.stripe.com';

	/**
	 * @inheritDoc
	 */
	public function set_secret_key(string $secret_key): void
	{
		$this->secret_key = trim($secret_key);
	}

	/**
	 * @inheritDoc
	 */
	public function create_checkout_session(array $params): array
	{
		return $this->request('POST', '/v1/checkout/sessions', $params);
	}

	/**
	 * @inheritDoc
	 */
	public function get_checkout_session(string $session_id): array
	{
		return $this->request('GET', '/v1/checkout/sessions/' . rawurlencode($session_id));
	}

	/**
	 * @inheritDoc
	 */
	public function cancel_subscription(string $subscription_id): array
	{
		return $this->request('DELETE', '/v1/subscriptions/' . rawurlencode($subscription_id));
	}

	/**
	 * @inheritDoc
	 */
	public function verify_webhook_signature(array $headers, string $payload, string $webhook_secret, int $tolerance = 300): bool
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

		if (empty($norm_headers['stripe-signature']))
		{
			return false;
		}

		$sig_header = $norm_headers['stripe-signature'];
		$items = explode(',', $sig_header);
		$timestamp = -1;
		$signatures = array();

		foreach ($items as $item)
		{
			$kv = explode('=', trim($item), 2);
			if (count($kv) === 2)
			{
				if ($kv[0] === 't')
				{
					$timestamp = (int) $kv[1];
				}
				else if ($kv[0] === 'v1')
				{
					$signatures[] = $kv[1];
				}
			}
		}

		if ($timestamp <= 0 || empty($signatures))
		{
			return false;
		}

		if ($tolerance > 0 && abs(time() - $timestamp) > $tolerance)
		{
			return false;
		}

		$signed_payload = $timestamp . '.' . $payload;
		$expected_sig = hash_hmac('sha256', $signed_payload, $webhook_secret);

		foreach ($signatures as $sig)
		{
			if (hash_equals($expected_sig, $sig))
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * Perform an HTTP request to Stripe API using cURL.
	 *
	 * @param string $method
	 * @param string $path
	 * @param array  $data
	 *
	 * @return array
	 */
	protected function request(string $method, string $path, array $data = array()): array
	{
		$url = $this->api_base_url . $path;

		$headers = array(
			'Authorization: Bearer ' . $this->secret_key,
			'Content-Type: application/x-www-form-urlencoded',
			'User-Agent: phpBB-GroupSub/2.1.0 (Stripe)',
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
			curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
		}
		else if ($method !== 'GET')
		{
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
			if (!empty($data))
			{
				curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
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
