<?php
if (!defined('ABSPATH')) {
    exit;
}

class Octanist_Form_Elementor
{
    public static function register(): void
    {
        add_action('elementor_pro/forms/new_record', [__CLASS__, 'handle'], 10, 2);
    }

    public static function handle($record, $handler): void
    {
        if (!is_object($record)) {
            return;
        }

        $raw_fields = method_exists($record, 'get') ? $record->get('fields') : [];
        if (!is_array($raw_fields)) {
            $raw_fields = [];
        }

        $skip_types = ['step', 'html', 'recaptcha', 'recaptcha_v3', 'hcaptcha', 'honeypot'];

        $fields   = [];
        $octa_sid = null;

        foreach ($raw_fields as $key => $field) {
            if (!is_array($field)) {
                continue;
            }
            $type = $field['type'] ?? '';
            if (in_array($type, $skip_types, true)) {
                continue;
            }
            $name  = $field['id'] ?? (is_string($key) ? $key : 'field_' . $key);
            $label = $field['title'] ?? $name;
            $value = $field['value'] ?? '';

            if ($name === 'octa_sid' || $label === 'octa_sid') {
                $octa_sid = is_array($value) ? ($value[0] ?? null) : $value;
                continue;
            }
            if ($value === '' || $value === null) {
                continue;
            }
            $fields[$label] = $value;
        }

        if (!$octa_sid) {
            $octa_sid = Octanist_Form_Capture::discover_octa_sid();
        }

        $fields = Octanist_Form_Capture::drop_sensitive_keys($fields);

        $form_id = method_exists($record, 'get_form_settings') ? $record->get_form_settings('id') : 'unknown';

        $payload = Octanist_Form_Capture::build_payload(
            'elementor_' . ($form_id ?: 'unknown'),
            $fields,
            $octa_sid
        );
        Octanist_Form_Capture::dispatch($payload, 'elementor_pro');
    }
}
