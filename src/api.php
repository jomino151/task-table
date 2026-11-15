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

    // Categories listing
    register_rest_route('task-table/v1', '/categories', array(
        'methods' => 'GET',
        'callback' => 'task_table_api_get_categories',
        'permission_callback' => '__return_true'
    ));
    
    // Event management endpoints
    register_rest_route('task-table/v1', '/events', array(
        'methods' => 'GET',
        'callback' => 'task_table_get_events',
        'permission_callback' => function() {
            return current_user_can('manage_task_table');
        }
    ));
    
    register_rest_route('task-table/v1', '/events', array(
        'methods' => 'POST',
        'callback' => 'task_table_save_event',
        'permission_callback' => function() {
            return current_user_can('manage_task_table');
        }
    ));
    
    register_rest_route('task-table/v1', '/events/(?P<id>\d+)', array(
        'methods' => 'PUT',
        'callback' => 'task_table_update_event',
        'permission_callback' => function() {
            return current_user_can('manage_task_table');
        }
    ));
    
    register_rest_route('task-table/v1', '/events/(?P<id>\d+)', array(
        'methods' => 'DELETE',
        'callback' => 'task_table_delete_event',
        'permission_callback' => function() {
            return current_user_can('manage_task_table');
        }
    ));

    // Archive/unarchive event
    register_rest_route('task-table/v1', '/events/(?P<id>\d+)/archive', array(
        'methods' => 'POST',
        'callback' => 'task_table_archive_event',
        'permission_callback' => function() { return current_user_can('manage_task_table'); }
    ));

    // Export events (active or archived) as Excel (.xls XML Spreadsheet)
    register_rest_route('task-table/v1', '/events/export', array(
        'methods' => 'GET',
        'callback' => 'task_table_export_events_excel',
        // Same capability as the admin export (admin-post) and every other /events route.
        'permission_callback' => function() { return current_user_can('manage_task_table'); }
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
 * Simple fixed-window rate limiter for the public endpoints, backed by transients.
 *
 * @param string $bucket Logical action name, e.g. "signup".
 * @param string $key    Who is limited (IP address or email).
 * @param int    $limit  Allowed calls per window. Filterable via `task_table_rate_limit`.
 * @param int    $window Window length in seconds.
 * @return bool True when the caller is over the limit.
 */
function task_table_is_rate_limited($bucket, $key, $limit, $window) {
    $limit = (int) apply_filters('task_table_rate_limit', $limit, $bucket);
    if ($limit <= 0 || $key === '') {
        return false;
    }

    $transient = 'tt_rl_' . md5($bucket . '|' . strtolower($key));
    $state = get_transient($transient);
    if (!is_array($state) || !isset($state['count'], $state['reset']) || $state['reset'] <= time()) {
        $state = array('count' => 0, 'reset' => time() + $window);
    }

    if ($state['count'] >= $limit) {
        return true;
    }

    $state['count']++;
    set_transient($transient, $state, max(1, $state['reset'] - time()));
    return false;
}

/**
 * Client IP used for rate limiting. Uses REMOTE_ADDR only; proxies can adjust it via the filter.
 */
function task_table_get_client_ip() {
    $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
    return (string) apply_filters('task_table_client_ip', $ip);
}

function task_table_too_many_requests_response() {
    return new WP_REST_Response(array(
        'success' => false,
        'message' => __('Zu viele Anfragen. Bitte versuche es später erneut.', 'task-table'),
    ), 429);
}

/**
 * Handle the signup form submission via REST API
 */
function task_table_handle_signup_submission(WP_REST_Request $request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'task_table_signups';

    if (task_table_is_rate_limited('signup', task_table_get_client_ip(), 5, 10 * MINUTE_IN_SECONDS)) {
        return task_table_too_many_requests_response();
    }

    $data = $request->get_json_params();
    $data = is_array($data) ? $data : array();
    $event_id = isset($data['event_id']) ? absint($data['event_id']) : 0;
    $user_name = isset($data['user_name']) && is_scalar($data['user_name']) ? sanitize_text_field((string) $data['user_name']) : '';
    $user_email = isset($data['user_email']) && is_scalar($data['user_email']) ? sanitize_email((string) $data['user_email']) : '';
    $free_text = isset($data['free_text']) && is_scalar($data['free_text']) ? wp_kses_post((string) $data['free_text']) : '';

    if ($user_name === '' || mb_strlen($user_name) > 100 || !is_email($user_email) || strlen($user_email) > 100) {
        return new WP_REST_Response(array(
            'success' => false,
            'message' => __('Bitte gib einen Namen und eine gültige E-Mail-Adresse an.', 'task-table'),
        ), 400);
    }
    if (mb_strlen($free_text) > 2000) {
        $free_text = mb_substr($free_text, 0, 2000);
    }

    // Only open (non-archived) events accept signups.
    $events_table = $wpdb->prefix . 'task_table_events';
    $event_exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $events_table WHERE id = %d AND archived = 0",
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
        'free_text' => $free_text,
        'status' => 'pending',
        'created_at' => current_time('mysql')
    ));

    if ($result === false) {
        error_log('Database insert failed: ' . $wpdb->last_error);
        return new WP_REST_Response(array('success' => false, 'message' => 'Database insert failed'), 500);
    }

    // Send email to moderator using our notification system
    task_table_send_new_signup_notification(array(
        'event_id' => $event_id,
        'user_name' => $user_name,
        'user_email' => $user_email,
        'free_text' => $free_text,
    ));

    return new WP_REST_Response(array('success' => true), 200);
}

