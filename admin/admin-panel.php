<?php
// Add main menu and submenus in the WordPress dashboard
function wp_time_logging_menu() {
    add_menu_page('Time logging', 'Time logging', 'manage_options', 'wp_time_logging', 'wp_time_logging_admin_page', 'dashicons-clock', 6);
    add_submenu_page('wp_time_logging', 'Add New Client', 'Add New Client', 'manage_options', 'wp_time_logging_add_client', 'wp_time_logging_add_client_page');
    add_submenu_page('wp_time_logging', 'Archived Clients', 'Archived Clients', 'manage_options', 'wp_time_logging_archived_clients', 'wp_time_logging_archived_clients_page');
}
add_action('admin_menu', 'wp_time_logging_menu');

// Enqueue scripts for timer
function wp_time_logging_enqueue_scripts($hook) {
    // Restrict the script to load only on the "wp-time-logging" main page
    if ($hook !== 'toplevel_page_wp_time_logging') {
        return; // Exit if not on the specific plugin page
    }

    // Enqueue the JavaScript file and localize the AJAX URL
    wp_enqueue_script('time-logging-timer', plugin_dir_url(__FILE__) . 'js/timer.js', ['jquery'], null, true);
    wp_localize_script('time-logging-timer', 'wpTimeLogging', [
        'ajax_url' => admin_url('admin-ajax.php')
    ]);
}
add_action('admin_enqueue_scripts', 'wp_time_logging_enqueue_scripts');
add_action('admin_enqueue_scripts', function($hook) { error_log("Current admin page hook: " . $hook); });

// Main admin page displaying client list
function wp_time_logging_admin_page() {
    
    // Check for and display the transient message
    if ($message = get_transient('wp_time_logging_redirect_message')) {
        echo $message;
        delete_transient('wp_time_logging_redirect_message'); // Clear it after displaying
    }
    ?>
    <div class="wrap">
        <h1>Clients List</h1>
        <?php wp_time_logging_display_clients_list(); ?>
    </div>
    <?php
}

function wp_time_logging_format_time($seconds) {
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    return sprintf('%dh %dm', $hours, $minutes);
}

// Submenu page to add a new client
function wp_time_logging_add_client_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'time_logging_clients';

    // Check if we're editing an existing client
    $is_edit = isset($_GET['edit']) ? intval($_GET['edit']) : false;
    $client = null;

    if ($is_edit) {
        // Fetch the client's current data for editing
        $client = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $is_edit));
        if (!$client) {
            echo '<div class="notice notice-error"><p>Client not found.</p></div>';
            return;
        }
    }

    // Display the form
    ?>
    <div class="wrap">
        <h1><?php echo $is_edit ? 'Edit Client' : 'Add New Client'; ?></h1>
        <form method="post" action="">
            <?php wp_nonce_field('wp_time_logging_save_client', 'wp_time_logging_nonce'); ?>
            <input type="hidden" name="client_id" value="<?php echo esc_attr($is_edit); ?>" />
            <table class="form-table">
                <tr>
                    <th><label for="client_name">Client Name</label></th>
                    <td>
                        <input type="text" name="client_name" id="client_name" required value="<?php echo esc_attr($client ? get_userdata($client->user_id)->first_name . ' ' . get_userdata($client->user_id)->last_name : ''); ?>" />
                    </td>
                </tr>
                <tr>
                    <th><label for="client_email">Client Email</label></th>
                    <td>
                        <input type="text" name="client_email" id="client_email" required value="<?php echo esc_attr($client ? get_userdata($client->user_id)->user_email : ''); ?>" />
                    </td>
                </tr>
                <tr>
                    <th><label for="subscription_type">Subscription Type</label></th>
                    <td>
                        <input type="text" name="subscription_type" id="subscription_type" required value="<?php echo esc_attr($client->subscription_type ?? ''); ?>" />
                    </td>
                </tr>
                <tr>
                    <th><label for="remaining_time">Remaining Time (hrs)</label></th>
                    <td>
                        <input type="number" name="remaining_time" id="remaining_time" required value="<?php echo esc_attr($client->remaining_time ?? 140); ?>" />
                    </td>
                </tr>
            </table>
            <input type="submit" name="save_client" class="button button-primary" value="<?php echo $is_edit ? 'Update Client' : 'Add Client'; ?>">
        </form>
    </div>
    <?php
}

