<?php
function wp_time_logging_create_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $client_table = $wpdb->prefix . 'time_logging_clients';
    $log_table = $wpdb->prefix . 'time_logging_logs';
    $plan_table = $wpdb->prefix . 'time_logging_plans';

    // SQL for the time_logging_clients table
    $client_table_sql = "
        CREATE TABLE IF NOT EXISTS $client_table (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            subscription_type VARCHAR(255) NOT NULL,
            remaining_time INT NOT NULL DEFAULT 140,
            current_plan_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            is_archived TINYINT(1) NOT NULL DEFAULT 0,
            total_logged_time INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) $charset_collate;
    ";

    // SQL for the time_logging_logs table without foreign keys
    $log_table_sql = "
        CREATE TABLE IF NOT EXISTS $log_table (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            client_id BIGINT UNSIGNED NOT NULL,
            time_logged INT NOT NULL,
            log_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            plan_id BIGINT UNSIGNED NOT NULL
        ) $charset_collate;
    ";

    // SQL for the time_logging_plans table without foreign keys
    $plan_table_sql = "
        CREATE TABLE IF NOT EXISTS $plan_table (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            client_id BIGINT UNSIGNED NOT NULL,
            hours_allocated INT NOT NULL DEFAULT 140,
            plan_start_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) $charset_collate;
    ";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($client_table_sql);
    dbDelta($log_table_sql);
    dbDelta($plan_table_sql);
}
