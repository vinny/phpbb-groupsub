# Group Subscription (PayPal) [![Tests](https://github.com/vinny/phpbb-groupsub/actions/workflows/tests.yml/badge.svg)](https://github.com/vinny/phpbb-groupsub/actions/workflows/tests.yml)

Group Subscription is a phpBB extension that allows board administrators to offer paid usergroup subscriptions through PayPal. Users are automatically added to groups upon payment and removed when their subscription ends.

## Features

- **PayPal Checkout & Subscriptions REST API:** Accept payments securely using modern PayPal REST APIs and Smart Payment Buttons.
- **Auto-Renewing Recurring Billing:** Offer true recurring subscriptions that automatically renew at the end of each billing period until cancelled.
- **One-Time Fixed Term Options:** Seamlessly mix one-time purchases and recurring terms within the same or different packages.
- **PayPal Webhooks Integration:** Cryptographically verified webhook handling for real-time automatic renewal extensions and cancellations.
- **Self-Service Cancellation:** Subscribers can cancel auto-renewal directly from the forum with clear feedback while retaining group access until their term ends.
- **Automated Group Management:** Automatically add subscribers to designated usergroups on start and remove them upon expiration.
- **Default Group Assignment:** Optionally set a subscription group as the user’s default group while active.
- **Automated Notification System:** Keep users and administrators informed about subscription start, automatic renewals, upcoming expirations, and cancellations.
- **Grace Periods and Early Warnings:** Configurable expiration warning notices and grace periods before revoking group memberships.
- **Sandbox Testing Mode:** Easily test both one-time and recurring payments with sandbox credentials and webhooks before going live.

## Requirements

- PHP 8.2 or newer
- phpBB 3.3.0 or newer
- PHP cURL extension or `allow_url_fopen` with OpenSSL support
- A PayPal Business or Developer account

## Installation

1. Copy the extension files into the following directory of your phpBB installation:
   ```text
   ext/stevotvr/groupsub
   ```
2. In the phpBB **Administration Control Panel (ACP)**, navigate to **Customise** -> **Manage extensions**.
3. Under **Disabled Extensions**, locate **Group Subscription (PayPal)** and click **Enable**.
4. Configure your PayPal API credentials and subscription packages under **Extensions** -> **Group Subscription**.

## Configuration Guide

### 1. PayPal API Credentials

1. Log in to the [PayPal Developer Dashboard](https://developer.paypal.com/developer/applications/).
2. Select **Apps & Credentials** and choose either **Sandbox** (for testing) or **Live** (for production).
3. Click **Create App** (or select an existing App).
4. Copy the **Client ID** and **Secret key** into the corresponding fields under phpBB ACP -> **Extensions** -> **Group Subscription** -> **Settings**.

### 2. PayPal Webhooks Setup (Required for Recurring Subscriptions)

Webhooks allow PayPal to notify your forum when an automatic renewal succeeds or when a subscriber cancels their subscription.

1. In the [PayPal Developer Dashboard](https://developer.paypal.com/developer/applications/), open your App.
2. Scroll down to the **Webhooks** section and click **Add Webhook**.
3. In the **Webhook URL** field, enter your forum’s webhook endpoint (displayed on your ACP Settings page):
   ```text
   https://yourforum.com/groupsub/webhook
   ```
4. Under **Event types**, select the following events:
   - **Payment sale completed** (`PAYMENT.SALE.COMPLETED`)
   - **Billing subscription** (select all):
     - `Billing subscription activated` (`BILLING.SUBSCRIPTION.ACTIVATED`)
     - `Billing subscription cancelled` (`BILLING.SUBSCRIPTION.CANCELLED`)
     - `Billing subscription created` (`BILLING.SUBSCRIPTION.CREATED`)
     - `Billing subscription expired` (`BILLING.SUBSCRIPTION.EXPIRED`)
     - `Billing subscription payment failed` (`BILLING.SUBSCRIPTION.PAYMENT_FAILED`)
     - `Billing subscription re-activated` (`BILLING.SUBSCRIPTION.RE-ACTIVATED`)
     - `Billing subscription suspended` (`BILLING.SUBSCRIPTION.SUSPENDED`)
     - `Billing subscription updated` (`BILLING.SUBSCRIPTION.UPDATED`)
5. Click **Save**.
6. Copy the generated **Webhook ID** and paste it into the **Webhook ID** field in phpBB ACP Settings.

### 3. Setting Up Packages and Terms

1. Navigate to ACP -> **Extensions** -> **Group Subscription** -> **Manage packages**.
2. Click **Create subscription package** (or edit an existing one).
3. In the **Subscription terms** section, enter the price, length (e.g. 1 month, 1 year), and select the **Recurring billing** checkbox if you want PayPal to bill the user automatically on a recurring schedule.
4. Save the package. When a recurring term is created, phpBB automatically registers the corresponding Product and Billing Plan with PayPal’s Subscriptions API.

## Uninstallation

1. Navigate in the ACP to **Customise** -> **Manage extensions**.
2. Locate **Group Subscription (PayPal)** under **Enabled Extensions** and click **Disable**.
3. To permanently delete all associated data, click **Delete data** and then remove the `ext/stevotvr/groupsub` folder.

## License

[GNU General Public License v2 (GPL-2.0)](LICENSE)

## Credits

- Originally created by [Steve Guidetti](https://github.com/stevotvr).
- Continued and maintained by [Vinny](https://github.com/vinny).
