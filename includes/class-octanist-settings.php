<?php
if (!defined('ABSPATH')) {
    exit;
}

class Octanist_Settings
{
    const OPTION       = 'octanist_settings';
    const GROUP        = 'octanist_settings_group';
    const PAGE_SLUG    = 'octanist';
    const HOOK_SUFFIX  = 'settings_page_octanist';
    const NOTICE_FLAG  = 'octanist_show_v3_notice';

    public static function register(): void
    {
        add_action('admin_menu', [__CLASS__, 'add_menu']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('admin_notices', [__CLASS__, 'maybe_render_upgrade_notice']);
        add_action('admin_post_octanist_dismiss_notice', [__CLASS__, 'dismiss_notice']);
    }

    public static function get(): array
    {
        $defaults = [
            'measurement_id' => '',
            'listener_mode'  => 'server',
            'consent_mode'   => 'auto',
        ];
        $settings = get_option(self::OPTION, []);
        if (!is_array($settings)) {
            $settings = [];
        }
        return array_merge($defaults, $settings);
    }

    public static function is_configured(): bool
    {
        $s = self::get();
        return !empty($s['measurement_id']);
    }

    /**
     * Data-* attributes the pixel <script> tag should carry.
     * Single source of truth for how settings map to pixel behavior.
     */
    public static function pixel_data_attrs(): array
    {
        $s = self::get();
        $attrs = [
            'data-id'           => $s['measurement_id'],
            'data-consent-mode' => $s['consent_mode'],
            'data-cookie-mode'  => 'server',
        ];
        if ($s['listener_mode'] === 'client') {
            // Value-less attribute, browser pixel binds to forms itself.
            $attrs['data-forms'] = '';
        }
        return $attrs;
    }

    /**
     * Whether server-side form-capture hooks should be registered.
     * False when the pixel is doing form capture in the browser (would duplicate).
     */
    public static function server_hooks_enabled(): bool
    {
        if (!self::is_configured()) {
            return false;
        }
        $s = self::get();
        return ($s['listener_mode'] ?? 'server') === 'server';
    }

    public static function add_menu(): void
    {
        add_options_page(
            __('Octanist', 'octanist'),
            __('Octanist', 'octanist'),
            'manage_options',
            self::PAGE_SLUG,
            [__CLASS__, 'render_page']
        );
    }

    public static function register_settings(): void
    {
        register_setting(self::GROUP, self::OPTION, [
            'type'              => 'array',
            'sanitize_callback' => [__CLASS__, 'sanitize'],
            'default'           => [
                'measurement_id' => '',
                'listener_mode'  => 'server',
                'consent_mode'   => 'auto',
            ],
        ]);
    }

    public static function sanitize($input): array
    {
        if (!is_array($input)) {
            $input = [];
        }

        if (!empty($input['setup_code'])) {
            $decoded = self::decode_setup_code((string) $input['setup_code']);

            if (is_wp_error($decoded)) {
                add_settings_error(
                    self::OPTION,
                    'octanist_setup_code_invalid',
                    $decoded->get_error_message(),
                    'error'
                );
            } else {
                $input['measurement_id'] = $decoded['measurement_id'];
                $input['listener_mode']  = $decoded['listener_mode'];
                $input['consent_mode']   = $decoded['consent_mode'];

                add_settings_error(
                    self::OPTION,
                    'octanist_setup_code_applied',
                    __('Octanist setup code applied.', 'octanist'),
                    'success'
                );
            }
        }

        $mid = isset($input['measurement_id']) ? sanitize_text_field($input['measurement_id']) : '';
        $mid = trim($mid);

        $listener = isset($input['listener_mode']) ? sanitize_key($input['listener_mode']) : 'server';
        if (!in_array($listener, ['server', 'client'], true)) {
            $listener = 'server';
        }

        $consent = isset($input['consent_mode']) ? sanitize_key($input['consent_mode']) : 'auto';
        if (!in_array($consent, ['auto', 'granted', 'denied'], true)) {
            $consent = 'auto';
        }

        if ($mid === '') {
            add_settings_error(self::OPTION, 'octanist_mid_empty', __('Measurement ID is required for the pixel to load.', 'octanist'), 'warning');
        } elseif (class_exists('Octanist_Queue')) {
            Octanist_Queue::schedule_pixel_refresh();
        }

        return [
            'measurement_id' => $mid,
            'listener_mode'  => $listener,
            'consent_mode'   => $consent,
        ];
    }

    private static function decode_setup_code(string $code)
    {
        $code = trim(wp_unslash($code));
        $code = preg_replace('/\s+/', '', $code);

        $prefix = 'OCTA1.';
        if (strpos($code, $prefix) !== 0) {
            return new WP_Error(
                'octanist_setup_code_prefix',
                __('Setup code must start with OCTA1.', 'octanist')
            );
        }

        $parts = explode('.', $code);
        if (count($parts) !== 4) {
            return new WP_Error(
                'octanist_setup_code_shape',
                __('Setup code should look like OCTA1.OCT-XXXXXXXX.s.a.', 'octanist')
            );
        }

        $measurement_id = sanitize_text_field((string) $parts[1]);
        $listener_code  = sanitize_key((string) $parts[2]);
        $consent_code   = sanitize_key((string) $parts[3]);

        if (!preg_match('/^OCT-[A-Z0-9]{8}$/', $measurement_id)) {
            return new WP_Error(
                'octanist_setup_code_measurement',
                __('Setup code contains an invalid measurement ID.', 'octanist')
            );
        }

        $listener_modes = [
            's' => 'server',
            'c' => 'client',
        ];
        $consent_modes = [
            'a' => 'auto',
            'g' => 'granted',
            'd' => 'denied',
        ];

        $listener_mode = $listener_modes[$listener_code] ?? '';
        $consent_mode = $consent_modes[$consent_code] ?? '';

        if (!in_array($listener_mode, ['server', 'client'], true)) {
            return new WP_Error(
                'octanist_setup_code_listener',
                __('Setup code contains an invalid form capture mode.', 'octanist')
            );
        }

        if (!in_array($consent_mode, ['auto', 'granted', 'denied'], true)) {
            return new WP_Error(
                'octanist_setup_code_consent',
                __('Setup code contains an invalid consent mode.', 'octanist')
            );
        }

        return [
            'measurement_id' => $measurement_id,
            'listener_mode'  => $listener_mode,
            'consent_mode'   => $consent_mode,
        ];
    }

    public static function enqueue_assets($hook): void
    {
        if ($hook !== self::HOOK_SUFFIX) {
            return;
        }
        wp_enqueue_style(
            'octanist-admin',
            OCTANIST_URL . 'assets/css/admin.css',
            [],
            OCTANIST_VERSION
        );
        wp_enqueue_script(
            'octanist-admin',
            OCTANIST_URL . 'assets/js/admin.js',
            [],
            OCTANIST_VERSION,
            true
        );
    }

    public static function maybe_render_upgrade_notice(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        if (get_option(self::NOTICE_FLAG) !== '1') {
            return;
        }
        $settings_url = admin_url('options-general.php?page=' . self::PAGE_SLUG);
        $dismiss_url  = wp_nonce_url(
            admin_url('admin-post.php?action=octanist_dismiss_notice'),
            'octanist_dismiss_notice'
        );
        ?>
        <div class="notice notice-info is-dismissible">
            <p>
                <strong><?php esc_html_e('Octanist has been updated to 4.0.', 'octanist'); ?></strong>
                <?php esc_html_e('Field mappings, debug mode, and the datalayer option have been removed. Configuration is now a single measurement ID.', 'octanist'); ?>
            </p>
            <p>
                <a class="button button-primary" href="<?php echo esc_url($settings_url); ?>"><?php esc_html_e('Open settings', 'octanist'); ?></a>
                <a class="button" href="<?php echo esc_url($dismiss_url); ?>"><?php esc_html_e('Dismiss', 'octanist'); ?></a>
            </p>
        </div>
        <?php
    }

    public static function dismiss_notice(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden', 403);
        }
        check_admin_referer('octanist_dismiss_notice');
        delete_option(self::NOTICE_FLAG);
        wp_safe_redirect(wp_get_referer() ?: admin_url());
        exit;
    }

