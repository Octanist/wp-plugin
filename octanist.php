<?php
/**
 * Plugin Name:         Octanist
 * Description:         First-party proxy for the Octanist pixel. Serves the tracking script, forwards events and call-tracking assignments, and captures form submissions server-side from popular form plugins.
 * Version:             4.1.0
 * Author:              Octanist
 * Author URI:          https://www.octanist.com/
 * Text Domain:         octanist
 * License:             GPLv2 or later
 * License URI:         https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least:   6.0
 * Tested up to:        7.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('OCTANIST_VERSION', '4.1.0');
define('OCTANIST_PATH', plugin_dir_path(__FILE__));
define('OCTANIST_URL', plugin_dir_url(__FILE__));
define('OCTANIST_FILE', __FILE__);
if (!defined('OCTANIST_UPSTREAM')) {
    define('OCTANIST_UPSTREAM', 'https://c.octani.st');
}

require_once OCTANIST_PATH . 'includes/class-octanist-plugin.php';
Octanist_Plugin::boot();

register_activation_hook(__FILE__, 'octanist_activate');
function octanist_activate()
{
    $legacy = get_option('octanist_settings');
    if (is_array($legacy) && isset($legacy['field_mappings']) && !get_option('octanist_settings_legacy')) {
        add_option('octanist_settings_legacy', $legacy);
        // Signal the one-time legacy settings admin notice.
        update_option('octanist_show_v3_notice', '1');
    }

    $defaults = [
        'measurement_id' => '',
        'listener_mode'  => 'server',
        'consent_mode'   => 'auto',
    ];
    $current = get_option('octanist_settings');
    if (!is_array($current) || !array_key_exists('measurement_id', $current)) {
        update_option('octanist_settings', $defaults);
    }

    update_option('octanist_version', OCTANIST_VERSION);
    if (class_exists('Octanist_Queue')) {
        Octanist_Queue::schedule();
        if (Octanist_Settings::is_configured()) {
            Octanist_Queue::schedule_pixel_refresh();
        }
    }
}

register_deactivation_hook(__FILE__, 'octanist_deactivate');
function octanist_deactivate()
{
    if (class_exists('Octanist_Queue')) {
        Octanist_Queue::unschedule();
    }
    // Data is preserved on deactivation. Settings are removed on full uninstall.
}

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'octanist_add_action_links');
function octanist_add_action_links($links)
{
    $settings_link = '<a href="' . esc_url(admin_url('options-general.php?page=octanist')) . '">' . esc_html__('Settings', 'octanist') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
