<?php
/*
Plugin Name: WP Time Logging
Description: Logs time for clients with subscriptions.
Version: 1.0
Author: Bokis Angelov
Author URI: https://github.com/BokisAngelov
*/

// Include necessary files
include_once plugin_dir_path(__FILE__) . 'admin/admin-panel.php';
include_once plugin_dir_path(__FILE__) . 'frontend/user-dashboard.php';
include_once plugin_dir_path(__FILE__) . 'includes/db-operations.php';

// Register activation and deactivation hooks
register_activation_hook(__FILE__, 'wp_time_logging_install');
register_deactivation_hook(__FILE__, 'wp_time_logging_uninstall');

// Activation hook
function wp_time_logging_install() {
    wp_time_logging_create_tables();
}

// Uninstall hook
function wp_time_logging_uninstall() {
    // Add code for deleting tables with a confirmation
}
?>