// Handle form submission for adding or updating clients
function wp_time_logging_save_client() {
    if (isset($_POST['save_client']) && check_admin_referer('wp_time_logging_save_client', 'wp_time_logging_nonce')) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'time_logging_clients';

        $client_id = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;
        $client_name = sanitize_text_field($_POST['client_name']);
        $client_email = sanitize_text_field($_POST['client_email']);
        $subscription_type = sanitize_text_field($_POST['subscription_type']);
        $remaining_time_hours = intval($_POST['remaining_time']) ?: 140; // Default subscription time
        $remaining_time = $remaining_time_hours * 3600;
        $redirect_message = '';

        $name_parts = explode(' ', $client_name);
        $temp_username = str_replace(' ', '', sanitize_user($client_name));
        $client_username = strtolower($temp_username);
        $first_name = $name_parts[0];
        $last_name = count($name_parts) > 1 ? implode(' ', array_slice($name_parts, 1)) : '';

        // If client ID exists, update; otherwise, insert a new client
        if ($client_id) {
            // Retrieve existing client data
            $existing_client = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $client_id));
            $user_id = $existing_client->user_id;
            $user_info = get_userdata($user_id);

            $user_full_name = $user_info->first_name . ' ' . $user_info->last_name;

            // Array to store changed fields
            $changes = [];
            $user_changes = [];

            // Check if each field has changed
            if ($existing_client->subscription_type !== $subscription_type) {
                $changes['subscription_type'] = $subscription_type;
            }
            if ($existing_client->remaining_time != $remaining_time) {
                $changes['remaining_time'] = $remaining_time;
            }

            if ($user_full_name !== $client_name){
                $inner_name_parts = explode(' ', $client_name);
                $inner_first_name = $inner_name_parts[0];
                $inner_last_name = count($inner_name_parts) > 1 ? implode(' ', array_slice($inner_name_parts, 1)) : '';
                $user_changes['first_name'] = $inner_first_name;
                $user_changes['last_name'] = $inner_last_name;
                $user_chnages['display_name'] = $user_full_name;
            }
            // Check and update client email
            if ($user_info->user_email !== $client_email) {
                $user_changes['user_email'] = $client_email;
            }

            // Update WordPress user if there are changes
            if (!empty($user_changes)) {
                $user_changes['ID'] = $user_id;
                $user_update = wp_update_user($user_changes);
                if (is_wp_error($user_update)) {
                    $redirect_message = '<div class="notice notice-error"><p>Error updating user: ' . $user_update->get_error_message() . '</p></div>';
                } else {
                    $redirect_message = '<div class="notice notice-success"><p>Client updated successfully!</p></div>';
                }
            }

            // Update only if there are changes
            if (!empty($changes)) {
                $wpdb->update($table_name, $changes, ['id' => $client_id]);
                $redirect_message = '<div class="notice notice-success"><p>Client updated successfully!</p></div>';
            } 

            if (empty($changes) && empty($user_changes)) {
                $redirect_message = '<div class="notice notice-info"><p>No changes detected. ' . $user_full_name . '</p></div>';
            }

            
        } else {

            // Create a new user with username, first name, and last name
            $user_id = wp_insert_user([
                'user_login' => $client_username,
                'user_pass' => wp_generate_password(),
                'user_email' => $client_email,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'role' => 'subscriber'
            ]);

            if (is_wp_error($user_id)) {
                $redirect_message = '<div class="notice notice-error"><p>Error creating user: ' . $user_id->get_error_message() . '</p></div>';
            } else {

            // Insert new client
                $wpdb->insert(
                    $table_name,
                    [
                        'user_id' => $user_id,
                        'subscription_type' => $subscription_type,
                        'remaining_time' => $remaining_time,
                        'current_plan_id' => 1,
                        'is_archived' => 0
                    ]
                );
                $redirect_message = '<div class="notice notice-success"><p>Client added successfully!</p></div>';
            }
        }

        // Redirect to avoid duplicate submissions
        set_transient('wp_time_logging_redirect_message', $redirect_message, 60);
        wp_redirect(admin_url('admin.php?page=wp_time_logging'));
        exit;
    }
}
add_action('admin_init', 'wp_time_logging_save_client');

