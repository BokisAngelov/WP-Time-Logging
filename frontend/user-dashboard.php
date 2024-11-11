<?php

function wp_time_logging_user_dashboard() {
    if (!is_user_logged_in()) {
        return '<p>You must be logged in to view this page.</p>';
    }

    global $wpdb;
    $current_user_id = get_current_user_id();
    $client_table = $wpdb->prefix . 'time_logging_clients';
    $log_table = $wpdb->prefix . 'time_logging_logs';

    // Fetch client data for the current user
    $client_data = $wpdb->get_row($wpdb->prepare(
        "SELECT id, subscription_type, remaining_time, total_logged_time FROM $client_table WHERE user_id = %d AND is_archived = 0",
        $current_user_id
    ));

    if (!$client_data) {
        return '<p>No subscription data found for your account.</p>';
    }

    // Calculate total logged hours
    $total_logged_hours = wp_time_logging_format_time($client_data->total_logged_time);
    $remaining_time = wp_time_logging_format_time($client_data->remaining_time);

    // Display user dashboard
    ob_start(); ?>

    <div class="uk-container">
        <h2 class="uk-heading-line"><span>Dashboard</span></h2>
        
        <div class="uk-card uk-card-default uk-card-body uk-margin">
            <h3 class="uk-card-title uk-text-center">Subscription Details</h3>
            <div class="uk-grid-small uk-child-width-1-3 uk-text-center" uk-grid>
                <div>
                    <div class="uk-card uk-card-body">
                        <p><strong>Subscription Type</strong></p>
                        <span><?php echo esc_html($client_data->subscription_type); ?></span>
                    </div>
                </div>
                <div>    
                    <div class="uk-card uk-card-body">
                        <p><strong>Remaining Hours</strong></p>
                        <span><?php echo esc_html($remaining_time); ?></span>
                    </div>
                </div>
                <div>
                    <div class="uk-card uk-card-body">
                        <p><strong>Total Logged Hours</strong></p>
                        <span><?php echo esc_html($total_logged_hours); ?></span>
                    </div>
                </div>
            </div>
            <div class="uk-text-center uk-margin-top">
                <a class="request-time uk-button uk-button-primary">Request more time</a>
            </div>
        </div>
    </div>

    <?php
    return ob_get_clean();
}
add_shortcode('user_dashboard', 'wp_time_logging_user_dashboard');

