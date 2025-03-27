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
    // Event signup endpoint
    register_rest_route('event-signups/v1', '/submit', array(
        'methods' => 'POST',
        'callback' => 'task_table_handle_signup_submission',
        'permission_callback' => '__return_true' // Allow public access
    ));
    
    // Event management endpoints
    register_rest_route('task-table/v1', '/events', array(
        'methods' => 'GET',
        'callback' => 'task_table_get_events',
        'permission_callback' => function() {
            return current_user_can('edit_posts');
        }
    ));
    
    register_rest_route('task-table/v1', '/events', array(
        'methods' => 'POST',
        'callback' => 'task_table_save_event',
        'permission_callback' => function() {
            return current_user_can('edit_posts');
        }
    ));
    
    register_rest_route('task-table/v1', '/events/(?P<id>\d+)', array(
        'methods' => 'PUT',
        'callback' => 'task_table_update_event',
        'permission_callback' => function() {
            return current_user_can('edit_posts');
        }
    ));
    
    register_rest_route('task-table/v1', '/events/(?P<id>\d+)', array(
        'methods' => 'DELETE',
        'callback' => 'task_table_delete_event',
        'permission_callback' => function() {
            return current_user_can('edit_posts');
        }
    ));
    
    // Notification subscriber management
    register_rest_route('task-table/v1', '/subscribers', array(
        'methods' => 'POST',
        'callback' => 'task_table_add_subscriber',
        'permission_callback' => '__return_true' // Public access to subscribe
    ));
    
    // Notification trigger endpoint
    register_rest_route('task-table/v1', '/trigger-event-notification', array(
        'methods' => 'POST',
        'callback' => 'task_table_trigger_notification',
        'permission_callback' => function() {
            return current_user_can('edit_posts');
        }
    ));
}
add_action('rest_api_init', 'task_table_register_api_routes');

/**
 * Handle the signup form submission via REST API
 */
function task_table_handle_signup_submission(WP_REST_Request $request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'task_table_signups';

    $data = $request->get_json_params();
    $event_id = intval($data['event_id']); 
    $user_name = sanitize_text_field($data['user_name']);
    $user_email = sanitize_email($data['user_email']);
    
    // Verify the event exists
    $events_table = $wpdb->prefix . 'task_table_events';
    $event_exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $events_table WHERE id = %d",
        $event_id
    ));
    
    if (!$event_exists) {
        return new WP_REST_Response(array('success' => false, 'message' => 'Event not found'), 404);
    }

    // Insert into the database
    $result = $wpdb->insert($table_name, array(
        'event_id' => $event_id,
        'user_name' => $user_name,
        'user_email' => $user_email,
        'status' => 'pending',
        'created_at' => current_time('mysql')
    ));

    if ($result === false) {
        error_log('Database insert failed: ' . $wpdb->last_error);
        return new WP_REST_Response(array('success' => false, 'message' => 'Database insert failed'), 500);
    }

    // Send email to moderator using our notification system
    task_table_send_new_signup_notification($data);

    return new WP_REST_Response(array('success' => true), 200);
}

/**
 * Process a new event signup submission
 */
function task_table_process_event_signup($request) {
    // Existing code to handle the signup...
    
    // Assuming the signup data is structured something like this:
    $signup_data = array(
        'event_id' => $request->get_param('event_id'),
        'user_name' => $request->get_param('user_name'),
        'user_email' => $request->get_param('user_email'),
        // other fields...
    );
    
    // Process the signup data
    // ...existing code to save to database...
    
    // Apply filter to allow sending notifications
    // 'pending' is the default status for new signups
    $processed_data = apply_filters('task_table_process_signup', $signup_data, 'pending');
    
    // Return response
    // ...existing response code...
}

/**
 * Get all events from the database
 */
function task_table_get_events() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'task_table_events';
    
    $events = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC", ARRAY_A);
    
    return new WP_REST_Response($events, 200);
}

/**
 * Save a new event to the database
 */
