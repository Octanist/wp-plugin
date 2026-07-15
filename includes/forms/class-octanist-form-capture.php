<?php
if (!defined('ABSPATH')) {
    exit;
}

class Octanist_Form_Capture
{
    const SID_FIELD_NAME = 'octa_sid';

    public static function register(): void
    {
        add_action('init', [__CLASS__, 'register_handlers'], 20);
    }

    public static function register_handlers(): void
    {
        if (!Octanist_Settings::is_configured()) {
            return;
        }

        // Respect the "client-side" listener mode, the pixel captures forms itself
        // in the browser, so server-side hooks must not fire (would cause duplicates).
        $settings = Octanist_Settings::get();
        if (($settings['listener_mode'] ?? 'server') !== 'server') {
            return;
        }

        if (class_exists('GFForms')) {
            Octanist_Form_Gravity::register();
        }
        if (class_exists('WPCF7')) {
            Octanist_Form_Cf7::register();
        }
        if (function_exists('wpforms')) {
            Octanist_Form_Wpforms::register();
        }
        if (class_exists('Ninja_Forms')) {
            Octanist_Form_Ninja::register();
        }
        if (class_exists('\\ElementorPro\\Modules\\Forms\\Module')) {
            Octanist_Form_Elementor::register();
        }
        if (self::fluent_forms_available()) {
            Octanist_Form_Fluent::register();
        }
        if (self::formidable_forms_available()) {
            Octanist_Form_Formidable::register();
        }
        if (self::forminator_available()) {
            Octanist_Form_Forminator::register();
        }
        if (self::sureforms_available()) {
            Octanist_Form_Sureforms::register();
        }
        if (self::divi_available()) {
            Octanist_Form_Divi::register();
        }
    }

    /**
     * Build the canonical form_submit envelope (matches the browser pixel's tracker).
     */
    public static function build_payload(string $form_identifier, array $fields, ?string $octa_sid): array
    {
        $settings = Octanist_Settings::get();
        $url      = self::current_url();
        $parsed   = $url ? wp_parse_url($url) : [];
        $domain   = $parsed['host'] ?? (parse_url(home_url(), PHP_URL_HOST) ?: '');
        $path     = self::resolve_page_path($parsed['path'] ?? null, $domain);

        $payload = [
            'type'            => 'form_submit',
            'source'          => 'wordpress',
            'mid'             => $settings['measurement_id'],
            'sid'             => $octa_sid ?: null,
            'domain'          => $domain,
            'path'            => $path,
            'title'           => '',
            'referrer'        => $_SERVER['HTTP_REFERER'] ?? '',
            'timestamp'       => (int) round(microtime(true) * 1000),
            'form_identifier' => $form_identifier,
            'form_fields'     => self::sanitize_fields($fields),
        ];

        if (!empty($_COOKIE['octa_cid']) && is_string($_COOKIE['octa_cid'])) {
            $payload['cid'] = sanitize_text_field(wp_unslash($_COOKIE['octa_cid']));
        }

        return $payload;
    }

    public static function dispatch(array $payload, string $source_tag): void
    {
        if (empty($payload['form_fields']) || !is_array($payload['form_fields'])) {
            return;
        }

        if (empty($payload['sid']) && class_exists('Octanist_Health')) {
            Octanist_Health::record_missing_sid(
                $source_tag,
                isset($payload['form_identifier']) ? (string) $payload['form_identifier'] : ''
            );
        }

        Octanist_Api::forward_event($payload, [
            'blocking'         => true,
            'timeout'          => Octanist_Api::FORM_TIMEOUT,
            'client_signals'   => Octanist_Api::collect_client_signals(),
            'cookies'          => Octanist_Api::filter_forwardable_cookies($_COOKIE ?? []),
            'source'           => $source_tag,
            'queue_on_failure' => true,
        ]);
    }

    public static function current_url(): string
    {
        $scheme = is_ssl() ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? parse_url(home_url(), PHP_URL_HOST);
        $uri    = $_SERVER['REQUEST_URI'] ?? '/';
        if (!$host) {
            return '';
        }
        return $scheme . '://' . $host . $uri;
    }

