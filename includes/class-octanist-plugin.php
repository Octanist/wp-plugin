<?php
if (!defined('ABSPATH')) {
    exit;
}

class Octanist_Plugin
{
    private static $booted = false;

    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }
        self::$booted = true;

        self::require_files();

        add_action('plugins_loaded', [__CLASS__, 'maybe_upgrade'], 5);

        Octanist_Rest::register();
        Octanist_Pixel::register();
        Octanist_Form_Capture::register();
        Octanist_Queue::register();

        if (is_admin()) {
            Octanist_Settings::register();
        }
    }

    public static function maybe_upgrade(): void
    {
        $installed_version = (string) get_option('octanist_version', '0');
        if (version_compare($installed_version, OCTANIST_VERSION, '>=')) {
            return;
        }

        Octanist_Queue::schedule();
        Octanist_Api::get_pixel_cache();

        if (Octanist_Settings::is_configured()) {
            Octanist_Queue::schedule_pixel_refresh();
        }

        update_option('octanist_version', OCTANIST_VERSION);
    }

    private static function require_files(): void
    {
        $inc = OCTANIST_PATH . 'includes/';

        require_once $inc . 'class-octanist-health.php';
        require_once $inc . 'class-octanist-queue.php';
        require_once $inc . 'class-octanist-api.php';
        require_once $inc . 'class-octanist-rest.php';
        require_once $inc . 'class-octanist-settings.php';
        require_once $inc . 'class-octanist-pixel.php';

        $forms = $inc . 'forms/';
        require_once $forms . 'class-octanist-form-capture.php';
        require_once $forms . 'class-octanist-form-gravity.php';
        require_once $forms . 'class-octanist-form-cf7.php';
        require_once $forms . 'class-octanist-form-wpforms.php';
        require_once $forms . 'class-octanist-form-ninja.php';
        require_once $forms . 'class-octanist-form-elementor.php';
        require_once $forms . 'class-octanist-form-fluent.php';
        require_once $forms . 'class-octanist-form-formidable.php';
        require_once $forms . 'class-octanist-form-forminator.php';
        require_once $forms . 'class-octanist-form-sureforms.php';
        require_once $forms . 'class-octanist-form-divi.php';
    }
}
