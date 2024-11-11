<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit();
}

global $wpdb;
$client_table = $wpdb->prefix . 'time_logging_clients';
$time_log_table = $wpdb->prefix . 'time_logging_logs';

$wpdb->query("DROP TABLE IF EXISTS $client_table, $time_log_table");
?>
