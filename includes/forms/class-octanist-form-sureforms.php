<?php
if (!defined('ABSPATH')) {
    exit;
}

class Octanist_Form_Sureforms
{
    public static function register(): void
    {
        add_action('srfm_form_submit', [__CLASS__, 'handle'], 10, 1);
    }

    public static function handle($form_data): void
    {
        if (!is_array($form_data) || empty($form_data)) {
            return;
        }

        $octa_sid = Octanist_Form_Capture::discover_octa_sid();
        $fields = self::extract_fields($form_data, $octa_sid);
        $fields = Octanist_Form_Capture::drop_sensitive_keys($fields);

        $payload = Octanist_Form_Capture::build_payload(
            'sureforms_' . self::extract_form_id($form_data),
            $fields,
            $octa_sid
        );
        Octanist_Form_Capture::dispatch($payload, 'sureforms');
    }

    private static function extract_form_id(array $form_data): string
    {
        foreach (['form_id', 'id', 'formId', 'post_id'] as $key) {
            if (!empty($form_data[$key]) && is_scalar($form_data[$key])) {
                return sanitize_key((string) $form_data[$key]) ?: 'unknown';
            }
        }

        return 'unknown';
    }

    private static function extract_fields(array $form_data, &$octa_sid): array
    {
        $field_data = $form_data['fields'] ?? ($form_data['form_data'] ?? ($form_data['data'] ?? $form_data));
        if (!is_array($field_data)) {
            return [];
        }

        $meta_keys = [
            'form_id',
            'id',
            'formId',
            'post_id',
            'entry_id',
            'submission_id',
            'status',
            'success',
            'message',
            'redirect',
        ];

        $fields = [];
        foreach ($field_data as $key => $value) {
            $key = is_string($key) ? $key : 'field_' . (string) $key;
            if (in_array($key, $meta_keys, true)) {
                continue;
            }

            if ($key === Octanist_Form_Capture::SID_FIELD_NAME) {
                $octa_sid = self::scalar_or_first($value);
                continue;
            }

            if (is_array($value)) {
                $field_key = self::field_key($value, $key);
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

            $fields[$key] = $value;
        }

        return $fields;
    }

    private static function field_key(array $field, string $fallback): string
    {
        foreach (['label', 'name', 'key', 'id', 'slug'] as $key) {
            if (!empty($field[$key]) && is_scalar($field[$key])) {
                return (string) $field[$key];
            }
        }

        return $fallback;
    }

    private static function field_value(array $field)
    {
        foreach (['value', 'field_value', 'answer'] as $key) {
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