// Display the list of clients with edit, archive, and delete options
function wp_time_logging_display_clients_list() {
    global $wpdb;
    $client_table = $wpdb->prefix . 'time_logging_clients';
    $log_table = $wpdb->prefix . 'time_logging_logs';
    
    $clients = $wpdb->get_results("SELECT * FROM $client_table WHERE is_archived = 0");

    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th>Client Name</th><th>Subscription Type</th><th>Remaining Time</th><th>Overall Logged Time</th><th>Timer</th><th>Actions</th></tr></thead>';
    echo '<tbody>';

    if ($clients) {
        foreach ($clients as $client) {
            $user_info = get_userdata($client->user_id);

            // Calculate current plan time
            // $current_plan_seconds = $wpdb->get_var($wpdb->prepare(
            //     "SELECT SUM(time_logged) FROM {$wpdb->prefix}time_logging_logs WHERE client_id = %d AND plan_id = %d",
            //     $client->id, $client->current_plan_id
            // ));

            // $current_plan_time_display = wp_time_logging_format_time($current_plan_seconds);
            
            // $overall_time_display = wp_time_logging_format_time($overall_logged_seconds);
            $overall_time_display = wp_time_logging_format_time($client->total_logged_time);
            $remaining_time_display = wp_time_logging_format_time($client->remaining_time);

            echo '<tr>';
            echo '<td>' . esc_html($user_info->first_name) . ' ' . esc_html($user_info->last_name) . '</td>';
            echo '<td>' . esc_html($client->subscription_type) . '</td>';
            echo '<td class="remaining-time" data-client-id="' . $client->id . '">' . esc_html($remaining_time_display) . '</td>';
            // echo '<td class="current-plan-time" data-client-id="' . $client->id . '">' . esc_html($current_plan_time_display) . '</td>';
            echo '<td class="overall-time" data-client-id="' . $client->id . '">' . esc_html($overall_time_display) . '</td>';
            echo '<td>
                <span style="padding-bottom: 8px; display:block; margin-left: 4px;" id="timer-display-' . $client->id . '" data-client-id="' . $client->id . '" class="timer-display">00:00:00</span>
                <button class="start-timer page-title-action" data-client-id="' . $client->id . '">Start</button>
                <button class="pause-timer page-title-action" data-client-id="' . $client->id . '" disabled>Pause</button>
                <button class="reset-timer page-title-action" data-client-id="' . $client->id . '">Reset</button>
                <button style="margin-top: 8px; width: 176px;" class="log-time page-title-action" data-client-id="' . $client->id . '" disabled>Log</button>
                <br>
                <span id="success-message-' . $client->id . '" class="success-message" style="display: none; color: green;">Time logged successfully!</span>
            </td>';
            echo '<td>
                <a href="?page=wp_time_logging_add_client&edit=' . $client->id . '">Edit</a> | 
                <a href="?page=wp_time_logging&archive=' . $client->id . '">Archive</a> | 
                <a style="color: #d63638;" href="?page=wp_time_logging&delete=' . $client->id . '" onclick="return confirm(\'Are you sure you want to delete this client?\')">Delete</a>
                <br>
                <button style="margin-top: 8px; margin-left: 0;" class="add-plan page-title-action" data-client-id="' . $client->id . '">Add New Plan</button>
                <br>
                <span id="plan-message-' . $client->id . '" class="plan-message" style="display: none; color: green;">New plan added successfully with 140 hours.</span>
            </td>';
            echo '</tr>';
        }
    } else {
        echo '<p>No clients found.</p>';
    }
    echo '</tbody>';
    echo '</table>';
}



// Archive, delete, and edit clients based on URL parameters
function wp_time_logging_handle_actions() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'time_logging_clients';

    // Archive Client
    if (isset($_GET['archive'])) {
        $client_id = intval($_GET['archive']);
        $wpdb->update($table_name, ['is_archived' => 1], ['id' => $client_id]);
        echo '<div class="notice notice-success"><p>Client archived successfully!</p></div>';
    }

    // Activate Client
    if (isset($_GET['activate'])) {
        $client_id = intval($_GET['activate']);
        $wpdb->update($table_name, ['is_archived' => 0], ['id' => $client_id]);
        echo '<div class="notice notice-success"><p>Client activated successfully!</p></div>';
        // wp_redirect(admin_url('admin.php?page=wp_time_logging'));
    }

    // Delete Client
    if (isset($_GET['delete'])) {
        $client_id = intval($_GET['delete']);
        $user_id = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->prefix}time_logging_clients WHERE id = %d",
            $client_id
        ));
        // Delete logs associated with this client
        $wpdb->delete("{$wpdb->prefix}time_logging_logs", ['client_id' => $client_id]);

        // Delete the client record from the clients table
        $wpdb->delete("{$wpdb->prefix}time_logging_clients", ['id' => $client_id]);
        // Fetch the user_id associated with this client
        
        wp_delete_user($user_id);
        echo '<div class="notice notice-success"><p>Client and all related records deleted successfully!</p></div>';
    }

}
add_action('admin_init', 'wp_time_logging_handle_actions');

