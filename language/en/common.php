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
	'GROUPSUB_ERROR_CAPTURE_NOT_SUPPORTED' => 'Capture is not supported by this payment gateway.',
	'GROUPSUB_ERROR_WEBHOOK_NOT_IMPLEMENTED' => 'Webhook is not implemented for this payment gateway.',
	'GROUPSUB_ERROR_PIX_CURRENCY'	=> 'Pix payments are only supported in Brazilian Real (BRL).',
	'GROUPSUB_ERROR_PIX_CREDENTIALS' => 'Mercado Pago API credentials are not configured.',
	'GROUPSUB_ERROR_PIX_KEY_REQUIRED' => 'Mercado Pago error: Make sure your Mercado Pago account has an active Pix Key (Chave Pix) registered in your account settings.',
	'GROUPSUB_ERROR_PIX_CREATION'	=> 'Failed to generate Pix payment. Please try again.',
	'GROUPSUB_ERROR_STRIPE_CREDENTIALS' => 'Stripe API keys are not configured.',
	'GROUPSUB_ERROR_STRIPE_CREATION' => 'Failed to create Stripe Checkout session.',
	'GROUPSUB_ERROR_BTCPAY_CREDENTIALS' => 'BTCPay Server credentials are not configured.',
	'GROUPSUB_SELECT_GATEWAY'		=> 'Select a payment method:',
	'GROUPSUB_GATEWAY_NAME_PAYPAL'	=> 'PayPal',
	'GROUPSUB_GATEWAY_NAME_PIX'		=> 'Pix (Mercado Pago)',
	'GROUPSUB_GATEWAY_NAME_STRIPE'	=> 'Credit Card (Stripe)',
	'GROUPSUB_GATEWAY_NAME_BTCPAY'	=> 'Bitcoin / Lightning (BTCPay Server)',
	'ACP_GROUPSUB_GATEWAY_NAME_PAYPAL'	=> 'PayPal',
	'ACP_GROUPSUB_GATEWAY_NAME_PIX'		=> 'Pix (Mercado Pago)',
	'ACP_GROUPSUB_GATEWAY_NAME_STRIPE'	=> 'Credit Card (Stripe)',
	'ACP_GROUPSUB_GATEWAY_NAME_BTCPAY'	=> 'Bitcoin / Lightning (BTCPay Server)',
	'GROUPSUB_PAY_WITH_PAYPAL'		=> 'PayPal',
	'GROUPSUB_PAY_WITH_PIX'			=> 'Pix (Mercado Pago)',
	'GROUPSUB_PAY_WITH_STRIPE'		=> 'Credit Card (Stripe)',
	'GROUPSUB_PAY_WITH_BTCPAY'		=> 'Bitcoin / Lightning (BTCPay)',
	'GROUPSUB_PIX_GENERATE_BTN'		=> 'Generate Pix QR Code',
	'GROUPSUB_PIX_SCAN_QR'			=> 'Scan the QR code below using your banking app to complete the payment:',
	'GROUPSUB_PIX_COPY_CODE'		=> 'Pix Copy and Paste code',
	'GROUPSUB_PIX_COPY_BTN'			=> 'Copy Pix Code',
	'GROUPSUB_PIX_COPIED'			=> 'Copied!',
	'GROUPSUB_PIX_WAITING'			=> 'Waiting for payment confirmation…',
	'GROUPSUB_PIX_APPROVED'			=> 'Payment approved! Your subscription is now active.',
	'GROUPSUB_PIX_EXPIRES_IN'		=> 'This Pix code expires in %s.',
	'GROUPSUB_STRIPE_BTN'			=> 'Proceed to Stripe Checkout',
	'GROUPSUB_BTCPAY_BTN'			=> 'Proceed to BTCPay Invoice',
	'GROUPSUB_NO_AVAILABLE_GATEWAYS' => 'There are currently no payment methods available for this subscription.',
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