    public static function render_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden', 403);
        }

        $settings   = self::get();
        $health     = Octanist_Health::get();
        $queue_size = class_exists('Octanist_Queue') ? Octanist_Queue::count() : 0;
        $plugins    = class_exists('Octanist_Form_Capture') ? Octanist_Form_Capture::detected_plugins() : [];
        $configured = self::is_configured();
        ?>
        <div class="wrap octanist-wrap" id="octanist-settings-page">
            <header class="octanist-header">
                <div class="octanist-header__brand">
                    <img class="octanist-header__logo" src="<?php echo esc_url(OCTANIST_URL . 'assets/icon.svg'); ?>" alt="">
                    <div class="octanist-header__text">
                        <h1 class="octanist-header__title"><?php esc_html_e('Octanist', 'octanist'); ?></h1>
                        <p class="octanist-header__tagline"><?php esc_html_e('First-party pixel proxy and server-side form capture.', 'octanist'); ?></p>
                    </div>
                </div>
                <span class="octanist-status <?php echo $configured ? 'is-ok' : 'is-warn'; ?>">
                    <span class="octanist-status__dot" aria-hidden="true"></span>
                    <?php echo $configured ? esc_html__('Connected', 'octanist') : esc_html__('Not configured', 'octanist'); ?>
                </span>
            </header>

            <hr class="wp-header-end">

            <form method="POST" action="options.php" class="octanist-form">
                <?php settings_fields(self::GROUP); ?>

                <?php if (!$configured) : ?>
                    <section class="octanist-card">
                        <header class="octanist-card__head">
                            <h2 class="octanist-card__title"><?php esc_html_e('Connect Octanist', 'octanist'); ?></h2>
                            <p class="octanist-card__subtitle"><?php esc_html_e('Paste the setup code from Octanist onboarding to apply your measurement ID, form capture mode, and consent mode.', 'octanist'); ?></p>
                        </header>
                        <div class="octanist-card__body">
                            <input
                                type="text"
                                id="octanist_setup_code"
                                class="octanist-input octanist-input--wide"
                                name="<?php echo esc_attr(self::OPTION); ?>[setup_code]"
                                placeholder="OCTA1.OCT-XXXXXXXX.s.a"
                                autocomplete="off"
                                spellcheck="false"
                                aria-label="<?php esc_attr_e('Setup code', 'octanist'); ?>">
                            <p class="octanist-help"><?php esc_html_e('The code is decoded inside WordPress. No request is sent to Octanist when you save it.', 'octanist'); ?></p>
                        </div>
                    </section>
                <?php else : ?>
                    <section class="octanist-card">
                        <header class="octanist-card__head">
                            <h2 class="octanist-card__title"><?php esc_html_e('Current settings', 'octanist'); ?></h2>
                            <p class="octanist-card__subtitle"><?php esc_html_e('Octanist is connected. You can leave these settings as they are, change them manually, or paste a new setup code.', 'octanist'); ?></p>
                        </header>
                        <div class="octanist-card__body">
                            <div class="octanist-connected-panel">
                                <div class="octanist-connected-panel__head">
                                    <div class="octanist-connected-panel__mark" aria-hidden="true">
                                        <span></span>
                                    </div>
                                    <div class="octanist-connected-panel__copy">
                                        <h3><?php esc_html_e('Pixel configuration is active', 'octanist'); ?></h3>
                                        <p><?php esc_html_e('These are the values currently used by the Octanist pixel on this WordPress site.', 'octanist'); ?></p>
                                    </div>
                                    <span class="octanist-badge octanist-badge--success"><?php esc_html_e('Connected', 'octanist'); ?></span>
                                </div>

                                <dl class="octanist-summary-grid">
                                    <div class="octanist-summary-tile">
                                        <dt><?php esc_html_e('Measurement ID', 'octanist'); ?></dt>
                                        <dd><code><?php echo esc_html($settings['measurement_id']); ?></code></dd>
                                        <span><?php esc_html_e('Identifies this property in Octanist.', 'octanist'); ?></span>
                                    </div>
                                    <div class="octanist-summary-tile">
                                        <dt><?php esc_html_e('Form capture', 'octanist'); ?></dt>
                                        <dd><?php echo esc_html(self::format_listener_mode($settings['listener_mode'])); ?></dd>
                                        <span><?php echo esc_html(self::format_listener_mode_help($settings['listener_mode'])); ?></span>
                                    </div>
                                    <div class="octanist-summary-tile">
                                        <dt><?php esc_html_e('Consent mode', 'octanist'); ?></dt>
                                        <dd><?php echo esc_html(self::format_consent_mode($settings['consent_mode'])); ?></dd>
                                        <span><?php echo esc_html(self::format_consent_mode_help($settings['consent_mode'])); ?></span>
                                    </div>
                                </dl>
                            </div>

                            <div class="octanist-settings-edit">
                                <details class="octanist-disclosure">
                                    <summary><?php esc_html_e('Change settings manually', 'octanist'); ?></summary>
                                    <div class="octanist-disclosure__body">
                                        <label class="octanist-field" for="octanist_measurement_id">
                                            <span class="octanist-field__label"><?php esc_html_e('Measurement ID', 'octanist'); ?></span>
                                            <input
                                                type="text"
                                                id="octanist_measurement_id"
                                                class="octanist-input"
                                                name="<?php echo esc_attr(self::OPTION); ?>[measurement_id]"
                                                value="<?php echo esc_attr($settings['measurement_id']); ?>"
                                                placeholder="OCT-XXXXXXXX"
                                                autocomplete="off"
                                                spellcheck="false">
                                        </label>

                                        <div class="octanist-field">
                                            <span class="octanist-field__label"><?php esc_html_e('Form capture', 'octanist'); ?></span>
                                            <div class="octanist-options">
                                                <label class="octanist-option <?php echo $settings['listener_mode'] === 'server' ? 'is-active' : ''; ?>">
                                                    <input type="radio" name="<?php echo esc_attr(self::OPTION); ?>[listener_mode]" value="server" <?php checked($settings['listener_mode'], 'server'); ?>>
                                                    <span class="octanist-option__content">
                                                        <span class="octanist-option__title">
                                                            <?php esc_html_e('Server-side', 'octanist'); ?>
                                                            <span class="octanist-badge"><?php esc_html_e('Recommended', 'octanist'); ?></span>
                                                        </span>
                                                        <span class="octanist-option__desc"><?php esc_html_e('Hooks into major WordPress form plugins including Gravity Forms, Contact Form 7, WPForms, Ninja Forms, Elementor Pro, Fluent Forms, Formidable Forms, Forminator, SureForms, and Divi Contact Form.', 'octanist'); ?></span>
                                                    </span>
                                                </label>
                                                <label class="octanist-option <?php echo $settings['listener_mode'] === 'client' ? 'is-active' : ''; ?>">
                                                    <input type="radio" name="<?php echo esc_attr(self::OPTION); ?>[listener_mode]" value="client" <?php checked($settings['listener_mode'], 'client'); ?>>
                                                    <span class="octanist-option__content">
                                                        <span class="octanist-option__title"><?php esc_html_e('Client-side', 'octanist'); ?></span>
                                                        <span class="octanist-option__desc"><?php esc_html_e('Lets the pixel bind to forms in the browser. Use only if your form plugin isn\'t supported server-side.', 'octanist'); ?></span>
                                                    </span>
                                                </label>
                                            </div>
                                        </div>

                                        <label class="octanist-field" for="octanist_consent_mode">
                                            <span class="octanist-field__label"><?php esc_html_e('Consent mode', 'octanist'); ?></span>
                                            <select class="octanist-input" name="<?php echo esc_attr(self::OPTION); ?>[consent_mode]" id="octanist_consent_mode">
                                                <option value="auto" <?php selected($settings['consent_mode'], 'auto'); ?>><?php esc_html_e('Auto, detect from your CMP', 'octanist'); ?></option>
                                                <option value="granted" <?php selected($settings['consent_mode'], 'granted'); ?>><?php esc_html_e('Granted, always on', 'octanist'); ?></option>
                                                <option value="denied" <?php selected($settings['consent_mode'], 'denied'); ?>><?php esc_html_e('Denied, always off', 'octanist'); ?></option>
                                            </select>
                                        </label>
                                    </div>
                                </details>

                                <details class="octanist-disclosure">
                                    <summary><?php esc_html_e('Enter a new setup code', 'octanist'); ?></summary>
                                    <div class="octanist-disclosure__body">
                                        <input
                                            type="text"
                                            id="octanist_setup_code"
                                            class="octanist-input octanist-input--wide"
                                            name="<?php echo esc_attr(self::OPTION); ?>[setup_code]"
                                            placeholder="OCTA1.OCT-XXXXXXXX.s.a"
                                            autocomplete="off"
                                            spellcheck="false"
                                            aria-label="<?php esc_attr_e('Setup code', 'octanist'); ?>">
                                        <p class="octanist-help"><?php esc_html_e('Saving a setup code replaces the current measurement ID, form capture mode, and consent mode.', 'octanist'); ?></p>
                                    </div>
                                </details>
                            </div>
                        </div>
                    </section>
                <?php endif; ?>

                <div class="octanist-actions">
                    <?php submit_button(__('Save changes', 'octanist'), 'primary large', 'submit', false); ?>
                </div>
            </form>

            <section class="octanist-card octanist-card--muted">
                <header class="octanist-card__head">
                    <h2 class="octanist-card__title"><?php esc_html_e('Health', 'octanist'); ?></h2>
                    <p class="octanist-card__subtitle"><?php esc_html_e('Last activity from form captures and proxied events.', 'octanist'); ?></p>
                </header>
                <div class="octanist-card__body">
                    <dl class="octanist-health">
                        <div class="octanist-health__row">
                            <dt><?php esc_html_e('Last success', 'octanist'); ?></dt>
                            <dd><?php echo esc_html(self::format_time($health['last_success_at'] ?? null)); ?></dd>
                        </div>
                        <div class="octanist-health__row">
                            <dt><?php esc_html_e('Last failure', 'octanist'); ?></dt>
                            <dd>
                                <?php echo esc_html(self::format_time($health['last_failure_at'] ?? null)); ?>
                                <?php if (!empty($health['last_error_msg'])) : ?>
                                    <code class="octanist-health__error"><?php echo esc_html($health['last_error_msg']); ?></code>
                                <?php endif; ?>
                            </dd>
                        </div>
                        <div class="octanist-health__row">
                            <dt><?php esc_html_e('Last source', 'octanist'); ?></dt>
                            <dd><?php echo esc_html($health['last_form_source'] ?? '-'); ?></dd>
                        </div>
                        <div class="octanist-health__row">
                            <dt><?php esc_html_e('Queued form retries', 'octanist'); ?></dt>
                            <dd><?php echo esc_html((string) $queue_size); ?></dd>
                        </div>
                        <div class="octanist-health__row">
                            <dt><?php esc_html_e('Last missing session ID', 'octanist'); ?></dt>
                            <dd>
                                <?php echo esc_html(self::format_missing_sid($health)); ?>
                            </dd>
                        </div>
                        <div class="octanist-health__row">
                            <dt><?php esc_html_e('Detected form plugins', 'octanist'); ?></dt>
                            <dd><?php echo esc_html(self::format_detected_plugins($plugins)); ?></dd>
                        </div>
                        <div class="octanist-health__row">
                            <dt><?php esc_html_e('Pixel route', 'octanist'); ?></dt>
                            <dd><code><?php echo esc_html(Octanist_Rest::pixel_url()); ?></code></dd>
                        </div>
                        <div class="octanist-health__row">
                            <dt><?php esc_html_e('Event route', 'octanist'); ?></dt>
                            <dd><code><?php echo esc_html(Octanist_Rest::collect_url()); ?></code></dd>
                        </div>
                    </dl>
                </div>
            </section>
        </div>
        <?php
    }

    private static function format_listener_mode(string $mode): string
    {
        return $mode === 'client'
            ? __('Client-side', 'octanist')
            : __('Server-side', 'octanist');
    }

    private static function format_listener_mode_help(string $mode): string
    {
        return $mode === 'client'
            ? __('The browser pixel listens for form submits.', 'octanist')
            : __('WordPress form hooks send captures server-side.', 'octanist');
    }

    private static function format_consent_mode(string $mode): string
    {
        $labels = [
            'auto'    => __('Auto, detect from your CMP', 'octanist'),
            'granted' => __('Granted, always on', 'octanist'),
            'denied'  => __('Denied, always off', 'octanist'),
        ];

        return $labels[$mode] ?? $labels['auto'];
    }

    private static function format_consent_mode_help(string $mode): string
    {
        $labels = [
            'auto'    => __('Uses detected consent signals when available.', 'octanist'),
            'granted' => __('Runs as granted unless you gate the script yourself.', 'octanist'),
            'denied'  => __('Keeps analytics and marketing storage denied.', 'octanist'),
        ];

        return $labels[$mode] ?? $labels['auto'];
    }

    private static function format_time($ts): string
    {
        if (empty($ts)) {
            return '-';
        }
        return sprintf(
            /* translators: %s: human-readable time diff */
            __('%s ago', 'octanist'),
            human_time_diff((int) $ts, time())
        );
    }

    private static function format_missing_sid(array $health): string
    {
        if (empty($health['last_missing_sid_at'])) {
            return '-';
        }

        $parts = [
            self::format_time($health['last_missing_sid_at']),
        ];

        if (!empty($health['last_missing_sid_source'])) {
            $parts[] = (string) $health['last_missing_sid_source'];
        }
        if (!empty($health['last_missing_sid_form'])) {
            $parts[] = (string) $health['last_missing_sid_form'];
        }
        if (!empty($health['missing_sid_count'])) {
            $parts[] = sprintf(
                /* translators: %d: number of form submissions missing octa_sid */
                _n('%d total miss', '%d total misses', (int) $health['missing_sid_count'], 'octanist'),
                (int) $health['missing_sid_count']
            );
        }

        return implode(' | ', $parts);
    }

    private static function format_detected_plugins(array $plugins): string
    {
        $detected = [];
        foreach ($plugins as $plugin) {
            if (!empty($plugin['detected']) && !empty($plugin['label'])) {
                $detected[] = (string) $plugin['label'];
            }
        }

        return empty($detected) ? '-' : implode(', ', $detected);
    }
}
