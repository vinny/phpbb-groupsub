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
 * Implementation of Mercado Pago API client.
 */
class mercadopago_client implements mercadopago_client_interface
{
	/**
	 * @var string
	 */
	protected string $access_token = '';

	/**
	 * @var string
	 */
	protected string $api_base_url = 'https://api.mercadopago.com';

	/**
	 * @inheritDoc
	 */
	public function set_access_token(string $access_token): void
	{
		$this->access_token = trim($access_token);
	}

	/**
	 * @inheritDoc
	 */
	public function create_pix_payment(
		float $amount,
		string $description,
		string $email,
		string $username,
		string $external_reference = '',
		string $notification_url = '',
		string $idempotency_key = ''
	): array
	{
		if ($idempotency_key === '')
		{
			$idempotency_key = sprintf('%s_%s', uniqid('mp_pix_', true), bin2hex(random_bytes(4)));
		}

		$body = array(
			'transaction_amount' => (float) number_format($amount, 2, '.', ''),
			'description'        => $description,
			'payment_method_id'  => 'pix',
			'payer'              => array(
				'email'      => $email,
				'first_name' => $username,
			),
		);

		if ($external_reference !== '')
		{
			$body['external_reference'] = $external_reference;
		}

		if ($notification_url !== '')
		{
			$body['notification_url'] = $notification_url;
		}

		$headers = array(
			'X-Idempotency-Key: ' . $idempotency_key,
		);

		return $this->request('POST', '/v1/payments', $body, $headers);
	}

	/**
	 * @inheritDoc
	 */
	public function get_payment(string $payment_id): array
	{
		return $this->request('GET', '/v1/payments/' . rawurlencode($payment_id));
	}

	/**
	 * @inheritDoc
	 */
	public function verify_webhook_signature(array $headers, string $data_id, string $secret): bool
	{
		if ($secret === '' || $data_id === '')
		{
			return false;
		}

		// Normalize headers to lowercase
		$norm_headers = array();
		foreach ($headers as $name => $values)
		{
			$val = is_array($values) ? reset($values) : $values;
			$norm_headers[strtolower((string) $name)] = (string) $val;
		}

		if (empty($norm_headers['x-signature']))
		{
			return false;
		}

		$sig_header = $norm_headers['x-signature'];
		$request_id = isset($norm_headers['x-request-id']) ? $norm_headers['x-request-id'] : '';

		$parts = explode(',', $sig_header);
		$ts = '';
		$v1 = '';

		foreach ($parts as $part)
		{
			$kv = explode('=', trim($part), 2);
			if (count($kv) === 2)
			{
				if ($kv[0] === 'ts')
				{
					$ts = $kv[1];
				}
				else if ($kv[0] === 'v1')
				{
					$v1 = $kv[1];
				}
			}
		}

		if ($ts === '' || $v1 === '')
		{
			return false;
		}

		// Format according to Mercado Pago Webhook spec
		$manifest = 'id:' . $data_id . ';';
		if ($request_id !== '')
		{
			$manifest .= 'request-id:' . $request_id . ';';
		}
		$manifest .= 'ts:' . $ts . ';';

		$calculated = hash_hmac('sha256', $manifest, $secret);

		return hash_equals($calculated, $v1);
	}

	/**
	 * Perform an HTTP request to Mercado Pago API using cURL.
	 *
	 * @param string $method
	 * @param string $path
	 * @param array  $data
	 * @param array  $custom_headers
	 *
	 * @return array
	 */
	protected function request(string $method, string $path, array $data = array(), array $custom_headers = array()): array
	{
		$url = $this->api_base_url . $path;

		$headers = array_merge(array(
			'Authorization: Bearer ' . $this->access_token,
			'Content-Type: application/json',
			'Accept: application/json',
			'User-Agent: phpBB-GroupSub/2.1.0',
		), $custom_headers);

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
