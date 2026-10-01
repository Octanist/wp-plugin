<?php
if (!defined('ABSPATH')) {
    exit;
}

class Octanist_Form_Wpforms
{
    public static function register(): void
    {
        add_action('wpforms_display_submit_before', [__CLASS__, 'render_sid_field'], 10, 1);
        add_action('wpforms_process_complete', [__CLASS__, 'handle'], 10, 4);
    }

    public static function render_sid_field($form_data): void
    {
        echo Octanist_Form_Capture::sid_input_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public static function handle($fields, $entry, $form_data, $entry_id): void
    {
        $skip_types = ['divider', 'html', 'pagebreak', 'captcha', 'hcaptcha', 'turnstile'];

        $extracted = [];
        $octa_sid  = null;

        if (is_array($fields)) {
            foreach ($fields as $field) {
                $type = $field['type'] ?? '';
                if (in_array($type, $skip_types, true)) {
                    continue;
                }
                $name  = $field['name'] ?? ('field_' . ($field['id'] ?? ''));
                $value = $field['value'] ?? '';
                if ($name === 'octa_sid' || (isset($field['meta']['key']) && $field['meta']['key'] === 'octa_sid')) {
                    $octa_sid = is_array($value) ? ($value[0] ?? null) : $value;
                    continue;
                }
                if ($value === '' || $value === null) {
                    continue;
                }
                $extracted[$name] = $value;
            }
        }

        if (!$octa_sid && is_array($entry) && isset($entry['octa_sid'])) {
            $octa_sid = is_array($entry['octa_sid']) ? ($entry['octa_sid'][0] ?? null) : $entry['octa_sid'];
        }
        if (!$octa_sid) {
            $octa_sid = Octanist_Form_Capture::discover_octa_sid();
        }

        $extracted = Octanist_Form_Capture::drop_sensitive_keys($extracted);

        $payload = Octanist_Form_Capture::build_payload(
            'wpforms_' . ($form_data['id'] ?? 'unknown'),
            $extracted,
            $octa_sid
        );
        Octanist_Form_Capture::dispatch($payload, 'wpforms');
    }
}
