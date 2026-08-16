<?php
/**
 *
 * Group Subscription. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2017, Steve Guidetti, https://github.com/stevotvr
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

/**
* DO NOT CHANGE
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = array();
}

// DEVELOPERS PLEASE NOTE
//
// All language files should use UTF-8 as their encoding and the files must not contain a BOM.
//
// Placeholders can now contain order information, e.g. instead of
// 'Page %s of %s' you can (and should) write 'Page %1$s of %2$s', this allows
// translators to re-order the output of data while ensuring it remains correct
//
// You do not need this where single placeholders are used, e.g. 'Message %d' is fine
// equally where a string contains only two placeholders which are used to wrap text
// in a url you again do not need to specify an order e.g., 'Click %sHERE%s' is fine
//
// Some characters you may want to copy&paste:
// ’ » “ ” …
//

$lang = array_merge($lang, array(
	'GROUPSUB_PACKAGE_LIST'			=> 'Subscriptions',
	'GROUPSUB_NO_PACKAGES'			=> 'There are no subscriptions available.',
	'GROUPSUB_NO_DESC'				=> 'No description available.',
	'GROUPSUB_SUBSCRIPTION'			=> 'Subscription',
	'GROUPSUB_PRICE'				=> 'Price',
	'GROUPSUB_LENGTH'				=> 'Length',
	'GROUPSUB_LENGTH_UNLIMITED'		=> 'Unlimited',
	'GROUPSUB_SUBSCRIBE'			=> 'Subscribe',
	'GROUPSUB_RENEW'				=> 'Renew subscription',
	'GROUPSUB_CHOOSE_TERM'			=> 'Subscribe to %s',
	'GROUPSUB_SUBSCRIBED'			=> 'You are subscribed forever',
	'GROUPSUB_SUBSCRIBED_UNTIL'		=> 'You are subscribed until %s',
	'GROUPSUB_CONFIRM'				=> 'Confirm subscription to %s',
	'GROUPSUB_JS_REQUIRED'			=> 'Your browser does not support JavaScript, which is required to continue.',
	'GROUPSUB_PROCESSING'			=> 'Processing payment…',
	'GROUPSUB_PAYMENT_ERROR'		=> 'There was an error processing your payment. Please try again.',
	'GROUPSUB_PAYMENT_CANCELLED'	=> 'Payment was cancelled.',

	'GROUPSUB_ONE_TIME'				=> 'One-time',
	'GROUPSUB_RECURRING'			=> 'Auto-renewing',
	'GROUPSUB_RENEWAL'				=> 'Renewal',
	'GROUPSUB_RENEWAL_AUTO'			=> 'Auto-renewing subscription',
	'GROUPSUB_RENEWAL_AUTO_EXPLAIN'	=> 'This subscription renews automatically at the end of each billing period until cancelled.',
	'GROUPSUB_AUTO_RENEW_ACTIVE'	=> 'Auto-renewal is active',
	'GROUPSUB_AUTO_RENEW_NEXT'		=> 'Auto-renewal active (next billing: %s)',
	'GROUPSUB_CANCEL_AUTO_RENEW'	=> 'Cancel auto-renewal',
	'GROUPSUB_CANCEL_RENEWAL_TITLE'	=> 'Cancel subscription auto-renewal',
	'GROUPSUB_CANCEL_RENEWAL_CONFIRM' => 'Are you sure you want to cancel the auto-renewal for this subscription? Future payments will stop immediately, and your access will remain active until %s.',
	'GROUPSUB_CANCEL_RENEWAL_SUCCESS' => 'Auto-renewal has been cancelled. No further charges will be made.',
	'GROUPSUB_CANCEL_RENEWAL_SUCCESS_EXPIRES' => 'Auto-renewal has been cancelled. Your subscription will remain active until %s.',

	'GROUPSUB_RETURN_MESSAGE'		=> 'You are now subscribed to <strong>%1$s</strong> for %2$s.',

	'GROUPSUB_ERROR_PP_CREDENTIALS'	=> 'PayPal credentials are not configured.',
	'GROUPSUB_ERROR_INVALID_ACTION'	=> 'Invalid action requested.',
	'GROUPSUB_ERROR_INVALID_TERM'	=> 'Invalid subscription term specified.',
	'GROUPSUB_ERROR_ORDER_CREATION'	=> 'Failed to create the PayPal order.',
	'GROUPSUB_ERROR_MISSING_ORDER'	=> 'Missing order ID.',
	'GROUPSUB_ERROR_ORDER_CAPTURE'	=> 'Failed to capture the PayPal order.',
	'GROUPSUB_ERROR_EMPTY_BODY'		=> 'Empty request body.',
	'GROUPSUB_ERROR_INVALID_WEBHOOK_SIG' => 'Invalid webhook signature.',
	'GROUPSUB_ERROR_INVALID_JSON_EVENT'  => 'Invalid JSON event.',
	'GROUPSUB_REASON_USER_CANCELLED' => 'Cancelled by subscriber via forum.',

	// PayPal supported locale codes: https://developer.paypal.com/reference/locale-codes/
	'GROUPSUB_PP_LOCALE'			=> 'en_US',

	'GROUPSUB_DECIMAL_SEPARATOR'	=> '.',
	'GROUPSUB_THOUSANDS_SEPARATOR'	=> ',',

	'GROUPSUB_DAYS'		=> array(
		1	=> 'day',
		2	=> 'days',
	),
	'GROUPSUB_WEEKS'	=> array(
		1	=> 'week',
		2	=> 'weeks',
	),
	'GROUPSUB_MONTHS'	=> array(
		1	=> 'month',
		2	=> 'months',
	),
	'GROUPSUB_YEARS'	=> array(
		1	=> 'year',
		2	=> 'years',
	),
));