function task_table_save_event(WP_REST_Request $request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'task_table_events';
    
    $data = $request->get_json_params();
    
    // Sanitize all fields
    $event_data = array(
        'was_angefragt' => sanitize_text_field($data['wasAngefragt'] ?? ''),
        'kontext' => sanitize_text_field($data['kontext'] ?? ''),
        'name_hochschule' => sanitize_text_field($data['nameHochschule'] ?? ''),
        'tn_anzahl' => sanitize_text_field($data['tnAnzahl'] ?? ''),
        'zeitpunkt' => sanitize_text_field($data['zeitpunkt'] ?? ''),
        'auftragdetails' => sanitize_text_field($data['auftragdetails'] ?? ''),
        'wer_macht' => sanitize_text_field($data['werMacht'] ?? ''),
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql')
    );
    
    $result = $wpdb->insert($table_name, $event_data);
    
    if ($result === false) {
        return new WP_REST_Response(array(
            'success' => false, 
            'message' => 'Failed to save event',
            'error' => $wpdb->last_error
        ), 500);
    }
    
    $event_id = $wpdb->insert_id;
    $event_data['id'] = $event_id;
    
    // Trigger notification for new event if not empty
    if (!empty($event_data['was_angefragt']) || !empty($event_data['kontext'])) {
        task_table_send_new_event_notification($event_data);
    }
    
    return new WP_REST_Response(array(
        'success' => true,
        'event' => $event_data
    ), 201);
}

/**
 * Update an existing event
 */
function task_table_update_event(WP_REST_Request $request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'task_table_events';
    
    $event_id = (int) $request['id'];
    $data = $request->get_json_params();
    
    // Get existing event data first
    $existing_event = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE id = %d",
        $event_id
    ), ARRAY_A);
    
    if (!$existing_event) {
        return new WP_REST_Response(array(
            'success' => false,
            'message' => 'Event not found'
        ), 404);
    }
    
    // Sanitize and prepare update data - use wp_kses_post to preserve formatting but remove unsafe HTML
    $update_data = array(
        'was_angefragt' => isset($data['was_angefragt']) ? wp_kses_post($data['was_angefragt']) : $existing_event['was_angefragt'],
        'kontext' => isset($data['kontext']) ? wp_kses_post($data['kontext']) : $existing_event['kontext'],
        'name_hochschule' => isset($data['name_hochschule']) ? wp_kses_post($data['name_hochschule']) : $existing_event['name_hochschule'],
        'tn_anzahl' => isset($data['tn_anzahl']) ? wp_kses_post($data['tn_anzahl']) : $existing_event['tn_anzahl'],
        'zeitpunkt' => isset($data['zeitpunkt']) ? wp_kses_post($data['zeitpunkt']) : $existing_event['zeitpunkt'],
        'auftragdetails' => isset($data['auftragdetails']) ? wp_kses_post($data['auftragdetails']) : $existing_event['auftragdetails'],
        'wer_macht' => isset($data['wer_macht']) ? wp_kses_post($data['wer_macht']) : $existing_event['wer_macht'],
        'updated_at' => current_time('mysql')
    );
    
    // Also check for frontend-style field names and use them if present
    if (isset($data['wasAngefragt'])) $update_data['was_angefragt'] = wp_kses_post($data['wasAngefragt']);
    if (isset($data['kontext'])) $update_data['kontext'] = wp_kses_post($data['kontext']);
    if (isset($data['nameHochschule'])) $update_data['name_hochschule'] = wp_kses_post($data['nameHochschule']);
    if (isset($data['tnAnzahl'])) $update_data['tn_anzahl'] = wp_kses_post($data['tnAnzahl']);
    if (isset($data['zeitpunkt'])) $update_data['zeitpunkt'] = wp_kses_post($data['zeitpunkt']);
    if (isset($data['auftragdetails'])) $update_data['auftragdetails'] = wp_kses_post($data['auftragdetails']);
    if (isset($data['werMacht'])) $update_data['wer_macht'] = wp_kses_post($data['werMacht']);
    
    $result = $wpdb->update(
        $table_name,
        $update_data,
        array('id' => $event_id)
    );
    
    if ($result === false) {
        return new WP_REST_Response(array(
            'success' => false,
            'message' => 'Failed to update event',
            'error' => $wpdb->last_error
        ), 500);
    }
    
    // Include the ID in the response
    $update_data['id'] = $event_id;
    
    // Get the complete updated row from the database to ensure we return accurate data
    $updated_event = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE id = %d",
        $event_id
    ), ARRAY_A);
    
    return new WP_REST_Response(array(
        'success' => true,
        'event' => $updated_event
    ), 200);
}