/**
 * List available categories.
 */
function task_table_api_get_categories(WP_REST_Request $request) {
    $categories = task_table_get_categories();
    $default_id = task_table_get_default_category_id();

    $payload = array_map(function ($category) use ($default_id) {
        return array(
            'id' => (int) $category['id'],
            'name' => $category['name'],
            'slug' => $category['slug'],
            'description' => $category['description'],
            'sort_order' => (int) $category['sort_order'],
            'is_default' => ((int) $category['id'] === (int) $default_id),
        );
    }, $categories);

    return new WP_REST_Response($payload, 200);
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
function task_table_get_events(WP_REST_Request $request) {
    global $wpdb;
    $events_table = $wpdb->prefix . 'task_table_events';
    $categories_table = $wpdb->prefix . 'task_table_categories';
    $archived = $request->get_param('archived');
    $category_id = $request->get_param('category_id');
    $category_slug = $request->get_param('category');

    $conditions = array();
    $params = array();

    if ($archived === '1' || $archived === 1) {
        $conditions[] = 'e.archived = 1';
    } elseif ($archived === '0' || $archived === 0) {
        $conditions[] = 'e.archived = 0';
    }

    if (!empty($category_id)) {
        $conditions[] = 'e.category_id = %d';
        $params[] = (int) $category_id;
    } elseif (!empty($category_slug)) {
        $conditions[] = 'c.slug = %s';
        $params[] = sanitize_title($category_slug);
    }

    $where = '';
    if (!empty($conditions)) {
        $where = 'WHERE ' . implode(' AND ', $conditions);
    }

    $sql = "SELECT e.*, c.name AS category_name, c.slug AS category_slug
            FROM $events_table e
            LEFT JOIN $categories_table c ON c.id = e.category_id
            $where
            ORDER BY e.created_at DESC";

    if (!empty($params)) {
        $sql = $wpdb->prepare($sql, $params);
    }

    $events = $wpdb->get_results($sql, ARRAY_A);

    $formatted = array_map(function ($event) {
        $event['category_id'] = isset($event['category_id']) ? (int) $event['category_id'] : 0;
        return $event;
    }, $events);

    return new WP_REST_Response($formatted, 200);
}

/**
 * Allowed inline HTML for event text cells.
 *
 * Keep this list narrow so editors can format text and links without
 * introducing layout-breaking block markup in table cells.
 *
 * @return array<string, array<string, bool>>
 */
function task_table_get_event_text_allowed_tags() {
    return array(
        'a' => array(
            'href' => true,
            'target' => true,
            'rel' => true,
            'title' => true,
            'class' => true,
        ),
        'strong' => array(),
        'b' => array(),
        'em' => array(),
        'i' => array(),
        'u' => array(),
        's' => array(),
        'del' => array(),
        'mark' => array(),
        'small' => array(),
        'sub' => array(),
        'sup' => array(),
        'br' => array(),
        'span' => array(
            'class' => true,
        ),
    );
}

/**
 * Sanitize event text values while preserving safe inline formatting.
 *
 * @param mixed $value Raw value from request payload.
 * @return string
 */
function task_table_sanitize_event_text($value) {
    $string_value = is_scalar($value) ? (string) $value : '';

    return wp_kses($string_value, task_table_get_event_text_allowed_tags());
}

/**
 * Save a new event to the database
 */
function task_table_save_event(WP_REST_Request $request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'task_table_events';
    
    $data = $request->get_json_params();

    $category_id = 0;
    if (isset($data['category_id'])) {
        $category_id = (int) $data['category_id'];
    } elseif (isset($data['categoryId'])) {
        $category_id = (int) $data['categoryId'];
    }

    if ($category_id <= 0 || !task_table_get_category($category_id)) {
        $category_id = task_table_get_default_category_id();
    }
    
    // Sanitize all fields
    $event_data = array(
        'was_angefragt' => task_table_sanitize_event_text($data['wasAngefragt'] ?? ''),
        'kontext' => task_table_sanitize_event_text($data['kontext'] ?? ''),
        'name_hochschule' => task_table_sanitize_event_text($data['nameHochschule'] ?? ''),
        'tn_anzahl' => task_table_sanitize_event_text($data['tnAnzahl'] ?? ''),
        'zeitpunkt' => task_table_sanitize_event_text($data['zeitpunkt'] ?? ''),
        'auftragdetails' => task_table_sanitize_event_text($data['auftragdetails'] ?? ''),
        'wer_macht' => task_table_sanitize_event_text($data['werMacht'] ?? ''),
        'category_id' => $category_id,
        'archived' => 0,
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
        'was_angefragt' => isset($data['was_angefragt']) ? task_table_sanitize_event_text($data['was_angefragt']) : $existing_event['was_angefragt'],
        'kontext' => isset($data['kontext']) ? task_table_sanitize_event_text($data['kontext']) : $existing_event['kontext'],
        'name_hochschule' => isset($data['name_hochschule']) ? task_table_sanitize_event_text($data['name_hochschule']) : $existing_event['name_hochschule'],
        'tn_anzahl' => isset($data['tn_anzahl']) ? task_table_sanitize_event_text($data['tn_anzahl']) : $existing_event['tn_anzahl'],
        'zeitpunkt' => isset($data['zeitpunkt']) ? task_table_sanitize_event_text($data['zeitpunkt']) : $existing_event['zeitpunkt'],
        'auftragdetails' => isset($data['auftragdetails']) ? task_table_sanitize_event_text($data['auftragdetails']) : $existing_event['auftragdetails'],
        'wer_macht' => isset($data['wer_macht']) ? task_table_sanitize_event_text($data['wer_macht']) : $existing_event['wer_macht'],
        'updated_at' => current_time('mysql')
    );
    
    // Also check for frontend-style field names and use them if present
    if (isset($data['wasAngefragt'])) $update_data['was_angefragt'] = task_table_sanitize_event_text($data['wasAngefragt']);
    if (isset($data['kontext'])) $update_data['kontext'] = task_table_sanitize_event_text($data['kontext']);
    if (isset($data['nameHochschule'])) $update_data['name_hochschule'] = task_table_sanitize_event_text($data['nameHochschule']);
    if (isset($data['tnAnzahl'])) $update_data['tn_anzahl'] = task_table_sanitize_event_text($data['tnAnzahl']);
    if (isset($data['zeitpunkt'])) $update_data['zeitpunkt'] = task_table_sanitize_event_text($data['zeitpunkt']);
    if (isset($data['auftragdetails'])) $update_data['auftragdetails'] = task_table_sanitize_event_text($data['auftragdetails']);
    if (isset($data['werMacht'])) $update_data['wer_macht'] = task_table_sanitize_event_text($data['werMacht']);

    $incoming_category_id = null;
    if (isset($data['category_id'])) {
        $incoming_category_id = (int) $data['category_id'];
    } elseif (isset($data['categoryId'])) {
        $incoming_category_id = (int) $data['categoryId'];
    }

    if ($incoming_category_id !== null) {
        if ($incoming_category_id > 0 && task_table_get_category($incoming_category_id)) {
            $update_data['category_id'] = $incoming_category_id;
        } else {
            $default_category_id = task_table_get_default_category_id();
            if ($default_category_id) {
                $update_data['category_id'] = $default_category_id;
            }
        }
    }
    
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
 * Add a new notification subscriber (double opt-in).
 *
 * Responses are identical whether or not the address is already subscribed, so the
 * endpoint cannot be used to check who is on the list. Confirmed subscribers are never
 * modified by this unauthenticated endpoint.
 */
function task_table_add_subscriber(WP_REST_Request $request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'task_table_subscribers';

    if (task_table_is_rate_limited('subscribe_ip', task_table_get_client_ip(), 10, HOUR_IN_SECONDS)) {
        return task_table_too_many_requests_response();
    }

    $data = $request->get_json_params();
    $data = is_array($data) ? $data : array();
    $email = isset($data['email']) && is_scalar($data['email']) ? sanitize_email((string) $data['email']) : '';
    $requested_categories = isset($data['categories']) ? (array) $data['categories'] : array();

    if (!is_email($email) || strlen($email) > 100) {
        return new WP_REST_Response(array(
            'success' => false,
            'message' => 'Invalid email address'
        ), 400);
    }

    $generic_response = new WP_REST_Response(array(
        'success' => true,
        'message' => 'Please confirm your subscription via the email we just sent.'
    ), 200);

    // At most 3 confirmation mails per address per day, so the form can't be used to mail-bomb someone.
    if (task_table_is_rate_limited('subscribe_email', $email, 3, DAY_IN_SECONDS)) {
        return $generic_response;
    }

    $subscriber = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE email = %s",
        $email
    ));

    if ($subscriber && (int) $subscriber->confirmed === 1) {
        return $generic_response;
    }

    $token = wp_generate_password(32, false, false);
    $now = current_time('mysql');

    if ($subscriber) {
        task_table_sync_subscriber_categories((int) $subscriber->id, $requested_categories);
        $wpdb->update($table_name, array(
            'confirmation_token' => $token,
            'updated_at' => $now
        ), array('id' => $subscriber->id));
    } else {
        $result = $wpdb->insert($table_name, array(
            'email' => $email,
            'confirmed' => 0,
            'confirmation_token' => $token,
            'created_at' => $now,
            'updated_at' => $now
        ));

        if ($result === false) {
            error_log('Task Table: subscriber insert failed: ' . $wpdb->last_error);
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Failed to start subscription'
            ), 500);
        }

        task_table_sync_subscriber_categories((int) $wpdb->insert_id, $requested_categories);
    }

    task_table_send_subscription_confirmation_email($email, $token);

    return $generic_response;
}