    private static function resolve_page_path($request_path, string $domain): string
    {
        $request_path = is_string($request_path) ? $request_path : '';
        if (self::is_valid_page_path($request_path)) {
            return $request_path;
        }

        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        if (is_string($referer) && $referer !== '') {
            $parsed = wp_parse_url($referer);
            $referer_host = isset($parsed['host']) ? (string) $parsed['host'] : '';
            $referer_path = isset($parsed['path']) ? (string) $parsed['path'] : '';
            if (
                $referer_host !== '' &&
                strcasecmp($referer_host, $domain) === 0 &&
                self::is_valid_page_path($referer_path)
            ) {
                return $referer_path;
            }
        }

        return '/';
    }

    private static function is_valid_page_path(string $path): bool
    {
        if ($path === '' || $path[0] !== '/') {
            return false;
        }

        $lower = strtolower($path);
        if (
            strpos($lower, '/wp-admin/') === 0 ||
            strpos($lower, '/wp-json/') === 0 ||
            strpos($lower, '/wp-login.php') === 0 ||
            $lower === '/wp-admin/admin-ajax.php'
        ) {
            return false;
        }

        return true;
    }

    public static function sid_input_html(): string
    {
        return '<input type="hidden" name="' . esc_attr(self::SID_FIELD_NAME) . '" value="" data-octa-sid-field="1">';
    }

    public static function inject_sid_input(string $html): string
    {
        if ($html === '' || stripos($html, 'name="' . self::SID_FIELD_NAME . '"') !== false || stripos($html, "name='" . self::SID_FIELD_NAME . "'") !== false) {
            return $html;
        }

        if (stripos($html, '</form>') !== false) {
            return preg_replace('/<\/form>/i', self::sid_input_html() . '</form>', $html, 1) ?: $html;
        }

        return $html . self::sid_input_html();
    }

    public static function detected_plugins(): array
    {
        return [
            [
                'label'    => 'Gravity Forms',
                'detected' => class_exists('GFForms'),
            ],
            [
                'label'    => 'Contact Form 7',
                'detected' => class_exists('WPCF7'),
            ],
            [
                'label'    => 'WPForms',
                'detected' => function_exists('wpforms'),
            ],
            [
                'label'    => 'Ninja Forms',
                'detected' => class_exists('Ninja_Forms'),
            ],
            [
                'label'    => 'Elementor Pro Forms',
                'detected' => class_exists('\\ElementorPro\\Modules\\Forms\\Module'),
            ],
            [
                'label'    => 'Fluent Forms',
                'detected' => self::fluent_forms_available(),
            ],
            [
                'label'    => 'Formidable Forms',
                'detected' => self::formidable_forms_available(),
            ],
            [
                'label'    => 'Forminator',
                'detected' => self::forminator_available(),
            ],
            [
                'label'    => 'SureForms',
                'detected' => self::sureforms_available(),
            ],
            [
                'label'    => 'Divi Contact Form',
                'detected' => self::divi_available(),
            ],
        ];
    }

    public static function fluent_forms_available(): bool
    {
        return defined('FLUENTFORM') || defined('FLUENTFORM_VERSION') || function_exists('wpFluentForm');
    }

    public static function formidable_forms_available(): bool
    {
        return class_exists('FrmForm') || class_exists('FrmEntry');
    }

    public static function forminator_available(): bool
    {
        return defined('FORMINATOR_VERSION') || class_exists('Forminator');
    }

    public static function sureforms_available(): bool
    {
        return defined('SRFM_VER')
            || defined('SRFM_VERSION')
            || defined('SUREFORMS_VERSION')
            || did_action('srfm_core_loaded') > 0
            || class_exists('\\SRFM\\Inc\\Plugin')
            || class_exists('\\SureForms\\Inc\\Plugin');
    }

    public static function divi_available(): bool
    {
        return defined('ET_BUILDER_THEME')
            || defined('ET_BUILDER_PLUGIN_ACTIVE')
            || class_exists('ET_Builder_Module_Contact_Form')
            || function_exists('et_setup_theme');
    }

    public static function sanitize_fields(array $fields): array
    {
        $out = [];
        foreach ($fields as $key => $value) {
            if ($key === 'octa_sid' || $key === '') {
                continue;
            }
            $value = self::stringify_field_value($value);
            if ($value === '') {
                continue;
            }
            if (strlen($value) > 2000) {
                $value = substr($value, 0, 2000);
            }
            $out[$key] = $value;
        }
        return $out;
    }

