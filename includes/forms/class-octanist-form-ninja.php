<?php
if (!defined('ABSPATH')) {
    exit;
}

class Octanist_Form_Ninja
{
    public static function register(): void
    {
        add_action('ninja_forms_display_after_fields', [__CLASS__, 'render_sid_field'], 10, 2);
        add_action('ninja_forms_after_submission', [__CLASS__, 'handle']);
    }

    public static function render_sid_field($form_id, $is_preview): void
    {
        if ($is_preview) {
            return;
        }
        echo Octanist_Form_Capture::sid_input_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public static function handle($form_data): void
    {
        if (!is_array($form_data)) {
            return;
        }

        $form_id = $form_data['form_id'] ?? ($form_data['id'] ?? 'unknown');

        $fields    = [];
        $octa_sid  = null;

        $source_fields = [];
        if (isset($form_data['fields']) && is_array($form_data['fields'])) {
            $source_fields = $form_data['fields'];
        } elseif (isset($form_data['fields_by_key']) && is_array($form_data['fields_by_key'])) {
            $source_fields = $form_data['fields_by_key'];
        }

        $skip_types = ['submit', 'hr', 'html', 'recaptcha', 'hcaptcha'];

        foreach ($source_fields as $key => $field) {
            $type = $field['type'] ?? ($field['settings']['type'] ?? '');
            if (in_array($type, $skip_types, true)) {
                continue;
            }
            $name  = $field['key'] ?? ($field['settings']['key'] ?? (is_string($key) ? $key : 'field_' . $key));
            $label = $field['label'] ?? ($field['settings']['label'] ?? $name);
            $value = $field['value'] ?? ($field['settings']['value'] ?? '');

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

        $payload = Octanist_Form_Capture::build_payload(
            'nf_' . $form_id,
            $fields,
            $octa_sid
        );
        Octanist_Form_Capture::dispatch($payload, 'ninja_forms');
    }
}
