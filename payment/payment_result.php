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
 * Result object returned by payment provider when initiating or capturing a payment.
 */
class payment_result
{
	/**
	 * @var bool
	 */
	protected bool $success;

	/**
	 * @var string 'completed', 'pending', 'redirect', 'action_required', 'error'
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
	protected string $redirect_url;

	/**
	 * @var array
	 */
	protected array $data;

	/**
	 * @var string
	 */
	protected string $error_message;

	/**
	 * Constructor.
	 *
	 * @param bool   $success
	 * @param string $status
	 * @param string $transaction_id
	 * @param string $external_id
	 * @param string $redirect_url
	 * @param array  $data
	 * @param string $error_message
	 */
	public function __construct(
		bool $success,
		string $status = 'completed',
		string $transaction_id = '',
		string $external_id = '',
		string $redirect_url = '',
		array $data = array(),
		string $error_message = ''
	)
	{
		$this->success = $success;
		$this->status = $status;
		$this->transaction_id = $transaction_id;
		$this->external_id = $external_id;
		$this->redirect_url = $redirect_url;
		$this->data = $data;
		$this->error_message = $error_message;
	}

	public function is_success(): bool
	{
		return $this->success;
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

	public function get_redirect_url(): string
	{
		return $this->redirect_url;
	}

	public function get_data(): array
	{
		return $this->data;
	}

	public function get_error_message(): string
	{
		return $this->error_message;
	}
}