/**
 * Delete an event
 */
function task_table_delete_event(WP_REST_Request $request) {
    global $wpdb;
    $events_table = $wpdb->prefix . 'task_table_events';
    $signups_table = $wpdb->prefix . 'task_table_signups';
    
    $event_id = (int) $request['id'];
    
    // Delete related signups first
    $wpdb->delete($signups_table, array('event_id' => $event_id));
    
    // Then delete the event
    $result = $wpdb->delete($events_table, array('id' => $event_id));
    
    if ($result === false) {
        return new WP_REST_Response(array(
            'success' => false,
            'message' => 'Failed to delete event'
        ), 500);
    }
    
    return new WP_REST_Response(array(
        'success' => true,
        'message' => 'Event deleted successfully'
    ), 200);
}

/**
 * Add a new notification subscriber
 */
function task_table_add_subscriber(WP_REST_Request $request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'task_table_subscribers';
    
    $data = $request->get_json_params();
    $email = sanitize_email($data['email']);
    
    if (!is_email($email)) {
        return new WP_REST_Response(array(
            'success' => false,
            'message' => 'Invalid email address'
        ), 400);
    }
    
    // Check if subscriber already exists
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name WHERE email = %s",
        $email
    ));
    
    if ($exists) {
        return new WP_REST_Response(array(
            'success' => false,
            'message' => 'Email already subscribed'
        ), 409); // Conflict status code
    }
    
    // Insert new subscriber
    $result = $wpdb->insert($table_name, array(
        'email' => $email,
        'created_at' => current_time('mysql')
    ));
    
    if ($result === false) {
        return new WP_REST_Response(array(
            'success' => false,
            'message' => 'Failed to add subscriber',
            'error' => $wpdb->last_error
        ), 500);
    }
    
    return new WP_REST_Response(array(
        'success' => true,
        'message' => 'Subscription successful'
    ), 201);
}

/**
 * Trigger notification for an event
 */
function task_table_trigger_notification(WP_REST_Request $request) {
    $data = $request->get_json_params();
    $event = $data['event'] ?? null;
    $test_mode = !empty($data['_test_mode']);
    
    if (!$event || empty($event['id'])) {
        return new WP_REST_Response(array(
            'success' => false,
            'message' => 'Invalid event data'
        ), 400);
    }
    
    $result = task_table_send_new_event_notification($event, $test_mode);
    
    return new WP_REST_Response($result, 200);
}

/**
 * Utility function to get all approved signups for an event
 */
function task_table_get_event_signups($event_id, $status = 'approved') {
    global $wpdb;
    $table_name = $wpdb->prefix . 'task_table_signups';
    
    $signups = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name WHERE event_id = %d AND status = %s",
        $event_id,
        $status
    ), ARRAY_A);
    
    return $signups;
}

/**
 * Utility function to get an event by ID
 */
function task_table_get_event($event_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'task_table_events';
    
    $event = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE id = %d",
        $event_id
    ), ARRAY_A);
    
    if ($event) {
        // Convert database column names to frontend format for compatibility
        return array(
            'id' => $event['id'],
            'wasAngefragt' => $event['was_angefragt'],
            'kontext' => $event['kontext'],
            'nameHochschule' => $event['name_hochschule'],
            'tnAnzahl' => $event['tn_anzahl'],
            'zeitpunkt' => $event['zeitpunkt'],
            'auftragdetails' => $event['auftragdetails'],
            'werMacht' => $event['wer_macht'],
            'created_at' => $event['created_at'],
            'updated_at' => $event['updated_at']
        );
    }
    
    return null;
}

/**
 * AJAX fallback for notification testing
 */
function task_table_direct_notification_ajax() {
    check_ajax_referer('wp_rest', 'security');
    
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Permission denied');
        return;
    }
    
    $event_data = json_decode(stripslashes($_POST['event_data']), true);
    $test_mode = !empty($_POST['test_mode']);
    
    $result = task_table_send_new_event_notification($event_data, $test_mode);
    
    wp_send_json_success($result);
}
add_action('wp_ajax_task_table_direct_notification', 'task_table_direct_notification_ajax');
