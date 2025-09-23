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

    // Confirmation endpoint (GET) for double opt-in
    register_rest_route('task-table/v1', '/subscribers/confirm', array(
        'methods' => 'GET',
        'callback' => 'task_table_confirm_subscriber',
        'permission_callback' => '__return_true'
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

    // Queue notification; actual sending happens on post save
    if (task_table_event_is_complete($event_data)) {
        task_table_queue_event_notification($event_id);
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

    // Determine completeness before update
    $was_complete = task_table_event_is_complete($existing_event);
    
    // Sanitize and prepare update data - preserve formatting where needed
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
    
    // Determine completeness after update
    $now_complete = task_table_event_is_complete($update_data);
    
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
    
    // Get the complete updated row
    $updated_event = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE id = %d",
        $event_id
    ), ARRAY_A);

    $now_complete = task_table_event_is_complete($updated_event);

    // Queue notification only on transition to complete; sending deferred to save_post
    if (!$was_complete && $now_complete) {
        task_table_queue_event_notification($event_id);
    }
    
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
        // If already confirmed, treat as duplicate
        $confirmed = (int)$wpdb->get_var($wpdb->prepare("SELECT confirmed FROM $table_name WHERE email = %s", $email));
        if ($confirmed === 1) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Email already subscribed'
            ), 409);
        }
        // If not confirmed, regenerate a token and re-send email
        $token = wp_generate_password(32, false, false);
        $wpdb->update($table_name, array(
            'confirmation_token' => $token,
            'updated_at' => current_time('mysql')
        ), array('email' => $email));
        task_table_send_subscription_confirmation_email($email, $token);
        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Confirmation email resent. Please check your inbox.'
        ), 200);
    }

    // Create new unconfirmed subscriber with token
    $token = wp_generate_password(32, false, false);
    $result = $wpdb->insert($table_name, array(
        'email' => $email,
        'confirmed' => 0,
        'confirmation_token' => $token,
        'created_at' => current_time('mysql')
    ));

    if ($result === false) {
        return new WP_REST_Response(array(
            'success' => false,
            'message' => 'Failed to start subscription',
            'error' => $wpdb->last_error
        ), 500);
    }

    task_table_send_subscription_confirmation_email($email, $token);

    return new WP_REST_Response(array(
        'success' => true,
        'message' => 'Please confirm your subscription via the email we just sent.'
    ), 201);
}

/**
 * Confirm a subscriber using token + email (double opt-in)
 */
function task_table_confirm_subscriber(WP_REST_Request $request) {
    global $wpdb; 
    $table_name = $wpdb->prefix . 'task_table_subscribers';
    $email = sanitize_email($request->get_param('email'));
    $token = sanitize_text_field($request->get_param('token'));

    if (!is_email($email) || empty($token)) {
        return new WP_REST_Response(array(
            'success' => false,
            'message' => 'Invalid confirmation parameters.'
        ), 400);
    }

    $row = $wpdb->get_row($wpdb->prepare("SELECT id, confirmed, confirmation_token FROM $table_name WHERE email = %s", $email));
    if (!$row) {
        return new WP_REST_Response(array(
            'success' => false,
            'message' => 'Subscriber not found.'
        ), 404);
    }
    if ((int)$row->confirmed === 1) {
        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Subscription already confirmed.'
        ), 200);
    }
    if (hash_equals((string)$row->confirmation_token, (string)$token)) {
        $updated = $wpdb->update($table_name, array(
            'confirmed' => 1,
            'confirmation_token' => null,
            'updated_at' => current_time('mysql')
        ), array('id' => $row->id));
        if ($updated !== false) {
            // Re-fetch to verify
            $verify = (int)$wpdb->get_var($wpdb->prepare("SELECT confirmed FROM $table_name WHERE id = %d", $row->id));
            if ($verify === 1) {
                return new WP_REST_Response(array(
                    'success' => true,
                    'message' => 'Subscription confirmed. Thank you!'
                ), 200);
            }
        }
        return new WP_REST_Response(array(
            'success' => false,
            'message' => 'Could not mark as confirmed.'
        ), 500);
    }
    return new WP_REST_Response(array(
        'success' => false,
        'message' => 'Invalid or expired token.'
    ), 400);
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

// Track events already notified to avoid duplicate emails
if (!defined('TASK_TABLE_NOTIFIED_EVENTS_OPTION')) {
    define('TASK_TABLE_NOTIFIED_EVENTS_OPTION', 'task_table_notified_event_ids');
}

function task_table_mark_event_notified($event_id) {
    $list = get_option(TASK_TABLE_NOTIFIED_EVENTS_OPTION, array());
    $list = is_array($list) ? $list : array();
    $list[] = (int) $event_id;
    $list = array_values(array_unique(array_map('intval', $list)));
    update_option(TASK_TABLE_NOTIFIED_EVENTS_OPTION, $list);
}

function task_table_was_event_notified($event_id) {
    $list = get_option(TASK_TABLE_NOTIFIED_EVENTS_OPTION, array());
    return is_array($list) && in_array((int)$event_id, $list, true);
}

// Pending notifications queue (processed on post save)
if (!defined('TASK_TABLE_PENDING_NOTIFICATIONS_OPTION')) {
    define('TASK_TABLE_PENDING_NOTIFICATIONS_OPTION', 'task_table_pending_event_notifications');
}

function task_table_queue_event_notification($event_id) {
    $queue = get_option(TASK_TABLE_PENDING_NOTIFICATIONS_OPTION, array());
    $queue = is_array($queue) ? $queue : array();
    $queue[] = (int) $event_id;
    $queue = array_values(array_unique(array_map('intval', $queue)));
    update_option(TASK_TABLE_PENDING_NOTIFICATIONS_OPTION, $queue);
}

function task_table_send_pending_event_notifications($post_id, $post, $update) {
    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
        return;
    }
    $queue = get_option(TASK_TABLE_PENDING_NOTIFICATIONS_OPTION, array());
    if (empty($queue) || !is_array($queue)) {
        return;
    }

    global $wpdb;
    $events_table = $wpdb->prefix . 'task_table_events';
    foreach ($queue as $event_id) {
        if (task_table_was_event_notified($event_id)) {
            continue;
        }
        $event = $wpdb->get_row($wpdb->prepare("SELECT * FROM $events_table WHERE id = %d", (int)$event_id), ARRAY_A);
        if ($event && task_table_event_is_complete($event)) {
            task_table_send_new_event_notification($event);
            task_table_mark_event_notified($event_id);
        }
    }
    delete_option(TASK_TABLE_PENDING_NOTIFICATIONS_OPTION);
}
add_action('save_post', 'task_table_send_pending_event_notifications', 20, 3);

/**
 * Determine if an event is complete enough to notify (require title and context)
 */
function task_table_event_is_complete($event) {
    $title = isset($event['was_angefragt']) ? $event['was_angefragt'] : ($event['wasAngefragt'] ?? '');
    $context = isset($event['kontext']) ? $event['kontext'] : ($event['kontext'] ?? '');
    $title = trim(strip_tags((string)$title));
    $context = trim(strip_tags((string)$context));
    return ($title !== '' && $context !== '');
}
