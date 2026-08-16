<?php
/**
 *
 * Group Subscription. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2018, Steve Guidetti, https://github.com/stevotvr
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace stevotvr\groupsub\operator;

/**
 * Group Subscription transaction operator interface.
 */
interface transaction_interface
{
	/**
	 * The status for a completed payment
	 */
	const STATUS_COMPLETED = 'COMPLETED';

	/**
	 * Process a transaction from PayPal Orders v2 API.
	 *
	 * @param array   $order_data The captured order data array from PayPal REST API
	 * @param boolean $sandbox    This transaction occurred in the sandbox environment
	 *
	 * @return boolean The transaction was accepted
	 */
	public function process_transaction(array $order_data, $sandbox);

	/**
	 * Record a transaction directly in the database.
	 *
	 * @param string  $trans_id The transaction ID
	 * @param boolean $sandbox  Sandbox mode is enabled
	 * @param int     $amount   The payment amount in the currency subunit
	 * @param string  $currency The currency code
	 * @param int     $user_id  The user ID
	 * @param int     $sub_id   The subscription ID
	 * @param string  $gross    The gross payment amount
	 * @param string  $payer_id The PayPal payer ID
	 *
	 * @return boolean The record was inserted successfully
	 */
	public function record_transaction($trans_id, $sandbox, $amount, $currency, $user_id, $sub_id, $gross, $payer_id);

	/**
	 * Get transactions.
	 *
	 * @param int     $start      The offset of the first row to return
	 * @param int     $limit      The limit of the number of rows to return
	 * @param string  $sort_field The name of the field by which to sort
	 * @param boolean $sort_desc  Sort in descending order
	 *
	 * @return array The rows from the database
	 */
	public function get_transactions($start, $limit, $sort_field, $sort_desc);

	/**
	 * Count the total number of transactions.
	 *
	 * @return int The total number of transactions
	 */
	public function count_transactions();
}
