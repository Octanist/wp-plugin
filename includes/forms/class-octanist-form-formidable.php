<?php
if (!defined('ABSPATH')) {
    exit;
}

class Octanist_Form_Formidable
{
    public static function register(): void
    {
        add_action('frm_after_create_entry', [__CLASS__, 'handle'], 20, 2);
    }

    public static function handle($entry_id, $form_id): void
    {
        $posted_meta = [];
        if (isset($_POST['item_meta']) && is_array($_POST['item_meta'])) {
            $posted_meta = wp_unslash($_POST['item_meta']);
        }

        $octa_sid = Octanist_Form_Capture::discover_octa_sid();
        $labels = self::field_labels($form_id);

        $fields = [];
        foreach ($posted_meta as $field_id => $value) {
            $key = isset($labels[(string) $field_id]) ? $labels[(string) $field_id] : 'field_' . $field_id;
            if ($key === Octanist_Form_Capture::SID_FIELD_NAME) {
                $octa_sid = is_array($value) ? ($value[0] ?? null) : $value;
                continue;
            }
            $fields[$key] = $value;
        }

        $fields = Octanist_Form_Capture::drop_sensitive_keys($fields);

        $payload = Octanist_Form_Capture::build_payload(
            'formidable_' . (string) $form_id,
            $fields,
            is_scalar($octa_sid) ? (string) $octa_sid : null
        );
        Octanist_Form_Capture::dispatch($payload, 'formidable_forms');
    }

    private static function field_labels($form_id): array
    {
        if (!class_exists('FrmField') || !method_exists('FrmField', 'get_all_for_form')) {
            return [];
        }

        $labels = [];
        $fields = FrmField::get_all_for_form($form_id);
        if (!is_array($fields)) {
            return [];
        }

        foreach ($fields as $field) {
            if (!is_object($field)) {
                continue;
            }
            $id = isset($field->id) ? (string) $field->id : '';
            if ($id === '') {
                continue;
            }
            $labels[$id] = !empty($field->name) ? (string) $field->name : 'field_' . $id;
        }

        return $labels;
    }
}
