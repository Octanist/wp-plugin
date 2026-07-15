<?php
if (!defined('ABSPATH')) {
    exit;
}

class Octanist_Form_Forminator
{
    public static function register(): void
    {
        add_action('forminator_custom_form_submit_before_set_fields', [__CLASS__, 'handle'], 10, 3);
    }

    public static function handle($entry, $form_id, $field_data_array): void
    {
        $octa_sid = Octanist_Form_Capture::discover_octa_sid();
        $fields = [];

        if (is_array($field_data_array)) {
            foreach ($field_data_array as $field) {
                if (!is_array($field)) {
                    continue;
                }

                $name = $field['name'] ?? ($field['field_name'] ?? ($field['slug'] ?? ''));
                $label = $field['label'] ?? ($field['field_label'] ?? $name);
                $value = $field['value'] ?? ($field['field_value'] ?? '');

                if ($name === '' && $label === '') {
                    continue;
                }

                if ($name === Octanist_Form_Capture::SID_FIELD_NAME || $label === Octanist_Form_Capture::SID_FIELD_NAME) {
                    $octa_sid = is_array($value) ? ($value[0] ?? null) : $value;
                    continue;
                }

                $fields[$label ?: $name] = $value;
            }
        }

        $fields = Octanist_Form_Capture::drop_sensitive_keys($fields);

        $payload = Octanist_Form_Capture::build_payload(
            'forminator_' . (string) $form_id,
            $fields,
            is_scalar($octa_sid) ? (string) $octa_sid : null
        );
        Octanist_Form_Capture::dispatch($payload, 'forminator');
    }
}
