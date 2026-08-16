# Group Subscription (PayPal)

Group Subscription is a phpBB extension that allows board administrators to offer paid usergroup subscriptions through PayPal. Users are automatically added to groups upon payment and removed when their subscription ends.

## Features

- **PayPal Checkout integration:** Accept payments securely using modern PayPal REST API v2 and Smart Payment Buttons.
- **Multiple packages and flexible terms:** Create subscription packages with customizable pricing, currencies, and durations (days, weeks, months, years, or unlimited).
- **Automated group management:** Automatically add subscribers to designated usergroups on start and remove them upon expiration.
- **Default group assignment:** Optionally set a subscription group as the user’s default group while active.
- **Automated notification system:** Keep users and administrators informed about upcoming expirations, completed payments, and status changes.
- **Grace periods and early warnings:** Configurable expiration warning notices and grace periods before revoking group memberships.
- **Sandbox testing mode:** Easily test payments with sandbox credentials before going live.

## Requirements

- PHP 8.2 or newer
- phpBB 3.3.0 or newer
- PHP cURL extension or `allow_url_fopen` with OpenSSL support
- A PayPal Business account

## Installation

1. Copy the extension files into the following directory of your phpBB installation:
   ```text
   ext/stevotvr/groupsub
   ```
2. In the phpBB **Administration Control Panel (ACP)**, navigate to **Customise** -> **Manage extensions**.
3. Under **Disabled Extensions**, locate **Group Subscription (PayPal)** and click **Enable**.
4. Configure your PayPal API credentials and subscription packages under **Extensions** -> **Group Subscription**.

## Uninstallation

1. Navigate in the ACP to **Customise** -> **Manage extensions**.
2. Locate **Group Subscription (PayPal)** under **Enabled Extensions** and click **Disable**.
3. To permanently delete all associated data, click **Delete data** and then remove the `ext/stevotvr/groupsub` folder.

## License

[GNU General Public License v2 (GPL-2.0)](LICENSE)

## Credits

- Originally created by [Steve Guidetti](https://github.com/stevotvr).
- Continued and maintained by [Vinny](https://github.com/vinny).
