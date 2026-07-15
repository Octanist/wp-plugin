<?php
if (!defined('ABSPATH')) {
    exit;
}

class Octanist_Health
{
    const OPTION = 'octanist_health';

    public static function get(): array
    {
        $h = get_option(self::OPTION, []);
        return is_array($h) ? $h : [];
    }

    public static function record_success(string $source): void
    {
        $h = self::get();
        $h['last_success_at']  = time();
        $h['last_form_source'] = $source;
        update_option(self::OPTION, $h, false);
    }

    public static function record_failure(string $message, string $source): void
    {
        $h = self::get();
        $h['last_failure_at']  = time();
        $h['last_error_msg']   = substr($message, 0, 500);
        $h['last_form_source'] = $source;
        update_option(self::OPTION, $h, false);
    }

    public static function record_missing_sid(string $source, string $form_identifier): void
    {
        $h = self::get();
        $h['last_missing_sid_at'] = time();
        $h['last_missing_sid_source'] = $source;
        $h['last_missing_sid_form'] = substr($form_identifier, 0, 200);
        $h['missing_sid_count'] = isset($h['missing_sid_count']) ? ((int) $h['missing_sid_count']) + 1 : 1;
        update_option(self::OPTION, $h, false);
    }
}
