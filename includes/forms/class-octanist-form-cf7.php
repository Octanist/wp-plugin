<?php
if (!defined('ABSPATH')) {
    exit;
}

class Octanist_Form_Cf7
{
    public static function register(): void
    {
        add_filter('wpcf7_form_hidden_fields', [__CLASS__, 'hidden_fields']);
        add_action('wpcf7_mail_sent', [__CLASS__, 'handle']);
    }

    public static function hidden_fields($fields): array
    {
        if (!is_array($fields)) {
            $fields = [];
        }
        $fields[Octanist_Form_Capture::SID_FIELD_NAME] = '';
        return $fields;
    }

    public static function handle($contact_form): void
    {
        if (!class_exists('WPCF7_Submission')) {
            return;
        }

        $submission = WPCF7_Submission::get_instance();
        if (!$submission) {
            return;
        }

        $posted = $submission->get_posted_data();
        if (!is_array($posted)) {
            $posted = [];
        }

        $octa_sid = null;
        if (isset($posted['octa_sid'])) {
            $octa_sid = is_array($posted['octa_sid']) ? ($posted['octa_sid'][0] ?? null) : $posted['octa_sid'];
        }
        if (!$octa_sid) {
            $octa_sid = Octanist_Form_Capture::discover_octa_sid();
        }

        $fields = [];
        foreach ($posted as $key => $value) {
            if ($key === '' || strpos($key, '_wpcf7') === 0 || $key === 'octa_sid') {
                continue;
            }
            $fields[$key] = $value;
        }

        $fields = Octanist_Form_Capture::drop_sensitive_keys($fields);

        $form_id = is_object($contact_form) && method_exists($contact_form, 'id') ? $contact_form->id() : 'unknown';

        $payload = Octanist_Form_Capture::build_payload(
            'cf7_' . $form_id,
            $fields,
            $octa_sid
        );
        Octanist_Form_Capture::dispatch($payload, 'contact_form_7');
    }
}
