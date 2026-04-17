=== Thanks Mail for Stripe ===

Contributors: rapls
Donate link: https://buymeacoffee.com/rapls
Tags: stripe, payment, email, webhook, notifications
Requires at least: 5.0
Tested up to: 6.9
Stable tag: 1.1.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically send thank-you emails when Stripe Payment Links purchases are completed. Supports Japanese and English with customizable templates.

== Description ==

Thanks Mail for Stripe is a lightweight plugin that automatically sends customized thank-you emails to customers after they complete a purchase through Stripe Payment Links. Perfect for digital product sales, software licensing, and service businesses.

👉 **Setup guide & developer's notes:** [Why I built this and how to set up Stripe webhooks](https://raplsworks.com/thanks-mail-for-stripe/)

= No external services required - works directly with Stripe Webhooks =

This plugin receives Stripe webhook events directly and sends emails using WordPress's built-in mail function. No Zapier, Make, or other third-party automation services needed.

= Key Features =

* **Automatic Email Sending** - Sends thank-you emails automatically via Stripe Webhook
* **Multi-language Support** - Up to 10 customizable email templates with locale settings
* **Smart Language Detection** - Automatically detects customer language from Payment Link ID or locale
* **Customizable Templates** - Fully customizable email subject and body with placeholders
* **Custom Sender Settings** - Set custom From email address and sender name
* **Duplicate Prevention** - Built-in idempotency prevents sending duplicate emails
* **Test Email Function** - Send test emails to verify your settings before going live
* **Email Log** - View history of sent emails in the admin panel
* **Secure Webhook Verification** - Validates Stripe webhook signatures (HMAC-SHA256)

= How It Works =

1. Customer makes a purchase via your Stripe Payment Link
2. Stripe sends a webhook event to your WordPress site
3. Plugin verifies the webhook signature for security
4. Plugin detects customer language based on Payment Link ID
5. Thank-you email is sent to the customer using wp_mail()
6. Transaction is logged to prevent duplicate sends on retries

= Use Cases =

* Digital product sales (software, ebooks, courses)
* Software license key distribution (manual follow-up)
* Service booking confirmations
* Donation thank-you messages
* Any Stripe Payment Links checkout

= Requirements =

* WordPress 5.0 or higher
* PHP 7.4 or higher
* Stripe account with Payment Links
* SSL certificate (HTTPS) - required for webhooks
* SMTP plugin recommended for reliable email delivery

== Installation ==

1. Upload the `thanks-mail-for-stripe` folder to the `/wp-content/plugins/` directory
2. Activate the plugin via the 'Plugins' menu in WordPress
3. Go to Settings > Thanks Mail for Stripe to configure
4. Copy the Webhook URL and register it in Stripe Dashboard
5. Enter your Webhook Signing Secret from Stripe
6. Configure your Payment Link IDs for language detection
7. Customize your email templates
8. Send a test email to verify your settings

= Stripe Dashboard Setup =

1. Go to Stripe Dashboard > Developers > Webhooks
2. Click "Add endpoint"
3. Paste the Webhook URL from the plugin settings
4. Select events: `checkout.session.completed` and `checkout.session.async_payment_succeeded`
5. Copy the Signing Secret and paste it in the plugin settings

== Screenshots ==

1. Settings page - Configure Stripe webhook and email sender settings
2. Email templates - Customize email content
3. Test email - Send test emails to verify configuration
4. Email log - View history of sent thank-you emails

== Frequently Asked Questions ==

= Where can I find detailed documentation? =

A full walkthrough with Stripe webhook setup, template design tips, multi-language examples, and common pitfalls when shipping license keys automatically:

