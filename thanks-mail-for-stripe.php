<?php

/**
 * Thanks Mail for Stripe
 *
 * Automatically send thank-you emails when Stripe Payment Links purchases are completed.
 * Supports Japanese and English with customizable templates. No external services required.
 *
 * @package     TMFS_Thanks_Mail
 * @author      Rapls Works
 * @copyright   2026 Rapls Works
 * @license     GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Thanks Mail for Stripe
 * Plugin URI:        https://raplsworks.com/plugins/thanks-mail-for-stripe/
 * Description:       Automatically send thank-you emails when Stripe Payment Links purchases are completed. Supports Japanese and English with customizable templates.
 * Version:           1.2.0
 * Requires at least: 5.0
 * Requires PHP:      7.4
 * Author:            Rapls Works
 * Author URI:        https://raplsworks.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       thanks-mail-for-stripe
 */

// Prevent direct access
defined('ABSPATH') || exit;

/**
 * Plugin version.
 *
 * @var string
 */
if ( ! defined( 'TMFS_VERSION' ) ) {
    define( 'TMFS_VERSION', '1.2.0' );
}

/**
 * Plugin directory path.
 *
 * @var string
 */
if ( ! defined( 'TMFS_PLUGIN_DIR' ) ) {
    define( 'TMFS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

/**
 * Plugin directory URL.
 *
 * @var string
 */
if ( ! defined( 'TMFS_PLUGIN_URL' ) ) {
    define( 'TMFS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

/**
 * Plugin basename.
 *
 * @var string
 */
if ( ! defined( 'TMFS_PLUGIN_BASENAME' ) ) {
    define( 'TMFS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
}

/**
 * Main plugin class.
 *
 * Handles webhook processing, email sending, and admin settings
 * for Stripe Payment Links thank-you emails.
 *
 * @since 1.0.0
 */
final class TMFS_Thanks_Mail
{

    /**
     * Singleton instance.
     *
     * @since 1.0.0
     * @var self|null
     */
    private static ?self $instance = null;

    /**
     * Option name for plugin settings.
     *
     * @since 1.0.0
     * @var string
     */
    const OPTION_NAME = 'tmfs_settings';

    /**
     * Database table name (without prefix).
     *
     * @since 1.0.0
     * @var string
     */
    const TABLE_NAME = 'tmfs_sent_emails';

    /**
     * REST API namespace for webhook endpoints.
     *
     * @since 1.0.0
     * @var string
     */
    const REST_NAMESPACE = 'thanks-mail/v1';

    /**
     * Maximum number of email templates.
     *
     * @since 1.1.0
     * @var int
     */
    const MAX_TEMPLATES = 100;

    /**
     * Schema version of the sent-emails table, stored in `tmfs_db_version`.
     * Bump it when the CREATE TABLE statement changes.
     *
     * @since 1.2.1
     * @var string
     */
    const DB_VERSION = '1';

    /**
     * Failed signature checks allowed per IP per minute before answering 429.
     *
     * @since 1.2.1
     * @var int
     */
    const MAX_SIGNATURE_FAILURES = 10;

    /**
     * Get singleton instance.
     *
     * @since 1.0.0
     * @return self Plugin instance.
     */
    public static function get_instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks(): void
    {
        // Activation, plus a schema check for installs that never ran it
        // (multisite subsites created later, sites copied without activating).
        register_activation_hook(__FILE__, [$this, 'activate']);
        add_action('plugins_loaded', [$this, 'maybe_create_table']);

        /*
         * Nothing loads translations. The catalogue is not shipped:
         * WordPress.org builds it from translate.wordpress.org and serves it into
         * wp-content/languages/plugins/, which core's just-in-time loader reads
         * without being asked. Strings added since the published pack was built
         * read English until GlotPress catches up; that trade was made on purpose.
         */

        // Privacy tools (Tools → Export / Erase Personal Data)
        add_filter('wp_privacy_personal_data_exporters', [$this, 'register_privacy_exporter']);
        add_filter('wp_privacy_personal_data_erasers', [$this, 'register_privacy_eraser']);

        // Admin
        if (is_admin()) {
            add_action('admin_menu', [$this, 'add_admin_menu']);
            add_action('admin_init', [$this, 'register_settings']);
            add_action('admin_enqueue_scripts', [$this, 'admin_scripts']);
            add_filter('plugin_action_links_' . TMFS_PLUGIN_BASENAME, [$this, 'plugin_action_links']);
        }

        // REST API
        add_action('rest_api_init', [$this, 'register_rest_routes']);

    }

    /**
     * Plugin activation
     */
    public function activate(): void
    {
        $this->create_table();

        // Set default options if not exists
        if (!get_option(self::OPTION_NAME)) {
            $defaults = $this->get_default_settings();
            update_option(self::OPTION_NAME, $defaults);
        }
    }

    /**
     * Create the sent-emails table when the stored schema version is behind.
     *
     * The activation hook alone misses network-activated subsites created
     * later and sites copied without reactivating. Without the table the
     * duplicate guard cannot work, so this runs on every load; it costs one
     * autoloaded option read.
     */
    public function maybe_create_table(): void
    {
        if (get_option('tmfs_db_version') !== self::DB_VERSION) {
            $this->create_table();
        }
    }

    /**
     * Create or update the sent-emails table via dbDelta.
     */
    private function create_table(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . self::TABLE_NAME;
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            lang VARCHAR(8) NOT NULL,
            product_name VARCHAR(255) DEFAULT '',
            amount VARCHAR(50) DEFAULT '',
            sent_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY session_id (session_id),
            KEY email (email),
            KEY sent_at (sent_at)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        update_option('tmfs_db_version', self::DB_VERSION);
    }

    /**
     * Get default settings
     */
    public function get_default_settings(): array
    {
        return [
            // General
            'enabled' => true,
            // Whether purchases that needed no payment (100% discount codes,
            // free trials) also get the email. Off by default: before 1.2.1
            // they never did.
            'send_no_payment_required' => false,
            'webhook_secret' => '',
            'brand_name' => get_bloginfo('name'),
            'from_email' => get_option('admin_email'),
            'from_name' => get_bloginfo('name'),
            'support_email' => get_option('admin_email'),

            // Managed categories (array of ['id' => int, 'name' => string]) and
            // the monotonic counter used to assign new category IDs.
            'categories' => [],
            'category_seq' => 1,

            // Templates
            'templates' => $this->get_default_templates(),
        ];
    }

    /**
     * Get default templates
     */
    public function get_default_templates(): array
    {
        $is_ja = strpos(get_locale(), 'ja') === 0;

        $subject_ja = '【{brand}】ご購入ありがとうございます';
        $body_ja = 'このたびはご購入いただき、誠にありがとうございます。

Stripeの領収書（Receipt）も別メールで届きますのでご確認ください。

ライセンスキーは手動で発行いたします。準備でき次第ご案内いたします（3営業日以内）。

(照合用) Checkout Session: {session_id}

――
※本メールに心当たりがない場合は、このメールに返信してご連絡ください。';

        $subject_en = '[{brand}] Thank you for your purchase';
        $body_en = 'Thank you for your purchase!

You will also receive a Stripe receipt (separate email). Please check it as well.

Your license key will be issued manually. We\'ll email you once it\'s ready (within 3 business days).

(Reference) Checkout Session: {session_id}

—
If you didn\'t make this purchase, please reply to this email.';

        if ($is_ja) {
            return [
                [
                    'label'        => 'No.1',
                    'category'     => 0,
                    'locale'       => 'ja',
                    'payment_link' => '',
                    'subject'      => $subject_ja,
                    'body'         => $body_ja,
                ],
                [
                    'label'        => 'No.2',
                    'category'     => 0,
                    'locale'       => 'en',
                    'payment_link' => '',
                    'subject'      => $subject_en,
                    'body'         => $body_en,
                ],
            ];
        }

        return [
            [
                'label'        => 'No.1',
                'category'     => 0,
                'locale'       => 'en',
                'payment_link' => '',
                'subject'      => $subject_en,
                'body'         => $body_en,
            ],
            [
                'label'        => 'No.2',
                'category'     => 0,
                'locale'       => 'ja',
                'payment_link' => '',
                'subject'      => $subject_ja,
                'body'         => $body_ja,
            ],
        ];
    }

    /**
     * Get template by index with fallback
     */
    private function get_template(array $settings, $index): array
    {
        $index = intval($index);
        if (isset($settings['templates'][$index])) {
            return $settings['templates'][$index];
        }
        if (!empty($settings['templates'])) {
            return $settings['templates'][0];
        }
        $defaults = $this->get_default_templates();
        return $defaults[0];
    }

    /**
     * Get settings
     */
    public function get_settings(): array
    {
        $settings = get_option(self::OPTION_NAME, []);
        $defaults = $this->get_default_settings();

        // Migration from legacy flat keys (v1.0.x → v1.1.0)
        if (isset($settings['payment_link_ja']) && !isset($settings['templates'])) {
            $settings['templates'] = [];
            $is_ja = strpos(get_locale(), 'ja') === 0;

            $ja_template = [
                'label'        => 'No.1',
                'locale'       => 'ja',
                'payment_link' => $settings['payment_link_ja'] ?? '',
                'subject'      => $settings['subject_ja'] ?? '',
                'body'         => $settings['body_ja'] ?? '',
            ];
            $en_template = [
                'label'        => 'No.2',
                'locale'       => 'en',
                'payment_link' => $settings['payment_link_en'] ?? '',
                'subject'      => $settings['subject_en'] ?? '',
                'body'         => $settings['body_en'] ?? '',
            ];

            $settings['templates'] = $is_ja
                ? [$ja_template, $en_template]
                : [$en_template, $ja_template];

            // Remove legacy keys
            unset(
                $settings['payment_link_ja'], $settings['payment_link_en'],
                $settings['subject_ja'], $settings['body_ja'],
                $settings['subject_en'], $settings['body_en']
            );

            update_option(self::OPTION_NAME, $settings);
        }

        // Migration: convert legacy string-list categories into {id, name}
        // objects and remap template category names to the new IDs.
        if (!empty($settings['categories']) && is_array($settings['categories'])) {
            $needs_category_migration = false;
            foreach ($settings['categories'] as $maybe) {
                if (!is_array($maybe)) {
                    $needs_category_migration = true;
                    break;
                }
            }

            if ($needs_category_migration) {
                $seq = 1;
                $name_to_id = [];
                $new_categories = [];

                foreach ($settings['categories'] as $maybe) {
                    $name = is_array($maybe) ? ($maybe['name'] ?? '') : (string) $maybe;
                    $name = trim($name);
                    if ($name === '') {
                        continue;
                    }
                    $key = function_exists('mb_strtolower') ? mb_strtolower($name) : strtolower($name);
                    if (isset($name_to_id[$key])) {
                        continue;
                    }
                    $id = $seq++;
                    $name_to_id[$key] = $id;
                    $new_categories[] = ['id' => $id, 'name' => $name];
                }

                $settings['categories']   = $new_categories;
                $settings['category_seq'] = $seq;

                // Remap each template's category (a name string) to its new ID.
                if (!empty($settings['templates']) && is_array($settings['templates'])) {
                    foreach ($settings['templates'] as &$tpl_ref) {
                        $cv = isset($tpl_ref['category']) ? trim((string) $tpl_ref['category']) : '';
                        if ($cv === '') {
                            $tpl_ref['category'] = 0;
                            continue;
                        }
                        $key = function_exists('mb_strtolower') ? mb_strtolower($cv) : strtolower($cv);
                        $tpl_ref['category'] = $name_to_id[$key] ?? 0;
                    }
                    unset($tpl_ref);
                }

                update_option(self::OPTION_NAME, $settings);
            }
        }

        // Apply defaults for flat settings only
        $flat_defaults = $defaults;
        unset($flat_defaults['templates']);
        $settings = wp_parse_args($settings, $flat_defaults);

        // Ensure templates exist
        if (empty($settings['templates'])) {
            $settings['templates'] = $defaults['templates'];
        }

        return $settings;
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu(): void
    {
        add_options_page(
            __('Thanks Mail for Stripe', 'thanks-mail-for-stripe'),
            __('Thanks Mail for Stripe', 'thanks-mail-for-stripe'),
            'manage_options',
            'thanks-mail-for-stripe',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Plugin action links
     *
     * @param array $links Existing plugin action links.
     * @return array Modified plugin action links.
     */
    public function plugin_action_links(array $links): array
    {
        $settings_url  = esc_url(admin_url('options-general.php?page=thanks-mail-for-stripe'));
        $settings_text = esc_html__('Settings', 'thanks-mail-for-stripe');
        $settings_link = '<a href="' . $settings_url . '">' . $settings_text . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Register settings
     */
    public function register_settings(): void
    {
        register_setting(
            'tmfs_settings_group',
            self::OPTION_NAME,
            [$this, 'sanitize_settings']
        );
    }

    /**
     * Sanitize settings
     */
    public function sanitize_settings(array $input): array
    {
        $sanitized = [];

        // General
        $sanitized['enabled'] = !empty($input['enabled']);
        $sanitized['send_no_payment_required'] = !empty($input['send_no_payment_required']);
        $sanitized['webhook_secret'] = sanitize_text_field($input['webhook_secret'] ?? '');
        $sanitized['brand_name'] = sanitize_text_field($input['brand_name'] ?? '');
        $sanitized['from_email'] = sanitize_email($input['from_email'] ?? '');
        $sanitized['from_name'] = sanitize_text_field($input['from_name'] ?? '');
        $sanitized['support_email'] = sanitize_email($input['support_email'] ?? '');

        // Managed categories: each row carries an ID (existing integer ID, or a
        // temporary "new_*" token for rows added in the browser) and a name.
        // We assign stable integer IDs and build a map from every submitted
        // token to its final ID so templates can be remapped below.
        $existing_settings = get_option(self::OPTION_NAME, []);
        $next_category_id = isset($existing_settings['category_seq'])
            ? max(1, intval($existing_settings['category_seq']))
            : 1;

        $sanitized['categories'] = [];
        $category_id_map = [];   // submitted token (string) => final int ID
        $used_category_ids = [];

        if (!empty($input['categories']) && is_array($input['categories'])) {
            $seen_categories = [];
            foreach ($input['categories'] as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $name = sanitize_text_field($row['name'] ?? '');
                if ($name === '') {
                    continue;
                }
                $submitted_id = isset($row['id']) ? trim((string) $row['id']) : '';

                // A duplicate name merges into the first row with that name, so
                // templates assigned to either keep a category.
                $key = function_exists('mb_strtolower') ? mb_strtolower($name) : strtolower($name);
                if (isset($seen_categories[$key])) {
                    if ($submitted_id !== '' && !isset($category_id_map[$submitted_id])) {
                        $category_id_map[$submitted_id] = $seen_categories[$key];
                    }
                    continue;
                }
                if ($submitted_id !== '' && ctype_digit($submitted_id) && (int) $submitted_id > 0 && !isset($used_category_ids[(int) $submitted_id])) {
                    $final_id = (int) $submitted_id;
                } else {
                    $final_id = $next_category_id++;
                }
                if ($final_id >= $next_category_id) {
                    $next_category_id = $final_id + 1;
                }

                $used_category_ids[$final_id] = true;
                $seen_categories[$key] = $final_id;
                if ($submitted_id !== '') {
                    $category_id_map[$submitted_id] = $final_id;
                }
                $category_id_map[(string) $final_id] = $final_id;

                $sanitized['categories'][] = ['id' => $final_id, 'name' => $name];
            }
        }

        $sanitized['category_seq'] = $next_category_id;

        // Templates
        $sanitized['templates'] = [];
        $valid_locales = ['ja', 'en', ''];

        if (!empty($input['templates']) && is_array($input['templates'])) {
            foreach ($input['templates'] as $tmpl) {
                if (!is_array($tmpl)) {
                    continue;
                }

                $label   = sanitize_text_field($tmpl['label'] ?? '');
                $link    = sanitize_text_field($tmpl['payment_link'] ?? '');
                $subject = sanitize_text_field($tmpl['subject'] ?? '');
                $body    = sanitize_textarea_field($tmpl['body'] ?? '');

                // Skip completely empty templates
                if ('' === $label && '' === $link && '' === $subject && '' === $body) {
                    continue;
                }

                $locale = sanitize_text_field($tmpl['locale'] ?? '');
                if (!in_array($locale, $valid_locales, true)) {
                    $locale = '';
                }

                $raw_category = isset($tmpl['category']) ? trim((string) $tmpl['category']) : '';
                $category = ($raw_category !== '' && isset($category_id_map[$raw_category]))
                    ? (int) $category_id_map[$raw_category]
                    : 0;

                $sanitized['templates'][] = [
                    'label'        => $label,
                    'category'     => $category,
                    'locale'       => $locale,
                    'payment_link' => $link,
                    'subject'      => $subject,
                    'body'         => $body,
                ];
            }
        }

        // Ensure at least one template
        if (empty($sanitized['templates'])) {
            $defaults = $this->get_default_templates();
            $sanitized['templates'] = [$defaults[0]];
        }

        // Enforce max templates
        $sanitized['templates'] = array_slice($sanitized['templates'], 0, self::MAX_TEMPLATES);
        $sanitized['templates'] = array_values($sanitized['templates']);

        return $sanitized;
    }

    /**
     * Admin scripts
     */
    public function admin_scripts(string $hook): void
    {
        if ($hook !== 'settings_page_thanks-mail-for-stripe') {
            return;
        }

        wp_enqueue_style(
            'tmfs-admin',
            TMFS_PLUGIN_URL . 'assets/css/admin.css',
            [],
            TMFS_VERSION
        );

        wp_enqueue_script(
            'tmfs-admin',
            TMFS_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            TMFS_VERSION,
            true
        );

        wp_localize_script('tmfs-admin', 'tmfsAdmin', [
            'nonce'            => wp_create_nonce('wp_rest'),
            'testUrl'          => rest_url(self::REST_NAMESPACE . '/test'),
            'resetUrl'         => rest_url(self::REST_NAMESPACE . '/reset'),
            'maxTemplates'     => self::MAX_TEMPLATES,
            'defaultTemplates' => $this->get_default_templates(),
            'i18n'             => [
                'copied'               => __('Copied!', 'thanks-mail-for-stripe'),
                'show'                 => __('Show', 'thanks-mail-for-stripe'),
                'hide'                 => __('Hide', 'thanks-mail-for-stripe'),
                'confirmReset'         => __('Reset all settings to their defaults? This also clears the Webhook Signing Secret, so Stripe deliveries will fail until you enter it again. The sent-email log is kept.', 'thanks-mail-for-stripe'),
                'resetDone'            => __('Settings have been reset.', 'thanks-mail-for-stripe'),
                'enterEmail'           => __('Please enter an email address', 'thanks-mail-for-stripe'),
                'sending'              => __('Sending...', 'thanks-mail-for-stripe'),
                'testSent'             => __('Test email sent!', 'thanks-mail-for-stripe'),
                'sendFailed'           => __('Failed to send email', 'thanks-mail-for-stripe'),
                'error'                => __('Error occurred', 'thanks-mail-for-stripe'),
                'confirmDelete'        => __('Are you sure you want to delete this template?', 'thanks-mail-for-stripe'),
                'maxReached'           => __('Maximum number of templates reached.', 'thanks-mail-for-stripe'),
                'templateLabel'        => __('Template', 'thanks-mail-for-stripe'),
                'cannotDeleteAll'      => __('At least one template is required.', 'thanks-mail-for-stripe'),
                'confirmResetTemplate' => __('Replace this template\'s subject and body with the default text for its locale? Name, category, locale and Payment Link ID are kept.', 'thanks-mail-for-stripe'),
                'prev'                 => __('Previous', 'thanks-mail-for-stripe'),
                'next'                 => __('Next', 'thanks-mail-for-stripe'),
                'noResults'            => __('No templates match your search.', 'thanks-mail-for-stripe'),
                /* translators: %d: number of templates */
                'templateCountOne'     => _n('%d template', '%d templates', 1, 'thanks-mail-for-stripe'),
                /* translators: %d: number of templates */
                'templateCountMany'    => _n('%d template', '%d templates', 2, 'thanks-mail-for-stripe'),
                /* translators: 1: number of matching templates, 2: total number of templates */
                'templateCountFiltered' => __('%1$d of %2$d templates', 'thanks-mail-for-stripe'),
                'allCategories'        => __('All categories', 'thanks-mail-for-stripe'),
                'uncategorized'        => __('Uncategorized', 'thanks-mail-for-stripe'),
                'noCategory'           => __('— No category —', 'thanks-mail-for-stripe'),
                'unnamedCategory'      => __('(unnamed category)', 'thanks-mail-for-stripe'),
                /* translators: %s: name of the template being duplicated */
                'copyName'             => __('%s (copy)', 'thanks-mail-for-stripe'),
            ],
        ]);
    }

    /**
     * Render settings page
     */
    public function render_settings_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = $this->get_settings();
        $tmfs_templates = $settings['templates'];
        $webhook_url = rest_url(self::REST_NAMESPACE . '/webhook');

        include TMFS_PLUGIN_DIR . 'templates/admin-settings.php';
    }

    /**
     * Register REST routes
     */
    public function register_rest_routes(): void
    {
        register_rest_route(self::REST_NAMESPACE, '/webhook', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_webhook'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::REST_NAMESPACE, '/test', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_test'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
            'args' => [
                'email' => [
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_email',
                    'validate_callback' => function ( $value ) {
                        return is_email( $value );
                    },
                ],
                'lang' => [
                    'default'           => '0',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                // The template as currently on screen, so unsaved edits and
                // newly added templates can be tested before saving.
                'subject' => [
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'body' => [
                    'sanitize_callback' => 'sanitize_textarea_field',
                ],
            ],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/reset', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_reset'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);
    }

    /**
     * Handle Stripe webhook
     */
    public function handle_webhook(\WP_REST_Request $request): \WP_REST_Response
    {
        $settings = $this->get_settings();

        // Check if enabled
        if (empty($settings['enabled'])) {
            return new \WP_REST_Response(['error' => 'Plugin disabled'], 503);
        }

        // Check webhook secret
        $secret = $settings['webhook_secret'] ?? '';
        if (empty($secret)) {
            return new \WP_REST_Response(['error' => 'Webhook secret not configured'], 500);
        }

        $payload = $request->get_body();
        $sig_header = $request->get_header('stripe-signature');

        // Verify signature. Only failures count toward the rate limit, so
        // genuine Stripe deliveries are never throttled -- not during a burst
        // of sales, not when every event type is subscribed, and not behind a
        // proxy where all requests share one REMOTE_ADDR.
        if (!$this->verify_stripe_signature($payload, $sig_header, $secret)) {
            if ($this->record_signature_failure()) {
                return new \WP_REST_Response(['error' => 'Rate limit exceeded'], 429);
            }
            return new \WP_REST_Response(['error' => 'Invalid signature'], 400);
        }

        $event = json_decode($payload, true);
        if (!is_array($event)) {
            return new \WP_REST_Response(['error' => 'Invalid JSON'], 400);
        }

        $type = $event['type'] ?? '';

        // Only handle relevant events
        $valid_events = [
            'checkout.session.completed',
            'checkout.session.async_payment_succeeded',
        ];

        if (!in_array($type, $valid_events, true)) {
            return new \WP_REST_Response(['ok' => true, 'ignored' => $type], 200);
        }

        // A signed event that lacks what we need will look the same on every
        // retry, so answer 200 rather than have Stripe resend it for 3 days.
        $session = $event['data']['object'] ?? null;
        if (!is_array($session)) {
            return new \WP_REST_Response(['ok' => true, 'ignored' => 'missing_session_data'], 200);
        }

        // Extract and sanitize data
        $session_id = isset($session['id']) ? sanitize_text_field($session['id']) : '';
        $email = isset($session['customer_details']['email']) ? sanitize_email($session['customer_details']['email']) : '';
        $payment_status = isset($session['payment_status']) ? sanitize_key($session['payment_status']) : '';

        if (empty($session_id) || empty($email)) {
            return new \WP_REST_Response(['ok' => true, 'ignored' => 'missing_session_id_or_email'], 200);
        }

        // Send once the purchase is settled. 'unpaid' is an async method such
        // as konbini still pending; checkout.session.async_payment_succeeded
        // follows when it clears. 'no_payment_required' (100% discount codes,
        // free trials) is sent only when the site opts in.
        if ($payment_status === 'no_payment_required' && empty($settings['send_no_payment_required'])) {
            return new \WP_REST_Response(['ok' => true, 'status' => 'no_payment_required_skipped'], 200);
        }
        if (!in_array($payment_status, ['paid', 'no_payment_required'], true)) {
            return new \WP_REST_Response(['ok' => true, 'status' => 'not_paid'], 200);
        }

        // Detect language
        $lang = $this->detect_language($session, $settings);

        $amount = '';
        if (isset($session['amount_total'], $session['currency']) && is_numeric($session['amount_total'])) {
            $amount = $this->format_amount(absint($session['amount_total']), (string) $session['currency']);
        }

        // Claim the session before sending. The UNIQUE key on session_id makes
        // this atomic, so two deliveries of the same event arriving together
        // cannot both send.
        $claimed = $this->claim_session($session_id, $email, $lang, $amount);
        if ($claimed === null) {
            // Table missing or unwritable: refuse rather than send without a
            // duplicate guard. A non-2xx lets Stripe retry once it is fixed.
            return new \WP_REST_Response(['error' => 'Could not record session'], 500);
        }
        if ($claimed === false) {
            return new \WP_REST_Response(['ok' => true, 'already_sent' => true], 200);
        }

        $sent = $this->send_thanks_email($email, $lang, $session_id, $settings);

        // Release the claim on failure so a manual redelivery from Stripe can retry.
        if (!$sent) {
            $this->release_session($session_id);
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- intentional debug logging
                error_log( '[Thanks Mail for Stripe] Failed to send email for session: ' . $session_id );
            }
        }

        // Return response without PII (no email in response)
        return new \WP_REST_Response([
            'ok' => true,
            'sent' => $sent,
            'lang' => $lang,
        ], 200);
    }

    /**
     * Handle test email
     */
    public function handle_test(\WP_REST_Request $request): \WP_REST_Response
    {
        // Already sanitized and validated by the route's args schema.
        $email = $request->get_param('email');
        $lang = (string) $request->get_param('lang');

        $settings = $this->get_settings();
        $session_id = 'TEST_' . wp_generate_uuid4();

        $template = null;
        if ($request->has_param('subject') || $request->has_param('body')) {
            $template = [
                'subject' => (string) $request->get_param('subject'),
                'body'    => (string) $request->get_param('body'),
            ];
        }

        $sent = $this->send_thanks_email($email, $lang, $session_id, $settings, $template);

        return new \WP_REST_Response([
            'ok' => true,
            'sent' => $sent,
            'lang' => $lang,
        ], 200);
    }

    /**
     * Handle settings reset
     */
    public function handle_reset(\WP_REST_Request $request): \WP_REST_Response
    {
        update_option(self::OPTION_NAME, $this->get_default_settings());

        return new \WP_REST_Response([
            'ok' => true,
        ], 200);
    }

    /**
     * Verify Stripe signature
     */
    private function verify_stripe_signature(string $payload, ?string $sig_header, string $secret, int $tolerance = 300): bool
    {
        if (empty($sig_header)) {
            return false;
        }

        $timestamp = 0;
        $signatures = [];

        foreach (explode(',', $sig_header) as $part) {
            $part = trim($part);
            [$key, $value] = array_pad(explode('=', $part, 2), 2, '');
            if ($key === 't') {
                $timestamp = (int) $value;
            }
            if ($key === 'v1') {
                $signatures[] = $value;
            }
        }

        if ($timestamp <= 0 || empty($signatures)) {
            return false;
        }

        // Check timestamp tolerance
        if (abs(time() - $timestamp) > $tolerance) {
            return false;
        }

        // Verify signature
        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
        foreach ($signatures as $sig) {
            if (hash_equals($expected, $sig)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect language from session
     *
     * Returns a template index (as string) instead of language code.
     *
     * @param array $session Stripe session data.
     * @param array $settings Plugin settings.
     * @return string Template index.
     */
    private function detect_language(array $session, array $settings): string
    {
        $templates = $settings['templates'] ?? [];
        $payment_link = $session['payment_link'] ?? '';

        // 1. Check Payment Link ID match (priority)
        if (!empty($payment_link)) {
            foreach ($templates as $i => $tmpl) {
                if (!empty($tmpl['payment_link']) && $tmpl['payment_link'] === $payment_link) {
                    $lang = (string) $i;
                    break;
                }
            }
        }

        // 2. Check locale fallback
        if (!isset($lang)) {
            $session_locale = strtolower($session['locale'] ?? '');
            $locale_prefix = (strpos($session_locale, 'ja') === 0) ? 'ja' : 'en';

            foreach ($templates as $i => $tmpl) {
                if (!empty($tmpl['locale']) && $tmpl['locale'] === $locale_prefix) {
                    $lang = (string) $i;
                    break;
                }
            }
        }

        // 3. Final fallback: first template
        if (!isset($lang)) {
            $lang = '0';
        }

        /**
         * Filter the detected language.
         *
         * @since 1.0.0
         * @param string $lang    Template index (string).
         * @param array  $session Stripe session data.
         */
        return apply_filters('tmfs_detect_language', $lang, $session);
    }

    /**
     * Count a failed signature check against the client IP.
     *
     * @return bool True when the IP has exceeded the limit and should get 429.
     */
    private function record_signature_failure(): bool
    {
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated by FILTER_VALIDATE_IP
        $client_ip = isset( $_SERVER['REMOTE_ADDR'] )
            ? filter_var( wp_unslash( $_SERVER['REMOTE_ADDR'] ), FILTER_VALIDATE_IP )
            : '';
        if (empty($client_ip)) {
            return false;
        }

        $transient_key = 'tmfs_rate_' . md5($client_ip);
        $count = (int) get_transient($transient_key) + 1;
        set_transient($transient_key, $count, 60);

        return $count > self::MAX_SIGNATURE_FAILURES;
    }

    /**
     * Format a Stripe amount (smallest currency unit) for the log.
     *
     * Stripe sends zero-decimal currencies such as JPY in whole units and a
     * few currencies in thousandths, so dividing everything by 100 is wrong.
     *
     * @param int    $amount   Amount in the currency's smallest unit.
     * @param string $currency ISO currency code.
     * @return string e.g. "JPY 1,000" or "USD 12.50".
     */
    private function format_amount(int $amount, string $currency): string
    {
        $currency = strtoupper(sanitize_key($currency));

        // https://docs.stripe.com/currencies#zero-decimal
        $zero_decimal  = ['BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'];
        $three_decimal = ['BHD', 'JOD', 'KWD', 'OMR', 'TND'];

        if (in_array($currency, $zero_decimal, true)) {
            $decimals = 0;
        } elseif (in_array($currency, $three_decimal, true)) {
            $decimals = 3;
        } else {
            $decimals = 2;
        }

        return $currency . ' ' . number_format($amount / (10 ** $decimals), $decimals);
    }

    /**
     * Atomically claim a session before sending its email.
     *
     * @return bool|null True if claimed, false if already claimed (duplicate),
     *                   null if the row could not be written at all.
     */
    private function claim_session(string $session_id, string $email, string $lang, string $amount): ?bool
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_NAME;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table name from $wpdb->prefix; INSERT IGNORE is the duplicate guard
        $result = $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$table} (session_id, email, lang, amount, sent_at) VALUES (%s, %s, %s, %s, %s)",
            $session_id,
            $email,
            $lang,
            $amount,
            current_time('mysql')
        ));

        if ($result === false) {
            return null;
        }

        return $result === 1;
    }

    /**
     * Drop a claim whose email failed to send.
     */
    private function release_session(string $session_id): void
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_NAME;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table delete
        $wpdb->delete($table, ['session_id' => $session_id], ['%s']);
    }

    /**
     * Send thanks email
     *
     * @param string $to         Recipient email address.
     * @param string $lang       Template index (string).
     * @param string $session_id Stripe session ID.
     * @param array  $settings   Plugin settings.
     * @param array|null $template Subject/body to use instead of the saved template at $lang.
     * @return bool Whether the email was sent successfully.
     */
    private function send_thanks_email(string $to, string $lang, string $session_id, array $settings, ?array $template = null): bool
    {
        if (! is_email($to)) {
            return false;
        }

        $template   = $template ?? $this->get_template($settings, $lang);
        $brand      = $settings['brand_name'] ?: get_bloginfo('name');
        $from_email = $settings['from_email'] ?: get_option('admin_email');
        $from_name  = $settings['from_name'] ?: get_bloginfo('name');
        $reply_to   = $settings['support_email'] ?: '';

        $subject = $template['subject'] ?: '';
        $body    = $template['body'] ?: '';

        // Replace placeholders
        $replacements = array(
            '{brand}'      => $brand,
            '{session_id}' => $session_id,
            '{email}'      => $to,
        );

        $subject = str_replace(array_keys($replacements), array_values($replacements), $subject);
        $body    = str_replace(array_keys($replacements), array_values($replacements), $body);

        /**
         * Filter the email subject.
         *
         * @since 1.0.0
         * @param string $subject    Email subject.
         * @param string $to         Recipient email address.
         * @param string $lang       Template index (string).
         * @param string $session_id Stripe session ID.
         */
        $subject = apply_filters('tmfs_email_subject', $subject, $to, $lang, $session_id);

        /**
         * Filter the email body.
         *
         * @since 1.0.0
         * @param string $body       Email body.
         * @param string $to         Recipient email address.
         * @param string $lang       Template index (string).
         * @param string $session_id Stripe session ID.
         */
        $body = apply_filters('tmfs_email_body', $body, $to, $lang, $session_id);

        // Headers
        $headers   = array('Content-Type: text/plain; charset=UTF-8');
        $headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';
        if (! empty($reply_to)) {
            $headers[] = 'Reply-To: ' . $reply_to;
        }

        /**
         * Filter the email headers.
         *
         * @since 1.0.0
         * @param array  $headers    Email headers.
         * @param string $to         Recipient email address.
         * @param string $lang       Template index (string).
         * @param string $session_id Stripe session ID.
         */
        $headers = apply_filters('tmfs_email_headers', $headers, $to, $lang, $session_id);

        return wp_mail($to, $subject, $body, $headers);
    }

    /**
     * Get sent emails log
     */
    public function get_sent_emails(int $limit = 50): array
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_NAME;

        // id, not sent_at: sent_at is local time and can step backwards at a DST change.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table name from $wpdb->prefix; admin display only
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d",
            $limit
        ), ARRAY_A);
    }

    /**
     * Get sent emails count
     */
    public function get_sent_emails_count(): int
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_NAME;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table name from $wpdb->prefix
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
    }

    /**
     * Register the sent-email log with Tools → Export Personal Data.
     */
    public function register_privacy_exporter(array $exporters): array
    {
        $exporters['thanks-mail-for-stripe'] = [
            'exporter_friendly_name' => __('Thanks Mail for Stripe', 'thanks-mail-for-stripe'),
            'callback'               => [$this, 'export_personal_data'],
        ];
        return $exporters;
    }

    /**
     * Register the sent-email log with Tools → Erase Personal Data.
     */
    public function register_privacy_eraser(array $erasers): array
    {
        $erasers['thanks-mail-for-stripe'] = [
            'eraser_friendly_name' => __('Thanks Mail for Stripe', 'thanks-mail-for-stripe'),
            'callback'             => [$this, 'erase_personal_data'],
        ];
        return $erasers;
    }

    /**
     * Export the log rows for one email address.
     *
     * @param string $email_address Address being exported.
     * @return array Exporter response.
     */
    public function export_personal_data(string $email_address): array
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_NAME;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table name from $wpdb->prefix
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, session_id, email, amount, sent_at FROM {$table} WHERE email = %s ORDER BY id",
            $email_address
        ), ARRAY_A);

        $items = [];
        foreach ((array) $rows as $row) {
            $items[] = [
                'group_id'    => 'thanks-mail-for-stripe',
                'group_label' => __('Thank-you emails sent', 'thanks-mail-for-stripe'),
                'item_id'     => 'tmfs-sent-' . $row['id'],
                'data'        => [
                    ['name' => __('Email', 'thanks-mail-for-stripe'), 'value' => $row['email']],
                    ['name' => __('Session ID', 'thanks-mail-for-stripe'), 'value' => $row['session_id']],
                    ['name' => __('Amount', 'thanks-mail-for-stripe'), 'value' => $row['amount']],
                    ['name' => __('Date', 'thanks-mail-for-stripe'), 'value' => $row['sent_at']],
                ],
            ];
        }

        return ['data' => $items, 'done' => true];
    }

    /**
     * Erase the log rows for one email address.
     *
     * Erasing also removes the duplicate guard for those sessions, so a
     * manual redelivery from Stripe would send again.
     *
     * @param string $email_address Address being erased.
     * @return array Eraser response.
     */
    public function erase_personal_data(string $email_address): array
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_NAME;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table delete
        $deleted = $wpdb->delete($table, ['email' => $email_address], ['%s']);

        return [
            'items_removed'  => (bool) $deleted,
            'items_retained' => false,
            'messages'       => [],
            'done'           => true,
        ];
    }
}

// Initialize plugin
TMFS_Thanks_Mail::get_instance();