/**
 * How long a double opt-in link stays valid, in seconds.
 */
function task_table_get_confirmation_token_ttl() {
    return (int) apply_filters('task_table_confirmation_token_ttl', 7 * DAY_IN_SECONDS);
}

/**
 * Confirm a pending subscription.
 *
 * @return string 'confirmed', 'invalid' (unknown email, wrong/expired token, already confirmed) or 'error'.
 */
function task_table_confirm_subscription_token($email, $token) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'task_table_subscribers';

    if (!is_email($email) || !is_string($token) || $token === '') {
        return 'invalid';
    }

    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT id, confirmed, confirmation_token, created_at, updated_at FROM $table_name WHERE email = %s",
        $email
    ));
    if (!$row || (int) $row->confirmed === 1 || empty($row->confirmation_token)
        || !hash_equals((string) $row->confirmation_token, $token)) {
        return 'invalid';
    }

    $issued_at = strtotime((string) ($row->updated_at ?: $row->created_at));
    if ($issued_at && $issued_at + task_table_get_confirmation_token_ttl() < strtotime(current_time('mysql'))) {
        return 'invalid';
    }

    $updated = $wpdb->update($table_name, array(
        'confirmed' => 1,
        'confirmation_token' => null,
        'updated_at' => current_time('mysql')
    ), array('id' => $row->id));

    return $updated === false ? 'error' : 'confirmed';
}

