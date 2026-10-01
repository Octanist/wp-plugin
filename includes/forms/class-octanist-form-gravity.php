<?php
if (!defined('ABSPATH')) {
    exit;
}

class Octanist_Form_Gravity
{
    public static function register(): void
    {
        add_filter('gform_get_form_filter', [__CLASS__, 'inject_sid_field'], 10, 2);
        add_action('gform_after_submission', [__CLASS__, 'handle'], 10, 2);
    }

    public static function inject_sid_field($form_string, $form)
    {
        if (!is_string($form_string)) {
            return $form_string;
        }
        return Octanist_Form_Capture::inject_sid_input($form_string);
    }

    public static function handle($entry, $form): void
    {
        if (!is_array($entry) || !is_array($form)) {
            return;
        }

        $octa_sid = null;
        $fields   = [];

        foreach ((array) ($form['fields'] ?? []) as $field) {
            $input_type = $field->type ?? '';
            if (in_array($input_type, ['html', 'page', 'section', 'captcha'], true)) {
                continue;
            }

            $label = $field->label ?: ('field_' . ($field->id ?? ''));

            if (!empty($field->inputs) && is_array($field->inputs)) {
                $parts = [];
                foreach ($field->inputs as $input) {
                    $val = rgar($entry, (string) $input['id']);
                    if ($val !== '' && $val !== null) {
                        $parts[] = $val;
                    }
                }
                if (!empty($parts)) {
                    $fields[$label] = implode(' ', $parts);
                }
                continue;
            }

            $val = rgar($entry, (string) ($field->id ?? ''));
            if ($val !== '' && $val !== null) {
                if ($field->type === 'octa_sid_hidden' || $label === 'octa_sid') {
                    $octa_sid = (string) $val;
                    continue;
                }
                $fields[$label] = is_array($val) ? implode(', ', $val) : $val;
            }
        }

        if (!$octa_sid) {
            $octa_sid = rgar($entry, 'octa_sid') ?: Octanist_Form_Capture::discover_octa_sid();
        }

        $fields = Octanist_Form_Capture::drop_sensitive_keys($fields);

        $payload = Octanist_Form_Capture::build_payload(
            'gf_' . ($form['id'] ?? 'unknown'),
            $fields,
            $octa_sid
        );
        Octanist_Form_Capture::dispatch($payload, 'gravity_forms');
    }
}
