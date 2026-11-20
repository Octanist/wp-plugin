<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Lets a site send its own custom-coded forms to Octanist:
 *
 *     do_action('octanist_track_form', 'whitepaper_download', [
 *         'name'  => $name,
 *         'email' => $email,
 *     ]);
 *
 * Call it from the code that handles the visitor's submission, once the submission
 * is accepted. The session ID, IP and user agent are read from that request: the
 * pixel puts the session ID in the octa_sid field of every form and in the
 * X-Octanist-Session-Id header of same-origin AJAX calls. A call from a background
 * job would send the server's details instead of the visitor's.
 */
class Octanist_Form_Custom
{
    const HOOK = 'octanist_track_form';
    const MAX_FORM_NAME_LENGTH = 191;

    public static function register(): void
    {
        add_action(self::HOOK, [__CLASS__, 'handle'], 10, 2);
    }

    /**
     * @param mixed $form_name Name the form gets in Octanist, e.g. "whitepaper_download".
     * @param mixed $fields    Field name => submitted value.
     */
    public static function handle($form_name = '', $fields = []): void
    {
        // In browser mode the pixel already captures the form; sending it here too would duplicate it.
        if (!Octanist_Settings::server_hooks_enabled()) {
            return;
        }

        $form_name = is_scalar($form_name) ? trim(sanitize_text_field((string) $form_name)) : '';
        if ($form_name === '' || !is_array($fields) || empty($fields)) {
            return;
        }
        $form_name = substr($form_name, 0, self::MAX_FORM_NAME_LENGTH);

        // Drop WordPress internals such as _wpnonce and _wp_http_referer when $_POST is passed as is.
        foreach (array_keys($fields) as $key) {
            if (strpos((string) $key, '_') === 0) {
                unset($fields[$key]);
            }
        }
        $fields = Octanist_Form_Capture::drop_sensitive_keys($fields);

        $payload = Octanist_Form_Capture::build_payload(
            $form_name,
            $fields,
            Octanist_Form_Capture::discover_octa_sid()
        );
        Octanist_Form_Capture::dispatch($payload, 'custom_form');
    }
}
