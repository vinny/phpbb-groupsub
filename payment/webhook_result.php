<?php
/**
 *
 * Group Subscription. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026, Steve Guidetti, https://github.com/stevotvr
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace stevotvr\groupsub\payment;

/**
 * Result object returned by payment provider when handling incoming webhooks.
 */
class webhook_result
{
	/**
	 * @var bool
	 */
	protected bool $valid;

	/**
	 * @var string
	 */
	protected string $event_type;

	/**
	 * @var string 'completed', 'cancelled', 'suspended', 'expired', 'ignored', 'error'
	 */
	protected string $status;

	/**
	 * @var string
	 */
	protected string $transaction_id;

	/**
	 * @var string
	 */
	protected string $external_id;

	/**
	 * @var string
	 */
	protected string $subscription_id;

	/**
	 * @var string
	 */
	protected string $amount;

	/**
	 * @var string
	 */
	protected string $currency;

	/**
	 * @var string
	 */
	protected string $payer_id;

	/**
	 * @var array
	 */
	protected array $data;

	/**
	 * @var string
	 */
	protected string $message;

	/**
	 * Constructor.
	 *
	 * @param bool   $valid
	 * @param string $event_type
	 * @param string $status
	 * @param string $transaction_id
	 * @param string $external_id
	 * @param string $subscription_id
	 * @param string $amount
	 * @param string $currency
	 * @param string $payer_id
	 * @param array  $data
	 * @param string $message
	 */
	public function __construct(
		bool $valid,
		string $event_type = '',
		string $status = 'ignored',
		string $transaction_id = '',
		string $external_id = '',
		string $subscription_id = '',
		string $amount = '0.00',
		string $currency = '',
		string $payer_id = '',
		array $data = array(),
		string $message = ''
	)
	{
		$this->valid = $valid;
		$this->event_type = $event_type;
		$this->status = $status;
		$this->transaction_id = $transaction_id;
		$this->external_id = $external_id;
		$this->subscription_id = $subscription_id;
		$this->amount = $amount;
		$this->currency = $currency;
		$this->payer_id = $payer_id;
		$this->data = $data;
		$this->message = $message;
	}

	public function is_valid(): bool
	{
		return $this->valid;
	}

	public function get_event_type(): string
	{
		return $this->event_type;
	}

	public function get_status(): string
	{
		return $this->status;
	}

	public function get_transaction_id(): string
	{
		return $this->transaction_id;
	}

	public function get_external_id(): string
	{
		return $this->external_id;
	}

	public function get_subscription_id(): string
	{
		return $this->subscription_id;
	}

	public function get_amount(): string
	{
		return $this->amount;
	}

	public function get_currency(): string
	{
		return $this->currency;
	}

	public function get_payer_id(): string
	{
		return $this->payer_id;
	}

	public function get_data(): array
	{
		return $this->data;
	}

	public function get_message(): string
	{
		return $this->message;
	}
}
