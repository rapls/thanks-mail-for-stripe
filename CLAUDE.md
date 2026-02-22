# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

Thanks Mail for Stripe is a WordPress plugin that automatically sends thank-you emails when Stripe Payment Links purchases are completed. It supports Japanese and English with customizable email templates.

## Architecture

### Single-File Plugin Pattern
The entire plugin logic is contained in `thanks-mail-for-stripe.php` using a singleton class `TMFS_Thanks_Mail`. This is intentional for simplicity—do not split into multiple class files.

### Key Components

**Main Class (`TMFS_Thanks_Mail`)**
- Singleton pattern via `get_instance()`
- Constants: `OPTION_NAME` (stm_settings), `TABLE_NAME` (stm_sent_emails), `REST_NAMESPACE` (thanks-mail/v1)
- Handles: webhook processing, email sending, admin settings, REST API

**Database**
- Custom table `{prefix}_stm_sent_emails` stores sent email log with session_id as unique key for idempotency
- Settings stored in wp_options under `stm_settings`

**REST Endpoints**
- `POST /wp-json/thanks-mail/v1/webhook` - Stripe webhook handler (public)
- `POST /wp-json/thanks-mail/v1/test` - Test email sender (requires manage_options)

### Webhook Flow
1. Verify Stripe signature using webhook secret
2. Only process `checkout.session.completed` and `checkout.session.async_payment_succeeded` events
3. Check payment_status is 'paid'
4. Check idempotency via session_id in database
5. Detect language from Payment Link ID or locale
6. Send email via `wp_mail()` and record to database

### Language Detection Priority
1. Payment Link ID match (configured JA/EN payment links)
2. Session locale (falls back to 'en' if not 'ja')

### Available Filters
- `tmfs_detect_language` - Modify detected language
- `tmfs_email_subject` - Modify email subject
- `tmfs_email_body` - Modify email body
- `tmfs_email_headers` - Modify email headers

### Locale-Aware Admin UI
The admin settings page reorders Payment Link ID fields based on `get_locale()`:
- Japanese locale: JA field first (primary), EN field second (optional)
- Other locales: EN field first (primary), JA field second (optional)

## Internationalization

- Text domain: `thanks-mail-for-stripe`
- Translation files in `languages/` directory (PO/MO format)
- All user-facing strings use `esc_html_e()` / `__()` with the text domain
- When adding new translatable strings, update the PO file and regenerate MO:
  ```
  msgfmt -o languages/thanks-mail-for-stripe-ja.mo languages/thanks-mail-for-stripe-ja.po
  ```

## Development Notes

- This is a Local by Flywheel WordPress environment
- No build process—CSS is plain, no JavaScript bundling
- Admin styles in `assets/css/admin.css`
- Admin JS in `assets/js/admin.js` (enqueued via `wp_enqueue_script()`, data passed via `wp_localize_script()`)
- Admin template in `templates/admin-settings.php`
- Global constants use `TMFS_` prefix (e.g., `TMFS_VERSION`, `TMFS_PLUGIN_DIR`) with `if (!defined())` guards
- All input sanitization uses WordPress functions (`sanitize_text_field`, `sanitize_email`, etc.)
- Signature verification implements Stripe's v1 scheme with timestamp tolerance
- Direct database queries on the custom table require `phpcs:ignore` comments for WordPress Plugin Check compliance
- Blog post for distribution: `docs/blog-post-ja.html`

## Email Placeholders
`{brand}`, `{session_id}`, `{email}` - replaced in subject and body templates


<claude-mem-context>

</claude-mem-context>