    private static function stringify_field_value($value): string
    {
        if (is_scalar($value)) {
            return trim((string) $value);
        }

        if (!is_array($value)) {
            return '';
        }

        $parts = [];
        array_walk_recursive($value, function ($item) use (&$parts): void {
            if (is_scalar($item)) {
                $item = trim((string) $item);
                if ($item !== '') {
                    $parts[] = $item;
                }
            }
        });

        return implode(', ', $parts);
    }

    /**
     * Find octa_sid anywhere it might have landed in the current request.
     *
     * Checked, in order:
     *   1. $_POST['octa_sid'], native form submissions, GF, WPForms, Elementor
     *   2. $_REQUEST['octa_sid'], GET fallback
     *   3. $_POST['formData'] (JSON, recursive), Ninja Forms AJAX wraps the submission
     *   4. Raw php://input (JSON, recursive), any future JSON-bodied submitter
     */
    public static function discover_octa_sid(): ?string
    {
        if (!empty($_POST['octa_sid']) && is_string($_POST['octa_sid'])) {
            return self::clean_sid($_POST['octa_sid']);
        }

        if (!empty($_REQUEST['octa_sid']) && is_string($_REQUEST['octa_sid'])) {
            return self::clean_sid($_REQUEST['octa_sid']);
        }

        if (!empty($_SERVER['HTTP_X_OCTANIST_SESSION_ID']) && is_string($_SERVER['HTTP_X_OCTANIST_SESSION_ID'])) {
            return self::clean_sid($_SERVER['HTTP_X_OCTANIST_SESSION_ID']);
        }

        if (!empty($_POST) && is_array($_POST)) {
            $found = self::search_recursive(wp_unslash($_POST), 'octa_sid');
            if ($found !== null) {
                return self::clean_sid($found);
            }
        }

        if (!empty($_REQUEST) && is_array($_REQUEST)) {
            $found = self::search_recursive(wp_unslash($_REQUEST), 'octa_sid');
            if ($found !== null) {
                return self::clean_sid($found);
            }
        }

        if (!empty($_POST['formData']) && is_string($_POST['formData'])) {
            $decoded = json_decode(wp_unslash($_POST['formData']), true);
            if (is_array($decoded)) {
                $found = self::search_recursive($decoded, 'octa_sid');
                if ($found !== null) {
                    return self::clean_sid($found);
                }
            }
        }

        $raw = file_get_contents('php://input');
        if (is_string($raw) && $raw !== '' && strpos($raw, 'octa_sid') !== false) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $found = self::search_recursive($decoded, 'octa_sid');
                if ($found !== null) {
                    return self::clean_sid($found);
                }
            }
        }

        return null;
    }

    private static function search_recursive($data, string $needle): ?string
    {
        if (!is_array($data)) {
            return null;
        }
        foreach ($data as $key => $value) {
            if ($key === $needle && (is_string($value) || is_numeric($value))) {
                return (string) $value;
            }
            // Ninja stores fields as [{key: 'octa_sid', value: '...'}, ...]
            if (is_array($value) && isset($value['key'], $value['value']) && $value['key'] === $needle) {
                return is_scalar($value['value']) ? (string) $value['value'] : null;
            }
            if (is_array($value)) {
                $nested = self::search_recursive($value, $needle);
                if ($nested !== null) {
                    return $nested;
                }
            }
        }
        return null;
    }

    private static function clean_sid(string $sid): ?string
    {
        $sid = sanitize_text_field(wp_unslash($sid));
        $sid = trim($sid);
        return $sid === '' ? null : $sid;
    }

    public static function drop_sensitive_keys(array $fields): array
    {
        $denylist = ['password', 'passwd', 'pass', 'pwd', 'captcha', 'g-recaptcha-response', 'h-captcha-response', 'token', 'secret', 'nonce', '_token', '_wpnonce', 'csrf', 'card', 'credit', 'cvc', 'cvv', 'iban', 'bic'];
        foreach (array_keys($fields) as $key) {
            $lower = strtolower((string) $key);
            foreach ($denylist as $deny) {
                if (strpos($lower, $deny) !== false) {
                    unset($fields[$key]);
                    continue 2;
                }
            }
        }
        return $fields;
    }
}
