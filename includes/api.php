<?php
/**
 * REST API endpoints for task table
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Register REST API routes
 */
function task_table_register_api_routes() {
    register_rest_route('event-signups/v1', '/submit', array(
        'methods' => 'POST',
        'callback' => 'task_table_handle_signup_submission',
        'permission_callback' => '__return_true' // Allow public access
    ));
}
add_action('rest_api_init', 'task_table_register_api_routes');

/**
 * Handle the signup form submission via REST API
 */
function task_table_handle_signup_submission(WP_REST_Request $request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'event_signups';

    $data = $request->get_json_params();
    $event_id = intval($data['event_id']); 
    $user_name = sanitize_text_field($data['user_name']);
    $user_email = sanitize_email($data['user_email']);

    // Insert into the database
    $result = $wpdb->insert($table_name, array(
        'event_id' => $event_id,
        'user_name' => $user_name,
        'user_email' => $user_email,
        'status' => 'pending'
    ));

    if ($result === false) {
        error_log('Database insert failed: ' . $wpdb->last_error);
        return new WP_REST_Response(array('success' => false, 'message' => 'Database insert failed'), 500);
    }

    // Send email to moderator
    $moderator_email = get_option('moderator_email', get_option('admin_email'));
    $subject = 'New Sign-Up for Event';
    $message = "A new sign-up has been received:\n\nEvent ID: $event_id\nName: $user_name\nEmail: $user_email";
    wp_mail($moderator_email, $subject, $message);

    return new WP_REST_Response(array('success' => true), 200);
}
