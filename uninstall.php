<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('octanist_settings');
delete_option('octanist_settings_legacy');
delete_option('octanist_health');
delete_option('octanist_event_queue');
delete_option('octanist_event_queue_lock');
delete_option('octanist_pixel_js');
delete_option('octanist_pixel_delivery_failures');
delete_option('octanist_version');
delete_option('octanist_show_v3_notice');
delete_transient('octanist_pixel_js');
delete_transient('octanist_pixel_refresh_pending');
delete_transient('octanist_pixel_delivery_paused');

$timestamp = wp_next_scheduled('octanist_retry_event_queue');
while ($timestamp) {
    wp_unschedule_event($timestamp, 'octanist_retry_event_queue');
    $timestamp = wp_next_scheduled('octanist_retry_event_queue');
}

$timestamp = wp_next_scheduled('octanist_wake_event_queue');
while ($timestamp) {
    wp_unschedule_event($timestamp, 'octanist_wake_event_queue');
    $timestamp = wp_next_scheduled('octanist_wake_event_queue');
}

$timestamp = wp_next_scheduled('octanist_refresh_pixel_cache');
while ($timestamp) {
    wp_unschedule_event($timestamp, 'octanist_refresh_pixel_cache');
    $timestamp = wp_next_scheduled('octanist_refresh_pixel_cache');
}