/**
 * Confirm a subscriber using token + email (double opt-in)
 */
function task_table_confirm_subscriber(WP_REST_Request $request) {
    $email = sanitize_email((string) $request->get_param('email'));
    $token = sanitize_text_field((string) $request->get_param('token'));

    $result = task_table_confirm_subscription_token($email, $token);
    if ($result === 'confirmed') {
        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Subscription confirmed. Thank you!'
        ), 200);
    }
    if ($result === 'error') {
        return new WP_REST_Response(array(
            'success' => false,
            'message' => 'Could not mark as confirmed.'
        ), 500);
    }
    return new WP_REST_Response(array(
        'success' => false,
        'message' => 'Invalid or expired confirmation link.'
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
            'category_id' => isset($event['category_id']) ? (int) $event['category_id'] : 0,
            'created_at' => $event['created_at'],
            'updated_at' => $event['updated_at'],
            'archived' => (int)$event['archived']
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

/**
 * Archive or unarchive an event
 */
function task_table_archive_event(WP_REST_Request $request) {
    global $wpdb;
    $events_table = $wpdb->prefix . 'task_table_events';
    $event_id = (int)$request['id'];
    $body = $request->get_json_params();
    $archive = isset($body['archived']) ? (int)$body['archived'] : 1; // default archive
    $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $events_table WHERE id = %d", $event_id));
    if (!$exists) {
        return new WP_REST_Response(['success' => false, 'message' => 'Event not found'], 404);
    }
    $res = $wpdb->update($events_table, [ 'archived' => $archive, 'updated_at' => current_time('mysql') ], ['id' => $event_id]);
    if ($res === false) {
        return new WP_REST_Response(['success' => false, 'message' => 'Failed to update archive state'], 500);
    }
    $event = $wpdb->get_row($wpdb->prepare("SELECT * FROM $events_table WHERE id = %d", $event_id), ARRAY_A);
    return new WP_REST_Response(['success' => true, 'event' => $event]);
}

/**
 * Export events as CSV (optionally archived=1)
 */
function task_table_export_events_excel(WP_REST_Request $request) {
    // Defence in depth: the export contains internal event data, so require the plugin capability here too.
    if (!current_user_can('manage_task_table')) {
        return new WP_REST_Response(['success' => false, 'message' => 'Forbidden'], 403);
    }

    $params = $request->get_params();
    $archived = isset($params['archived']) ? $params['archived'] : null;
    $format = isset($params['format']) ? sanitize_text_field($params['format']) : 'xlsx';
    if (!in_array($format, ['xlsx', 'xml'], true)) {
        $format = 'xlsx';
    }
    $filters = task_table_normalize_export_filters($params);

    $sheet = task_table_generate_events_spreadsheet($archived, $format, $filters);

    return new WP_REST_Response($sheet['content'], 200, [
        'Content-Type' => $sheet['content_type'],
        'Content-Disposition' => 'attachment; filename="' . $sheet['filename'] . '"'
    ]);
}

/**
 * Serve the export body as a raw file instead of letting the REST server JSON-encode it.
 * Headers (Content-Type, Content-Disposition) are already sent by the server at this point.
 */
function task_table_serve_raw_export($served, $result, $request) {
    if ($served || !($result instanceof WP_HTTP_Response) || !($request instanceof WP_REST_Request)) {
        return $served;
    }
    if ($request->get_route() !== '/task-table/v1/events/export' || $result->get_status() !== 200 || !is_string($result->get_data())) {
        return $served;
    }
    echo $result->get_data(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- binary file body
    return true;
}
add_filter('rest_pre_serve_request', 'task_table_serve_raw_export', 10, 3);

/**
 * Normalize the "archived" filter value of an export request.
 */
function task_table_normalize_archived_filter($value) {
    if (is_array($value)) {
        $value = reset($value);
    }

    if ($value === null) {
        return null;
    }

    if (is_string($value)) {
        $value = strtolower(trim($value));
    }

    if ($value === '' || $value === 'all' || $value === 'any' || $value === 'both' || $value === 'everything') {
        return null;
    }

    if ($value === '1' || $value === 'true' || $value === 'archived' || $value === 'yes') {
        return 1;
    }

    if ($value === '0' || $value === 'false' || $value === 'active' || $value === 'no' || $value === 'live') {
        return 0;
    }

    if (is_numeric($value)) {
        $int_val = (int) $value;
        if ($int_val === 1) {
            return 1;
        }
        if ($int_val === 0) {
            return 0;
        }
    }

    return null;
}

function task_table_normalize_export_filters($params) {
    $filters = [];

    if (!is_array($params)) {
        return $filters;
    }

    $raw_categories = [];
    if (isset($params['tt_categories'])) {
        $raw_categories = wp_unslash($params['tt_categories']);
    } elseif (isset($params['tt_category'])) {
        $raw_categories = wp_unslash($params['tt_category']);
    }

    if (!empty($raw_categories)) {
        if (!is_array($raw_categories)) {
            $raw_categories = [$raw_categories];
        }

        $category_ids = [];
        foreach ($raw_categories as $value) {
            if (is_array($value)) {
                $value = reset($value);
            }
            $value = absint($value);
            if ($value > 0) {
                $category_ids[] = $value;
            }
        }

        if (!empty($category_ids)) {
            $filters['categories'] = array_values(array_unique($category_ids));
        }
    }

    if (isset($params['tt_search'])) {
        $search = sanitize_text_field(wp_unslash($params['tt_search']));
        if ($search !== '') {
            $filters['search'] = $search;
        }
    }

    return $filters;
}

function task_table_generate_events_spreadsheet($archived_param = null, $format = 'xlsx', $filters = []) {
    global $wpdb;
    $events_table = $wpdb->prefix . 'task_table_events';
    $categories_table = $wpdb->prefix . 'task_table_categories';

    $archived_filter = task_table_normalize_archived_filter($archived_param);
    if (!is_array($filters)) {
        $filters = [];
    }

    $where_clauses = [];
    $params = [];

    if ($archived_filter !== null) {
        $where_clauses[] = 'e.archived = %d';
        $params[] = $archived_filter;
    }

    if (!empty($filters['categories']) && is_array($filters['categories'])) {
        $category_ids = array_values(array_unique(array_filter(array_map('absint', $filters['categories']))));
        if (!empty($category_ids)) {
            $placeholders = implode(', ', array_fill(0, count($category_ids), '%d'));
            $where_clauses[] = "e.category_id IN ($placeholders)";
            $params = array_merge($params, $category_ids);
        }
    }

    if (isset($filters['search']) && $filters['search'] !== '') {
        $like = '%' . $wpdb->esc_like($filters['search']) . '%';
        $where_clauses[] = '(e.was_angefragt LIKE %s OR e.name_hochschule LIKE %s OR e.wer_macht LIKE %s)';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $where_sql = '';
    if (!empty($where_clauses)) {
        $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
    }

    $sql = "SELECT e.*, c.name AS category_name
            FROM $events_table e
            LEFT JOIN $categories_table c ON c.id = e.category_id
            $where_sql
            ORDER BY e.created_at DESC";

    if (!empty($params)) {
        $prepare_args = array_merge([$sql], $params);
        $sql = call_user_func_array([$wpdb, 'prepare'], $prepare_args);
    }

    $rows = $wpdb->get_results($sql, ARRAY_A);
    $headers = ['id','was_angefragt','kontext','name_hochschule','tn_anzahl','zeitpunkt','auftragdetails','wer_macht','category_id','category_name','archived','created_at','updated_at'];

    if ($format === 'xml' || !class_exists('ZipArchive')) {
        $xml  = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<?mso-application progid=\"Excel.Sheet\"?>\n";
        $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet" xmlns:html="http://www.w3.org/TR/REC-html40">';
        $xml .= '<Worksheet ss:Name="Events"><Table><Row>';
        foreach ($headers as $h) { $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars($h, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</Data></Cell>'; }
        $xml .= '</Row>';
        foreach ($rows as $r) {
            $xml .= '<Row>';
            foreach ($headers as $h) {
                $val = isset($r[$h]) ? (string)$r[$h] : '';
                $val = preg_replace("/\r\n?|\n/", "\n", $val);
                $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars($val, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</Data></Cell>';
            }
            $xml .= '</Row>';
        }
        $xml .= '</Table></Worksheet></Workbook>';
        return [ 'content' => $xml, 'filename' => 'task-table-events.xls', 'content_type' => 'application/vnd.ms-excel; charset=UTF-8' ];
    }

    // Build XLSX
    $sheet_rows_xml = '';
    $rowIndex = 1; $colIndex = 1;
    $sheet_rows_xml .= '<row r="'.$rowIndex.'">';
    foreach ($headers as $h) {
        $sheet_rows_xml .= '<c r="'.task_table_xlsx_cell_ref($colIndex,$rowIndex).'" t="inlineStr"><is><t>'.task_table_xlsx_escape($h).'</t></is></c>';
        $colIndex++;
    }
    $sheet_rows_xml .= '</row>';
    $rowIndex++;
    foreach ($rows as $r) {
        $sheet_rows_xml .= '<row r="'.$rowIndex.'">';
        $colIndex = 1;
        foreach ($headers as $h) {
            $val = isset($r[$h]) ? (string)$r[$h] : '';
            $val = preg_replace("/\r\n?|\n/", "\n", $val);
            $sheet_rows_xml .= '<c r="'.task_table_xlsx_cell_ref($colIndex,$rowIndex).'" t="inlineStr"><is><t>'.task_table_xlsx_escape($val).'</t></is></c>';
            $colIndex++;
        }
        $sheet_rows_xml .= '</row>';
        $rowIndex++;
    }
    $sheet_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheetData>'.$sheet_rows_xml.'</sheetData></worksheet>';
    $workbook_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Events" sheetId="1" r:id="rId1"/></sheets></workbook>';
    $rels_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
    $workbook_rels_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
    $styles_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"></styleSheet>';
    $content_types_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>';
    if (!function_exists('wp_tempnam')) {
        require_once ABSPATH . 'wp-admin/includes/file.php'; // Not loaded on REST requests.
    }
    $tmp = wp_tempnam('task-table-xlsx');
    if (!$tmp) { return task_table_generate_events_spreadsheet($archived_param, 'xml', $filters); }
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) { return task_table_generate_events_spreadsheet($archived_param, 'xml', $filters); }
    $zip->addFromString('[Content_Types].xml', $content_types_xml);
    $zip->addEmptyDir('_rels');
    $zip->addFromString('_rels/.rels', $rels_xml);
    $zip->addEmptyDir('xl/_rels');
    $zip->addEmptyDir('xl/worksheets');
    $zip->addFromString('xl/workbook.xml', $workbook_xml);
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheet_xml);
    $zip->addFromString('xl/_rels/workbook.xml.rels', $workbook_rels_xml);
    $zip->addFromString('xl/styles.xml', $styles_xml);
    $zip->close();
    $content = file_get_contents($tmp);
    @unlink($tmp);
    return [ 'content' => $content, 'filename' => 'task-table-events.xlsx', 'content_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' ];
}

function task_table_xlsx_cell_ref($col, $row) {
    $letters = '';
    while ($col > 0) { $rem = ($col - 1) % 26; $letters = chr(65 + $rem) . $letters; $col = intval(($col - 1) / 26); }
    return $letters . $row;
}
function task_table_xlsx_escape($value) { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

/**
 * Generate the Excel XML (SpreadsheetML) for events export. Shared between REST and admin-post download.
 *
 * @param mixed $archived_param (string|int|null) Filter archived state: '1' for archived, '0' for active, anything else for all.
 * @return string XML content
 */
function task_table_generate_events_excel_xml($archived_param = null, $filters = []) {
    $sheet = task_table_generate_events_spreadsheet($archived_param, 'xml', $filters);
    return is_array($sheet) ? $sheet['content'] : $sheet;
}

/**
 * Admin-post handler to download events as Excel without relying on REST cookie auth quirks.
 */
function task_table_admin_post_export_events() {
    if (!is_user_logged_in() || !current_user_can('manage_task_table')) {
        wp_die(__('You do not have permission to export events.', 'task-table'), 403);
    }
    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'task_table_export_events')) {
        wp_die(__('Security check failed.', 'task-table'), 403);
    }
    $request_params = wp_unslash($_GET);
    $archived = isset($request_params['archived']) ? $request_params['archived'] : null;
    $format = isset($request_params['format']) ? sanitize_text_field($request_params['format']) : 'xlsx';
    if (!in_array($format, ['xlsx', 'xml'], true)) {
        $format = 'xlsx';
    }
    $filters = task_table_normalize_export_filters($request_params);
    $sheet = task_table_generate_events_spreadsheet($archived, $format, $filters);
    nocache_headers();
    header('Content-Type: ' . $sheet['content_type']);
    header('Content-Disposition: attachment; filename="' . $sheet['filename'] . '"');
    header('X-Content-Type-Options: nosniff');
    echo $sheet['content']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    exit;
}
add_action('admin_post_task_table_export_events', 'task_table_admin_post_export_events');
