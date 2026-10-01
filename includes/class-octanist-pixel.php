<?php
if (!defined('ABSPATH')) {
    exit;
}

class Octanist_Pixel
{
    const HANDLE = 'octanist-pixel';

    public static function register(): void
    {
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue']);
        add_filter('script_loader_tag', [__CLASS__, 'filter_tag'], 10, 2);
    }

    public static function enqueue(): void
    {
        if (!Octanist_Settings::is_configured()) {
            return;
        }
        wp_enqueue_script(
            self::HANDLE,
            Octanist_Rest::pixel_url(),
            [],
            null,
            false
        );
    }

    public static function filter_tag($tag, $handle)
    {
        if ($handle !== self::HANDLE) {
            return $tag;
        }

        $attrs = Octanist_Settings::pixel_data_attrs();

        $extra = ' async defer';
        foreach ($attrs as $name => $value) {
            if ($value === '') {
                $extra .= ' ' . esc_attr($name);
            } else {
                $extra .= ' ' . esc_attr($name) . '="' . esc_attr($value) . '"';
            }
        }

        return preg_replace('/<script\b/', '<script' . $extra, $tag, 1);
    }
}
