<?php
if (!defined('ABSPATH')) {
    exit;
}

class Octanist_Form_Fluent
{
    public static function register(): void
    {
        add_action('fluentform/submission_inserted', [__CLASS__, 'handle'], 10, 3);
    }

    public static function handle($insert_id, $form_data, $form): void
    {
        if (!is_array($form_data)) {
            $form_data = [];
        }

        $octa_sid = null;
        if (isset($form_data[Octanist_Form_Capture::SID_FIELD_NAME])) {
            $octa_sid = is_array($form_data[Octanist_Form_Capture::SID_FIELD_NAME])
                ? ($form_data[Octanist_Form_Capture::SID_FIELD_NAME][0] ?? null)
                : $form_data[Octanist_Form_Capture::SID_FIELD_NAME];
        }
        if (!$octa_sid) {
            $octa_sid = Octanist_Form_Capture::discover_octa_sid();
        }

        $fields = $form_data;
        unset($fields[Octanist_Form_Capture::SID_FIELD_NAME]);
        $fields = Octanist_Form_Capture::drop_sensitive_keys($fields);

        $form_id = 'unknown';
        if (is_object($form) && isset($form->id)) {
            $form_id = (string) $form->id;
        } elseif (is_array($form) && isset($form['id'])) {
            $form_id = (string) $form['id'];
        }

        $payload = Octanist_Form_Capture::build_payload(
            'fluent_' . $form_id,
            $fields,
            $octa_sid
        );
        Octanist_Form_Capture::dispatch($payload, 'fluent_forms');
    }
}