* [Thanks Mail for Stripe — Developer's Guide & Setup Walkthrough](https://raplsworks.com/thanks-mail-for-stripe/)
* [Source code on GitHub](https://github.com/rapls/thanks-mail-for-stripe)
* [Developer's blog — Rapls Works](https://raplsworks.com/)

= Why am I getting 403 errors on the webhook? =

This is usually caused by security plugins or server WAF settings blocking the webhook requests. Common solutions:

* **Wordfence**: Add the webhook URL to the allowlist (Firewall > All Firewall Options > Allowlisted URLs)
* **Xserver WAF**: Disable "REST API Access Restriction" in server panel (this blocks overseas IPs including Stripe)
* **Cloudflare**: Create a firewall rule to allow Stripe IPs
* **Other security plugins**: Whitelist the webhook endpoint URL

For a detailed case study on troubleshooting Xserver WAF and webhook 501 errors, see the [setup guide](https://raplsworks.com/thanks-mail-for-stripe/).

= Why is the email not being sent even though webhook returns 200? =

Check the "Recent Sent Emails" log in the settings page. If you see the transaction listed, the plugin's duplicate prevention is working - the email was already sent for that checkout session. This is intentional to prevent duplicate emails when Stripe retries webhooks.

= How do I detect customer language? =

The plugin uses two methods for language detection (in order of priority):

1. **Payment Link ID** - If you create separate Payment Links for JA and EN customers and enter their IDs in settings, the plugin will detect language based on which link was used.
2. **Locale fallback** - If Payment Link matching fails, it checks the `locale` parameter from the checkout session.

= Can I use this with WooCommerce? =

This plugin is designed specifically for Stripe Payment Links (standalone checkout). If you're using WooCommerce with Stripe, use WooCommerce's built-in order email system instead.

= How do I test the webhook? =

1. Use Stripe's test mode
2. Set up webhook with test mode signing secret
3. Create a test payment using card number `4242 4242 4242 4242`
4. Check the webhook logs in Stripe Dashboard for 200 response
5. Check the "Recent Sent Emails" in plugin settings

= Why should I use separate Payment Links for each language? =

Using separate Payment Links is the most reliable way to detect customer language. The Payment Link ID is included in every webhook event, so language detection never fails. Using the same Payment Link with locale detection as fallback works but may be less reliable.

= What placeholders are available in email templates? =

* `{brand}` - Your brand name (configured in settings)
* `{session_id}` - Stripe Checkout Session ID (for reference/support)
* `{email}` - Customer's email address

= Will emails be sent for test mode purchases? =

Yes, if you configure the test mode webhook signing secret. Use this to verify your setup before going live.

= What happens if wp_mail() fails? =

The webhook will still return 200 to Stripe (to prevent retries), but the email won't be sent. Consider using an SMTP plugin (like WP Mail SMTP) for more reliable email delivery.

= Why are my emails not being delivered even though the webhook shows "sent: true"? =

This is usually caused by email authentication issues. You must use your own domain email address (e.g., info@yoursite.com) as the "From Email" setting.

**Do NOT use Gmail, Yahoo, or other free email services as the From address.** When your server sends an email claiming to be from @gmail.com, receiving servers detect this as spoofing because:

* **SPF check fails** - Gmail's SPF record doesn't authorize your server
* **DKIM check fails** - Your server can't sign with Gmail's key
* **DMARC policy** - Gmail's strict policy causes rejection

**Solution:** Use an email address that matches your website domain for the "From Email" setting. You can still use any email address for the "Reply-To" setting.

= Does this plugin store customer data? =

Yes, the plugin stores a log of sent emails including the customer's email address, Stripe session ID, and timestamp. This data is used to prevent duplicate emails and provide delivery history. All data is automatically deleted when you uninstall the plugin. See the "Data & Privacy" section for details.

= Is this plugin GDPR compliant? =

The plugin stores customer email addresses for the legitimate business purpose of preventing duplicate emails and maintaining delivery records. You should disclose this data collection in your privacy policy. The data is stored only in your WordPress database and is not shared with third parties.

== Other Notes ==

= Email Sender Best Practices =

**Important:** Always use your own domain email address as the "From Email" setting.

* **Good:** info@yoursite.com, support@yoursite.com
* **Bad:** yourname@gmail.com, yourname@yahoo.com

Using Gmail or other free email services as the sender will cause delivery failures due to SPF/DKIM/DMARC authentication. Your server is not authorized to send emails on behalf of Gmail.

You CAN use any email address (including Gmail) for the "Reply-To" setting - this only affects where replies go, not email deliverability.

= Recommended SMTP Plugins =

For reliable email delivery, we recommend using an SMTP plugin:

* WP Mail SMTP
* Post SMTP
* FluentSMTP

These plugins send emails through a proper SMTP server instead of PHP's mail() function, improving deliverability.

= Test vs Live Mode =

Remember to update your settings when switching from test to live mode:

* **Webhook Signing Secret** - Test and Live have different secrets (whsec_...)
* **Payment Link IDs** - Test and Live have different IDs (plink_...)
* **Webhook Endpoints** - Register endpoints in both Test and Live mode in Stripe Dashboard

= Security Best Practices =

* The plugin verifies Stripe webhook signatures using HMAC-SHA256
* Webhook requests are validated within a 5-minute tolerance window
* Session IDs are stored to prevent duplicate email sends
* All settings are properly sanitized and escaped

= Available Filter Hooks =

The plugin provides filter hooks for customization:

* `tmfs_email_headers` - Modify email headers before sending
* `tmfs_email_subject` - Customize email subject
* `tmfs_email_body` - Customize email body
* `tmfs_detect_language` - Override language detection logic

= Example: Add CC to emails =

    add_filter( 'tmfs_email_headers', function( $headers, $to, $lang, $session_id ) {
        $headers[] = 'Cc: sales@example.com';
        return $headers;
    }, 10, 4 );

= Example: Custom language detection =

    add_filter( 'tmfs_detect_language', function( $lang, $session ) {
        // Custom logic based on session data
        if ( strpos( $session['customer_details']['email'], '.jp' ) !== false ) {
            return 'ja';
        }
        return $lang;
    }, 10, 2 );

= Database Table =

The plugin creates a table `{prefix}stm_sent_emails` to track sent emails:

* `id` - Auto-increment ID
* `session_id` - Stripe Checkout Session ID (unique)
* `email` - Customer email address
* `lang` - Detected language (ja/en)
* `product_name` - Product name (if available)
* `amount` - Purchase amount
* `sent_at` - Timestamp when email was sent

= Troubleshooting Webhook Issues =

If webhooks aren't working, check these common issues:

1. **SSL Certificate** - Stripe requires HTTPS for webhooks
2. **Webhook URL** - Make sure the URL is correct and accessible
3. **Signing Secret** - Verify you're using the correct secret for test/live mode
4. **Server Firewall** - Some hosts block requests from overseas IPs
5. **Security Plugins** - May block REST API endpoints
6. **WAF Rules** - Server-level WAF may block webhook requests

Check Stripe Dashboard > Developers > Webhooks for detailed error logs.

== Data & Privacy ==

This plugin stores email delivery logs in your WordPress database to prevent duplicate sending and allow administrators to verify delivery history. No data is transmitted to external services by this plugin.

= What Data Is Stored =

The plugin stores the following data in a custom database table (`{prefix}stm_sent_emails`):

* `session_id` - Stripe Checkout Session ID (used as unique key for duplicate prevention)
* `email` - Customer email address (to confirm which customer received the email)
* `lang` - Detected language code, ja or en (to record which template was used)
* `product_name` - Product name (reserved for future use)
* `amount` - Purchase amount and currency (for administrator reference)
* `sent_at` - Timestamp when the email was sent

Additionally, plugin settings (webhook secret, email templates, Payment Link IDs, etc.) are stored in the `wp_options` table under the key `stm_settings`.

= Purpose =

* **Duplicate prevention** - Stripe may retry webhook events; the session ID prevents sending the same email twice
* **Delivery confirmation** - Administrators can verify that emails were sent to the correct address
* **Customer support** - Transaction reference for handling customer inquiries

= Data Retention =

* Email logs are stored indefinitely by default
* Administrators can manually delete individual records via database access
* **All data (logs and settings) is automatically removed when uninstalling the plugin** via the WordPress admin

= External Services =

* This plugin receives incoming webhook events from Stripe - it does not make outbound API calls to Stripe
* No customer data is sent to any third-party service by this plugin
* Emails are sent using WordPress's built-in `wp_mail()` function (delivery depends on your server or SMTP plugin configuration)

= GDPR Considerations =

* Customer email addresses are stored for the legitimate business purpose of preventing duplicate emails and maintaining delivery records
* You should disclose this data storage in your site's privacy policy
* Data can be exported or deleted upon customer request via direct database access

== Changelog ==

= 1.1.0 =
* New: Dynamic email templates - configure 1 to 10 templates with add/remove buttons
* New: Each template has its own label, locale setting, Payment Link ID, subject, and body
* New: Per-template reset button to restore default values
* New: Templates are matched to Stripe webhooks via Payment Link ID or locale fallback
* Improved: Settings data migrated automatically from flat keys to array format
* Breaking: `tmfs_detect_language` filter now returns template index (string) instead of language code

= 1.0.4 =
* Fixed: Renamed all internal prefixes from stm_ to tmfs_ (option name, table name, settings group, transient keys, JS global variable) to meet WordPress.org 4-character minimum prefix requirement
* Fixed: Plugin URI restored after page publication

= 1.0.3 =
* Fixed: Renamed main class to TMFS_Thanks_Mail for WordPress.org naming convention compliance
* Fixed: Renamed all filter hooks from stm_ to tmfs_ prefix (tmfs_email_subject, tmfs_email_body, tmfs_email_headers, tmfs_detect_language)
* Fixed: Renamed uninstall function to tmfs_uninstall_cleanup for global namespace prefix compliance
* Fixed: Prefixed template global variables ($tmfs_recent_emails, $tmfs_email)
* Fixed: Added phpcs:ignore for WordPress.DB.PreparedSQL.InterpolatedNotPrepared on custom table queries
* Removed: load_plugin_textdomain() call (unnecessary since WordPress 4.6 for WordPress.org hosted plugins)
* Fixed: Tested up to version format (major version only)

= 1.0.2 =
* Fixed: Removed external resource loading (BuyMeACoffee CDN image) - replaced with local text link
* Fixed: Moved inline JavaScript to properly enqueued external file (assets/js/admin.js)
* Fixed: Renamed global constants from STM_ to TMFS_ prefix to avoid conflicts with other plugins
* Fixed: Added define() conflict guards for all plugin constants
* Improved: $_SERVER['REMOTE_ADDR'] access now uses wp_unslash() and filter_var() with FILTER_VALIDATE_IP
* Improved: error_log() calls wrapped with WP_DEBUG check
* Improved: Restored esc_sql() for table name sanitization in uninstall.php
* Added: REST API schema definitions with validation for /test endpoint parameters

= 1.0.1 =
* Fixed: Email send failures are no longer incorrectly recorded as sent in the database
* Added: Email address validation before sending (using WordPress is_email())
* Added: Rate limiting on webhook endpoint (10 requests per 60 seconds per IP)
* Improved: Clearer Data & Privacy documentation in readme

= 1.0.0 =
* Initial release
* Webhook-based automatic thank-you email sending
* Japanese and English template support
* Language detection via Payment Link ID or locale
* Custom sender email and name settings
* Duplicate prevention using session ID tracking
* Test email functionality
* Email sending log with recent history
* Secure webhook signature verification
* Admin settings page with quick setup guide
* Japanese translation included

== Upgrade Notice ==

= 1.1.0 =
Dynamic templates: configure 1-10 email templates with per-template Payment Link ID and locale settings. Existing settings are automatically migrated.

= 1.0.4 =
Full tmfs_ prefix compliance: all internal option names, table names, and JS globals now use 4+ character prefix.

= 1.0.3 =
WordPress.org naming convention compliance: renamed class, hooks, and functions to use tmfs_ prefix.

= 1.0.2 =
WordPress.org review compliance: removed external resources, enqueued JavaScript properly, improved constant naming and input handling.

= 1.0.1 =
Bug fix: emails that fail to send are no longer recorded as sent. Added webhook rate limiting and email validation.

= 1.0.0 =
Initial release of Thanks Mail for Stripe.