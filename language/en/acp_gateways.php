<?php
/**
 *
 * Group Subscription. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026, Steve Guidetti, https://github.com/stevotvr
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

$lang = array_merge($lang, array(
	'ACP_GROUPSUB_GATEWAYS'					=> 'Payment Gateways',
	'ACP_GROUPSUB_GATEWAYS_TITLE'			=> 'Manage payment gateways',
	'ACP_GROUPSUB_GATEWAYS_EXPLAIN'			=> 'Here you can enable, configure, and manage the payment gateways available to subscribers.',
	'ACP_GROUPSUB_GW_NAME'					=> 'Gateway',
	'ACP_GROUPSUB_GW_STATUS'				=> 'Status',
	'ACP_GROUPSUB_GW_SANDBOX'				=> 'Mode',
	'ACP_GROUPSUB_GW_RECURRING'				=> 'Recurring billing',
	'ACP_GROUPSUB_GW_CURRENCIES'			=> 'Supported currencies',
	'ACP_GROUPSUB_GW_ALL_CURRENCIES'		=> 'All currencies',
	'ACP_GROUPSUB_GW_EDIT'					=> 'Configure gateway',
	'ACP_GROUPSUB_GW_EDIT_TITLE'			=> 'Configure payment gateway :: %s',
	'ACP_GROUPSUB_GW_ENABLE'				=> 'Enable gateway',
	'ACP_GROUPSUB_GW_ENABLE_EXPLAIN'		=> 'Allow members to select this payment gateway at checkout.',
	'ACP_GROUPSUB_GW_SAVED'					=> 'Payment gateway settings have been saved.',
	'ACP_GROUPSUB_GW_UNKNOWN'				=> 'The requested payment gateway does not exist.',
	'ACP_GROUPSUB_GW_ERROR_CREDENTIALS'		=> 'You must configure the required API credentials (%s) before enabling this payment gateway.',

	'ACP_GROUPSUB_GATEWAY_NAME_PAYPAL'		=> 'PayPal',
	'ACP_GROUPSUB_GATEWAY_NAME_PIX'			=> 'Pix (Mercado Pago)',
	'ACP_GROUPSUB_GATEWAY_NAME_STRIPE'		=> 'Stripe',
	'ACP_GROUPSUB_GATEWAY_NAME_BTCPAY'		=> 'BTCPay Server',

	'ACP_GROUPSUB_SETTINGS_GENERAL'			=> 'General settings',
	'ACP_GROUPSUB_SETTINGS_LIVE_API'		=> '%s API settings',
	'ACP_GROUPSUB_SETTINGS_SB_API'			=> '%s sandbox API settings',
	'ACP_GROUPSUB_GW_SANDBOX_MODE'			=> 'Enable sandbox mode',
	'ACP_GROUPSUB_GW_SANDBOX_MODE_EXPLAIN'	=> 'Sandbox mode allows you to test %s payments without using real funds.',

	'ACP_GROUPSUB_WEBHOOK_GUIDE_TITLE'		=> 'Setting up %s Webhooks',
	'ACP_GROUPSUB_WEBHOOK_GUIDE_EXPLAIN'	=> 'Webhooks allow %s to notify your forum when a payment, renewal, or cancellation event occurs. To configure a webhook, open your developer dashboard, paste the URL below, and subscribe to the required event types.',
	'ACP_GROUPSUB_WEBHOOK_URL'				=> 'Webhook URL',
	'ACP_GROUPSUB_WEBHOOK_EVENTS_TITLE'		=> 'Required Webhook event subscriptions',

	// PayPal
	'ACP_GROUPSUB_PP_CLIENT'				=> 'Client ID',
	'ACP_GROUPSUB_PP_SECRET'				=> 'Client secret',
	'ACP_GROUPSUB_WEBHOOK_ID'				=> 'Webhook ID',
	'ACP_GROUPSUB_WEBHOOK_ID_EXPLAIN'		=> 'Required for auto-renewing subscriptions and real-time payment updates.',
	'ACP_GROUPSUB_PP_API_EXPLAIN'			=> 'To get your Client ID and Client secret, go to the PayPal Developer Dashboard. With %s selected at the top, in the REST API apps section, click on an existing application or click Create App to create a new application. Here you will be able to access your API credentials.',

	// Pix / Mercado Pago
	'ACP_GROUPSUB_GW_PIX_PUBLIC_KEY'		=> 'Public Key',
	'ACP_GROUPSUB_GW_PIX_ACCESS_TOKEN'		=> 'Access Token',
	'ACP_GROUPSUB_GW_PIX_SB_PUBLIC_KEY'		=> 'Sandbox Public Key',
	'ACP_GROUPSUB_GW_PIX_SB_ACCESS_TOKEN'	=> 'Sandbox Access Token',
	'ACP_GROUPSUB_GW_PIX_SECRET'			=> 'Webhook Secret',
	'ACP_GROUPSUB_GW_PIX_SECRET_EXPLAIN'	=> 'Used to verify webhook notifications from Mercado Pago.',
	'ACP_GROUPSUB_GW_PIX_API_EXPLAIN'		=> 'To get your Access Token and Public Key, go to the Mercado Pago Developers Dashboard under Your integrations -> Credentials. With %s selected, copy the credentials into the fields above.',

	// Stripe
	'ACP_GROUPSUB_GW_STRIPE_PUB_KEY'				=> 'Publishable Key',
	'ACP_GROUPSUB_GW_STRIPE_SECRET_KEY'				=> 'Secret Key',
	'ACP_GROUPSUB_GW_STRIPE_WEBHOOK_SECRET'			=> 'Webhook Signing Secret',
	'ACP_GROUPSUB_GW_STRIPE_WEBHOOK_SECRET_EXPLAIN'	=> 'Used to verify webhook events received from Stripe.',
	'ACP_GROUPSUB_GW_STRIPE_SB_PUB_KEY'				=> 'Test Publishable Key',
	'ACP_GROUPSUB_GW_STRIPE_SB_SECRET_KEY'			=> 'Test Secret Key',
	'ACP_GROUPSUB_GW_STRIPE_SB_WEBHOOK_SECRET'		=> 'Test Webhook Signing Secret',
	'ACP_GROUPSUB_GW_STRIPE_API_EXPLAIN'			=> 'To get your Secret Key, Publishable Key, and Webhook Signing Secret, go to the Stripe Dashboard under Developers -> API keys & Webhooks with %s mode active.',

	// BTCPay Server
	'ACP_GROUPSUB_GW_BTCPAY_SERVER_URL'				=> 'BTCPay Server URL',
	'ACP_GROUPSUB_GW_BTCPAY_SERVER_URL_EXPLAIN'		=> 'The URL of your BTCPay Server instance (e.g. https://mainnet.demo.btcpayserver.org).',
	'ACP_GROUPSUB_GW_BTCPAY_STORE_ID'				=> 'Store ID',
	'ACP_GROUPSUB_GW_BTCPAY_API_KEY'				=> 'API Key',
	'ACP_GROUPSUB_GW_BTCPAY_WEBHOOK_SECRET'			=> 'Webhook Secret',
	'ACP_GROUPSUB_GW_BTCPAY_WEBHOOK_SECRET_EXPLAIN'	=> 'Used to verify webhook payloads received from BTCPay Server.',
	'ACP_GROUPSUB_GW_BTCPAY_SB_SERVER_URL'			=> 'Test BTCPay Server URL',
	'ACP_GROUPSUB_GW_BTCPAY_SB_STORE_ID'			=> 'Test Store ID',
	'ACP_GROUPSUB_GW_BTCPAY_SB_API_KEY'				=> 'Test API Key',
	'ACP_GROUPSUB_GW_BTCPAY_SB_WEBHOOK_SECRET'		=> 'Test Webhook Secret',
	'ACP_GROUPSUB_GW_BTCPAY_API_EXPLAIN'			=> 'To get your API Key and Store ID, log in to your BTCPay Server instance (%s) and navigate to Account -> API Keys and Store Settings -> Webhooks.',
));
