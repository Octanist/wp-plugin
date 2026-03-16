<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('octanist_settings');
delete_option('octanist_version');
