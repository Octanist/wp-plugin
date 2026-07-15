<?php
if (!defined('ABSPATH')) {
    exit;
}

class Octanist_Form_Divi
{
    public static function register(): void
    {
        add_action('et_pb_contact_form_submit', [__CLASS__, 'handle'], 10, 3);
    }

    public static function handle($processed_fields_values, $et_contact_error, $contact_form_info): void
    {
        if (!empty($et_contact_error)) {
            return;
        }

        if (!is_array($processed_fields_values)) {
            return;
        }

        $octa_sid = Octanist_Form_Capture::discover_octa_sid();
        $fields = self::extract_fields($processed_fields_values, $octa_sid);
        $fields = Octanist_Form_Capture::drop_sensitive_keys($fields);

        $payload = Octanist_Form_Capture::build_payload(
            'divi_' . self::extract_form_id($contact_form_info),
            $fields,
            $octa_sid
        );
        Octanist_Form_Capture::dispatch($payload, 'divi');
    }

    private static function extract_form_id($contact_form_info): string
    {
        if (!is_array($contact_form_info)) {
            return 'unknown';
        }

        foreach (['contact_form_unique_id', 'contact_form_number', 'form_id', 'module_id'] as $key) {
            if (!empty($contact_form_info[$key]) && is_scalar($contact_form_info[$key])) {
                return sanitize_key((string) $contact_form_info[$key]) ?: 'unknown';
            }
        }

        return 'unknown';
    }

    private static function extract_fields(array $processed_fields_values, &$octa_sid): array
    {
        $fields = [];
        foreach ($processed_fields_values as $key => $value) {
            $fallback = is_string($key) ? $key : 'field_' . (string) $key;

            if ($fallback === Octanist_Form_Capture::SID_FIELD_NAME) {
                $octa_sid = self::scalar_or_first($value);
                continue;
            }

            if (is_array($value)) {
                $field_key = self::field_key($value, $fallback);
                $field_value = self::field_value($value);
                if ($field_key === Octanist_Form_Capture::SID_FIELD_NAME) {
                    $octa_sid = self::scalar_or_first($field_value);
                    continue;
                }
                if ($field_value !== null) {
                    $fields[$field_key] = $field_value;
                }
                continue;
            }

            $fields[$fallback] = $value;
        }

        return $fields;
    }

    private static function field_key(array $field, string $fallback): string
    {
        foreach (['label', 'title', 'field_label', 'field_id', 'id', 'name'] as $key) {
            if (!empty($field[$key]) && is_scalar($field[$key])) {
                return (string) $field[$key];
            }
        }

        return $fallback;
    }

    private static function field_value(array $field)
    {
        foreach (['value', 'field_value', 'processed_value'] as $key) {
            if (array_key_exists($key, $field)) {
                return $field[$key];
            }
        }

        return $field;
    }

    private static function scalar_or_first($value): ?string
    {
        if (is_scalar($value)) {
            return (string) $value;
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                if (is_scalar($item)) {
                    return (string) $item;
                }
            }
        }

        return null;
    }
}