// Display archived clients
function wp_time_logging_archived_clients_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'time_logging_clients';
    $clients = $wpdb->get_results("SELECT * FROM $table_name WHERE is_archived = 1");

    if ($clients) {
        echo '<h2>Archived Clients</h2>';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>Client Name</th><th>Subscription Type</th><th>Remaining Time (hrs)</th><th>Actions</th></tr></thead>';
        echo '<tbody>';

        foreach ($clients as $client) {
            $user_info = get_userdata($client->user_id);
            echo '<tr>';
            echo '<td>' . esc_html($user_info->first_name) . ' ' . esc_html($user_info->last_name) . '</td>';
            echo '<td>' . esc_html($client->subscription_type) . '</td>';
            echo '<td>' . esc_html($client->remaining_time) . '</td>';
            echo '<td>
            <a href="?page=wp_time_logging&activate=' . $client->id . '">Activate</a> 
            </td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
    } else {
        echo '<p>No archived clients found.</p>';
    }
}

// debug
function cprf_log_message($message) {
    $log_file_path = WP_CONTENT_DIR . '/custom-log.txt';
    $timestamp = date("Y-m-d H:i:s");
    $log_message = $timestamp . ' - ' . $message . PHP_EOL;

    // Open the file for appending.
    $log_file = fopen($log_file_path, 'a');
    if ($log_file) {
        fwrite($log_file, $log_message);
        fflush($log_file);  // Force the output buffer to flush and write to the file.
        fclose($log_file);
    } else {
        error_log("Failed to open custom-log.txt for writing.");
    }
}

// Handle AJAX request to log time
function wp_time_logging_log_time() {
    global $wpdb;
    $client_id = intval($_POST['client_id']);
    $time_logged = intval($_POST['time_logged']); // in seconds
    $table_name = $wpdb->prefix . 'time_logging_clients';
    // Fetch client data
    $client = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}time_logging_clients WHERE id = %d", $client_id));
    $current_plan_id = $client->current_plan_id;

    if ($client && $time_logged > 0) {
        // Log the time
        $wpdb->insert("{$wpdb->prefix}time_logging_logs", [
            'client_id' => $client_id,
            'time_logged' => $time_logged,
            'log_date' => current_time('mysql'),
            'plan_id' => $current_plan_id
        ]);

        // Update total_logged_time in hours
        $updated_total_logged_time = $client->total_logged_time + $time_logged;
        $wpdb->update("{$wpdb->prefix}time_logging_clients", [
            'total_logged_time' => $updated_total_logged_time
        ], ['id' => $client_id]);

        cprf_log_message('updated total logged: ' . $updated_total_logged_time);

        // Update remaining time in hours and save it back to the database
        $remaining_time = $client->remaining_time - $time_logged;
        $wpdb->update($table_name, [
            'remaining_time' => $remaining_time
        ], ['id' => $client_id]);

        // Calculate current plan time
        // $current_plan_time_seconds = $wpdb->get_var($wpdb->prepare(
        //     "SELECT SUM(time_logged) FROM {$wpdb->prefix}time_logging_logs WHERE client_id = %d AND plan_id = %d",
        //     $client_id, $current_plan_id
        // ));

        // Send updated values back to the client
        wp_send_json_success([
            'remaining_time' => wp_time_logging_format_time($remaining_time),
            // 'current_plan_time' => wp_time_logging_format_time($current_plan_time_seconds),
            'overall_time' => wp_time_logging_format_time($updated_total_logged_time)
        ]);

    } else {
        wp_send_json_error(['message' => 'Invalid client or time logged value']);
    }
}
add_action('wp_ajax_log_time', 'wp_time_logging_log_time');


function wp_time_logging_add_new_plan() {
    global $wpdb;
    $client_id = intval($_POST['client_id']);

    // Fetch the current client data
    $client = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}time_logging_clients WHERE id = %d", $client_id));
    cprf_log_message('client id: ' . $client_id);
    if ($client) {
        // Increment plan ID and add 140 hours to the existing remaining time
        $new_plan_id = $client->current_plan_id + 1;
        $additional_hours = 140 * 3600;
        $new_remaining_time = $client->remaining_time + $additional_hours;

        // Update client with new plan ID and increased remaining time
        $wpdb->update("{$wpdb->prefix}time_logging_clients", [
            'current_plan_id' => $new_plan_id,
            'remaining_time' => $new_remaining_time
        ], ['id' => $client_id]);

        cprf_log_message('new remaining time: ' . $new_remaining_time);

        // Return updated remaining time
        wp_send_json_success([
            'remaining_time' => wp_time_logging_format_time($new_remaining_time)
        ]);
    } else {
        wp_send_json_error();
    }
}
add_action('wp_ajax_add_new_plan', 'wp_time_logging_add_new_plan');

