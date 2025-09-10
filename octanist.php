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

define('OFH_PATH', plugin_dir_path(__FILE__));
define('OFH_URL', plugin_dir_url(__FILE__));

require_once OFH_PATH . 'includes/admin.php';
require_once OFH_PATH . 'assets/styles.php';

register_activation_hook(__FILE__, 'ofh_activate_plugin');
function ofh_activate_plugin()
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
        'submission_mode' => 'ajax',
        'debug_mode' => '0',
    ];

    if (!get_option('octanist_settings')) {
        add_option('octanist_settings', $default_settings);
    }
}

register_deactivation_hook(__FILE__, 'ofh_deactivate_plugin');
function ofh_deactivate_plugin()
{
    delete_option('octanist_settings');
}