<?php
/**
 * Admin settings page template
 */

defined('ABSPATH') || exit;
?>
<div class="wrap stm-admin">
    <h1><?php esc_html_e('Thanks Mail for Stripe Settings', 'thanks-mail-for-stripe'); ?></h1>

    <!-- Setup Guide -->
    <div class="stm-card stm-help-card">
        <h2><?php esc_html_e('Quick Setup Guide', 'thanks-mail-for-stripe'); ?></h2>
        <ol>
            <li><?php esc_html_e('Copy the Webhook URL below and register it in Stripe Dashboard → Developers → Webhooks → Add endpoint', 'thanks-mail-for-stripe'); ?></li>
            <li><?php esc_html_e('Select events: checkout.session.completed and checkout.session.async_payment_succeeded', 'thanks-mail-for-stripe'); ?></li>
            <li><?php esc_html_e('Copy the Webhook Signing Secret (whsec_...) and paste it below', 'thanks-mail-for-stripe'); ?></li>
            <li><?php esc_html_e('Enter your Payment Link ID (plink_...) - found in the Payment Link URL in Stripe Dashboard', 'thanks-mail-for-stripe'); ?></li>
            <li><?php esc_html_e('Customize your email templates and save', 'thanks-mail-for-stripe'); ?></li>
            <li><?php esc_html_e('Send a test email to verify your settings', 'thanks-mail-for-stripe'); ?></li>
        </ol>
        <p class="description">
            <strong><?php esc_html_e('Important:', 'thanks-mail-for-stripe'); ?></strong>
            <?php esc_html_e('If you\'re using a security plugin (Wordfence, etc.) or server WAF, you may need to whitelist the Webhook URL to allow Stripe\'s requests.', 'thanks-mail-for-stripe'); ?>
        </p>
    </div>

    <form method="post" action="options.php">
        <?php settings_fields('tmfs_settings_group'); ?>

        <!-- Status -->
        <div class="stm-card stm-status-card">
            <h2><?php esc_html_e('Status', 'thanks-mail-for-stripe'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Enable', 'thanks-mail-for-stripe'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="<?php echo esc_attr(TMFS_Thanks_Mail::OPTION_NAME); ?>[enabled]" value="1" <?php checked($settings['enabled']); ?>>
                            <?php esc_html_e('Enable thank-you email sending', 'thanks-mail-for-stripe'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Webhook URL', 'thanks-mail-for-stripe'); ?></th>
                    <td>
                        <code class="stm-webhook-url"><?php echo esc_url($webhook_url); ?></code>
                        <button type="button" class="button button-small stm-copy-btn" data-copy="<?php echo esc_attr($webhook_url); ?>">
                            <?php esc_html_e('Copy', 'thanks-mail-for-stripe'); ?>
                        </button>
                        <p class="description">
                            <?php esc_html_e('Register this URL in Stripe Dashboard → Developers → Webhooks', 'thanks-mail-for-stripe'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Emails Sent', 'thanks-mail-for-stripe'); ?></th>
                    <td>
                        <strong><?php echo esc_html(TMFS_Thanks_Mail::get_instance()->get_sent_emails_count()); ?></strong>
                        <?php esc_html_e('emails', 'thanks-mail-for-stripe'); ?>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Stripe Settings -->
        <div class="stm-card">
            <h2><?php esc_html_e('Stripe Settings', 'thanks-mail-for-stripe'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="stm_webhook_secret"><?php esc_html_e('Webhook Signing Secret', 'thanks-mail-for-stripe'); ?></label>
                    </th>
                    <td>
                        <input type="password" id="stm_webhook_secret" name="<?php echo esc_attr(TMFS_Thanks_Mail::OPTION_NAME); ?>[webhook_secret]"
                               value="<?php echo esc_attr($settings['webhook_secret']); ?>"
                               class="regular-text" placeholder="whsec_...">
                        <button type="button" class="button button-small stm-toggle-password" data-target="stm_webhook_secret">
                            <?php esc_html_e('Show', 'thanks-mail-for-stripe'); ?>
                        </button>
                        <p class="description">
                            <?php esc_html_e('Found in Stripe Dashboard → Developers → Webhooks → Your endpoint → Signing secret', 'thanks-mail-for-stripe'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- General Settings -->
        <div class="stm-card">
            <h2><?php esc_html_e('General Settings', 'thanks-mail-for-stripe'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="stm_brand_name"><?php esc_html_e('Brand Name', 'thanks-mail-for-stripe'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="stm_brand_name" name="<?php echo esc_attr(TMFS_Thanks_Mail::OPTION_NAME); ?>[brand_name]"
                               value="<?php echo esc_attr($settings['brand_name']); ?>"
                               class="regular-text">
                        <p class="description">
                            <?php esc_html_e('Used in email subject and body ({brand} placeholder)', 'thanks-mail-for-stripe'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="stm_from_email"><?php esc_html_e('From Email', 'thanks-mail-for-stripe'); ?></label>
                    </th>
                    <td>
                        <input type="email" id="stm_from_email" name="<?php echo esc_attr(TMFS_Thanks_Mail::OPTION_NAME); ?>[from_email]"
                               value="<?php echo esc_attr($settings['from_email']); ?>"
                               class="regular-text" placeholder="info@example.com">
                        <p class="description">
                            <?php esc_html_e('Sender email address (e.g., info@yoursite.com)', 'thanks-mail-for-stripe'); ?>
                        </p>
                        <p class="description" style="color: #d63638;">
                            <strong><?php esc_html_e('Important:', 'thanks-mail-for-stripe'); ?></strong>
                            <?php esc_html_e('Use your own domain email (e.g., info@yoursite.com). Gmail or other free email addresses may cause delivery failures due to SPF/DKIM authentication.', 'thanks-mail-for-stripe'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="stm_from_name"><?php esc_html_e('From Name', 'thanks-mail-for-stripe'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="stm_from_name" name="<?php echo esc_attr(TMFS_Thanks_Mail::OPTION_NAME); ?>[from_name]"
                               value="<?php echo esc_attr($settings['from_name']); ?>"
                               class="regular-text" placeholder="Your Company Name">
                        <p class="description">
                            <?php esc_html_e('Sender name displayed in email client', 'thanks-mail-for-stripe'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="stm_support_email"><?php esc_html_e('Reply-To Email', 'thanks-mail-for-stripe'); ?></label>
                    </th>
                    <td>
                        <input type="email" id="stm_support_email" name="<?php echo esc_attr(TMFS_Thanks_Mail::OPTION_NAME); ?>[support_email]"
                               value="<?php echo esc_attr($settings['support_email']); ?>"
                               class="regular-text">
                        <p class="description">
                            <?php esc_html_e('Replies to thank-you emails will go to this address', 'thanks-mail-for-stripe'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Email Templates (Dynamic) -->
        <div class="stm-card">
            <h2><?php esc_html_e('Email Templates', 'thanks-mail-for-stripe'); ?></h2>
            <p class="description">
                <?php
                printf(
                    /* translators: %d: maximum number of templates */
                    esc_html__('You can configure up to %d email templates. Each template can be linked to a specific Stripe Payment Link ID.', 'thanks-mail-for-stripe'),
                    esc_html(TMFS_Thanks_Mail::MAX_TEMPLATES)
                );
                ?>
            </p>
        </div>

        <div id="tmfs-templates-container">
            <?php foreach ($tmfs_templates as $tmfs_index => $tmfs_tmpl) : ?>
            <div class="stm-card stm-template-card" data-index="<?php echo esc_attr($tmfs_index); ?>">
                <div class="stm-template-header">
                    <input type="text" class="tmfs-template-label"
                           name="<?php echo esc_attr(TMFS_Thanks_Mail::OPTION_NAME); ?>[templates][<?php echo esc_attr($tmfs_index); ?>][label]"
                           value="<?php echo esc_attr($tmfs_tmpl['label']); ?>"
                           placeholder="<?php esc_attr_e('Template name', 'thanks-mail-for-stripe'); ?>">
                    <select name="<?php echo esc_attr(TMFS_Thanks_Mail::OPTION_NAME); ?>[templates][<?php echo esc_attr($tmfs_index); ?>][locale]">
                        <option value="" <?php selected($tmfs_tmpl['locale'], ''); ?>><?php esc_html_e('— Locale —', 'thanks-mail-for-stripe'); ?></option>
                        <option value="ja" <?php selected($tmfs_tmpl['locale'], 'ja'); ?>>JA</option>
                        <option value="en" <?php selected($tmfs_tmpl['locale'], 'en'); ?>>EN</option>
                    </select>
                    <button type="button" class="button button-small tmfs-delete-template" title="<?php esc_attr_e('Delete', 'thanks-mail-for-stripe'); ?>">&times;</button>
                </div>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label><?php esc_html_e('Payment Link ID', 'thanks-mail-for-stripe'); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   name="<?php echo esc_attr(TMFS_Thanks_Mail::OPTION_NAME); ?>[templates][<?php echo esc_attr($tmfs_index); ?>][payment_link]"
                                   value="<?php echo esc_attr($tmfs_tmpl['payment_link']); ?>"
                                   class="regular-text" placeholder="plink_...">
                            <p class="description">
                                <?php esc_html_e('Stripe Payment Link ID (e.g., plink_1234567890)', 'thanks-mail-for-stripe'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label><?php esc_html_e('Subject', 'thanks-mail-for-stripe'); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   name="<?php echo esc_attr(TMFS_Thanks_Mail::OPTION_NAME); ?>[templates][<?php echo esc_attr($tmfs_index); ?>][subject]"
                                   value="<?php echo esc_attr($tmfs_tmpl['subject']); ?>"
                                   class="large-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label><?php esc_html_e('Body', 'thanks-mail-for-stripe'); ?></label>
                        </th>
                        <td>
                            <textarea name="<?php echo esc_attr(TMFS_Thanks_Mail::OPTION_NAME); ?>[templates][<?php echo esc_attr($tmfs_index); ?>][body]"
                                      rows="12" class="large-text code tmfs-field-body"><?php echo esc_textarea($tmfs_tmpl['body']); ?></textarea>
                        </td>
                    </tr>
                </table>
                <p class="tmfs-template-footer">
                    <button type="button" class="button button-small tmfs-reset-template">
                        <?php esc_html_e('Reset to default', 'thanks-mail-for-stripe'); ?>
                    </button>
                </p>
            </div>
            <?php endforeach; ?>
        </div>

        <p>
            <button type="button" id="tmfs-add-template" class="button button-secondary">
                + <?php esc_html_e('Add Template', 'thanks-mail-for-stripe'); ?>
            </button>
        </p>

        <!-- Hidden template for JS cloning -->
        <script type="text/template" id="tmfs-template-tmpl">
            <div class="stm-card stm-template-card" data-index="__INDEX__">
                <div class="stm-template-header">
                    <input type="text" class="tmfs-template-label"
                           name="<?php echo esc_attr(TMFS_Thanks_Mail::OPTION_NAME); ?>[templates][__INDEX__][label]"
                           value=""
                           placeholder="<?php esc_attr_e('Template name', 'thanks-mail-for-stripe'); ?>">
                    <select name="<?php echo esc_attr(TMFS_Thanks_Mail::OPTION_NAME); ?>[templates][__INDEX__][locale]">
                        <option value=""><?php esc_html_e('— Locale —', 'thanks-mail-for-stripe'); ?></option>
                        <option value="ja">JA</option>
                        <option value="en">EN</option>
                    </select>
                    <button type="button" class="button button-small tmfs-delete-template" title="<?php esc_attr_e('Delete', 'thanks-mail-for-stripe'); ?>">&times;</button>
                </div>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label><?php esc_html_e('Payment Link ID', 'thanks-mail-for-stripe'); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   name="<?php echo esc_attr(TMFS_Thanks_Mail::OPTION_NAME); ?>[templates][__INDEX__][payment_link]"
                                   value=""
                                   class="regular-text" placeholder="plink_...">
                            <p class="description">
                                <?php esc_html_e('Stripe Payment Link ID (e.g., plink_1234567890)', 'thanks-mail-for-stripe'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label><?php esc_html_e('Subject', 'thanks-mail-for-stripe'); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   name="<?php echo esc_attr(TMFS_Thanks_Mail::OPTION_NAME); ?>[templates][__INDEX__][subject]"
                                   value=""
                                   class="large-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label><?php esc_html_e('Body', 'thanks-mail-for-stripe'); ?></label>
                        </th>
                        <td>
                            <textarea name="<?php echo esc_attr(TMFS_Thanks_Mail::OPTION_NAME); ?>[templates][__INDEX__][body]"
                                      rows="12" class="large-text code tmfs-field-body"></textarea>
                        </td>
                    </tr>
                </table>
                <p class="tmfs-template-footer">
                    <button type="button" class="button button-small tmfs-reset-template">
                        <?php esc_html_e('Reset to default', 'thanks-mail-for-stripe'); ?>
                    </button>
                </p>
            </div>
        </script>

        <!-- Placeholders Help -->
        <div class="stm-card stm-help-card">
            <h2><?php esc_html_e('Available Placeholders', 'thanks-mail-for-stripe'); ?></h2>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Placeholder', 'thanks-mail-for-stripe'); ?></th>
                        <th><?php esc_html_e('Description', 'thanks-mail-for-stripe'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>{brand}</code></td>
                        <td><?php esc_html_e('Brand name', 'thanks-mail-for-stripe'); ?></td>
                    </tr>
                    <tr>
                        <td><code>{session_id}</code></td>
                        <td><?php esc_html_e('Stripe Checkout Session ID (for reference)', 'thanks-mail-for-stripe'); ?></td>
                    </tr>
                    <tr>
                        <td><code>{email}</code></td>
                        <td><?php esc_html_e('Customer email address', 'thanks-mail-for-stripe'); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <?php submit_button(__('Save Settings', 'thanks-mail-for-stripe')); ?>
    </form>

    <!-- Test Email -->
    <div class="stm-card stm-test-card">
        <h2><?php esc_html_e('Send Test Email', 'thanks-mail-for-stripe'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="stm_test_email"><?php esc_html_e('Email Address', 'thanks-mail-for-stripe'); ?></label>
                </th>
                <td>
                    <input type="email" id="stm_test_email" value="<?php echo esc_attr(get_option('admin_email')); ?>" class="regular-text">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="stm_test_lang"><?php esc_html_e('Template', 'thanks-mail-for-stripe'); ?></label>
                </th>
                <td>
                    <select id="stm_test_lang">
                        <?php foreach ($tmfs_templates as $tmfs_index => $tmfs_tmpl) : ?>
                        <option value="<?php echo esc_attr($tmfs_index); ?>">
                            <?php echo esc_html($tmfs_tmpl['label'] ?: __('Template', 'thanks-mail-for-stripe') . ' #' . ($tmfs_index + 1)); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th></th>
                <td>
                    <button type="button" id="stm_send_test" class="button button-secondary">
                        <?php esc_html_e('Send Test Email', 'thanks-mail-for-stripe'); ?>
                    </button>
                    <span id="stm_test_status"></span>
                </td>
            </tr>
        </table>
    </div>

    <!-- Reset Settings -->
    <div class="stm-card">
        <h2><?php esc_html_e('Reset Settings', 'thanks-mail-for-stripe'); ?></h2>
        <p class="description">
            <?php esc_html_e('Reset all settings to their default values.', 'thanks-mail-for-stripe'); ?>
        </p>
        <p>
            <button type="button" id="stm_reset_settings" class="button button-secondary" style="color: #d63638; border-color: #d63638;">
                <?php esc_html_e('Reset Settings', 'thanks-mail-for-stripe'); ?>
            </button>
            <span id="stm_reset_status"></span>
        </p>
    </div>

    <!-- Recent Emails -->
    <div class="stm-card">
        <h2><?php esc_html_e('Recent Sent Emails', 'thanks-mail-for-stripe'); ?></h2>
        <?php
        $tmfs_recent_emails = TMFS_Thanks_Mail::get_instance()->get_sent_emails(20);
        if (empty($tmfs_recent_emails)):
        ?>
            <p class="description"><?php esc_html_e('No emails sent yet.', 'thanks-mail-for-stripe'); ?></p>
        <?php else: ?>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Date', 'thanks-mail-for-stripe'); ?></th>
                        <th><?php esc_html_e('Email', 'thanks-mail-for-stripe'); ?></th>
                        <th><?php esc_html_e('Template', 'thanks-mail-for-stripe'); ?></th>
                        <th><?php esc_html_e('Session ID', 'thanks-mail-for-stripe'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tmfs_recent_emails as $tmfs_email): ?>
                    <tr>
                        <td><?php echo esc_html($tmfs_email['sent_at']); ?></td>
                        <td><?php echo esc_html($tmfs_email['email']); ?></td>
                        <td><?php echo esc_html(strtoupper($tmfs_email['lang'])); ?></td>
                        <td><code style="font-size: 11px;"><?php echo esc_html(substr($tmfs_email['session_id'], 0, 30) . '...'); ?></code></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Support -->
    <div class="stm-card stm-support-card">
        <h2><?php esc_html_e( 'Support This Plugin', 'thanks-mail-for-stripe' ); ?></h2>
        <p><?php esc_html_e( 'If you find this plugin useful, please consider supporting its development.', 'thanks-mail-for-stripe' ); ?></p>
        <p>
            <a href="https://www.buymeacoffee.com/rapls" target="_blank" rel="noopener noreferrer" class="button button-primary">
                &#9749; Buy Me A Coffee
            </a>
        </p>
        <p class="tmfs-review-link">
            <?php
            printf(
                /* translators: %s: WordPress.org review page link */
                esc_html__( 'Enjoying this plugin? A %s would be greatly appreciated!', 'thanks-mail-for-stripe' ),
                '<a href="https://wordpress.org/support/plugin/thanks-mail-for-stripe/reviews/#new-post" target="_blank" rel="noopener noreferrer">&#9733;&#9733;&#9733;&#9733;&#9733; ' . esc_html__( 'review on WordPress.org', 'thanks-mail-for-stripe' ) . '</a>'
            );
            ?>
        </p>
    </div>
</div>
