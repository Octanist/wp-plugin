<?php
/**
 * Plugin Name:         Octanist
 * Description:         Connect your WordPress forms to the Octanist platform for powerful, seamless offline conversion tracking.
 * Version:             2.0.0
 * Author:              Octanist
 * Author URI:          https://www.octanist.com/
 * Text Domain:         octanist
 * License:             GPLv2 or later
 * License URI:         https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least:   6.0
 * Tested up to:        6.8
 */

if (!defined('ABSPATH')) {
    exit;
}

define('OCTANIST_PATH', plugin_dir_path(__FILE__));
define('OCTANIST_URL', plugin_dir_url(__FILE__));
define('OCTANIST_VERSION', '2.0.0');

require_once OCTANIST_PATH . 'includes/admin.php';

register_activation_hook(__FILE__, 'octanist_activate_plugin');
function octanist_activate_plugin()
{
    // Set up a single option array with default values
    $default_settings = [
        'octanist_id' => '',
        'field_mappings' => [
            'name' => [''],
            'email' => [''],
            'phone' => [''],
            'custom' => [''],
        ],
        'send_to_octanist' => '1',
        'send_to_datalayer' => '0',
        'debug_mode' => '0',
    ];

    if (!get_option('octanist_settings')) {
        add_option('octanist_settings', $default_settings);
        add_option('octanist_version', OCTANIST_VERSION);
    }
}

register_deactivation_hook(__FILE__, 'octanist_deactivate_plugin');
function octanist_deactivate_plugin()
{
    delete_option('octanist_settings');
    delete_option('octanist_version');
}

// Add a settings link to the plugin actions
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'octanist_add_action_links');
function octanist_add_action_links($links)
{
    $settings_link = '<a href="' . esc_url(admin_url('options-general.php?page=octanist-settings')) . '">' . __('Settings', 'octanist') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}