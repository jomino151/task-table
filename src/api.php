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
        'permission_callback' => function() { return is_user_logged_in(); }
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
    $free_text = isset($data['free_text']) ? wp_kses_post($data['free_text']) : '';
    
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
        'free_text' => $free_text,
        'status' => 'pending',
        'created_at' => current_time('mysql')
    ));

    if ($result === false) {
        error_log('Database insert failed: ' . $wpdb->last_error);
        return new WP_REST_Response(array('success' => false, 'message' => 'Database insert failed'), 500);
    }

    // Send email to moderator using our notification system
    $data['free_text'] = $free_text;
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
function task_table_get_events(WP_REST_Request $request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'task_table_events';
    $archived = $request->get_param('archived');
    $where = '';
    if ($archived === '1' || $archived === 1) {
        $where = 'WHERE archived = 1';
    } elseif ($archived === '0' || $archived === 0) {
        $where = 'WHERE archived = 0';
    }
    $events = $wpdb->get_results("SELECT * FROM $table_name $where ORDER BY created_at DESC", ARRAY_A);
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
    // Allow any logged-in user to export (matches permission_callback)
    if (!is_user_logged_in()) {
        return new WP_REST_Response(['success' => false, 'message' => 'Forbidden'], 403);
    }
    global $wpdb;
    $table = $wpdb->prefix . 'task_table_events';
    $archived = $request->get_param('archived');
        $format = $request->get_param('format') ?: 'xlsx';
        $sheet = task_table_generate_events_spreadsheet($archived, $format);
        return new WP_REST_Response($sheet['content'], 200, [
            'Content-Type' => $sheet['content_type'],
            'Content-Disposition' => 'attachment; filename="'.$sheet['filename'].'"'
    ]);
}

/**
 * Generate events spreadsheet (XLSX or legacy XML) and return array with content, filename, content_type
 */
function task_table_generate_events_spreadsheet($archived_param = null, $format = 'xlsx') {
    global $wpdb;
    $table = $wpdb->prefix . 'task_table_events';
    $where = '';
    if ($archived_param === '1' || $archived_param === 1) { $where = 'WHERE archived = 1'; }
    elseif ($archived_param === '0' || $archived_param === 0) { $where = 'WHERE archived = 0'; }
    $rows = $wpdb->get_results("SELECT * FROM $table $where ORDER BY created_at DESC", ARRAY_A);
    $headers = ['id','was_angefragt','kontext','name_hochschule','tn_anzahl','zeitpunkt','auftragdetails','wer_macht','archived','created_at','updated_at'];

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
    $tmp = wp_tempnam('task-table-xlsx');
    if (!$tmp) { return task_table_generate_events_spreadsheet($archived_param,'xml'); }
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) { return task_table_generate_events_spreadsheet($archived_param,'xml'); }
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
function task_table_generate_events_excel_xml($archived_param = null) {
    global $wpdb;
    $table = $wpdb->prefix . 'task_table_events';
    $where = '';
    if ($archived_param === '1' || $archived_param === 1) { $where = 'WHERE archived = 1'; }
    elseif ($archived_param === '0' || $archived_param === 0) { $where = 'WHERE archived = 0'; }
    $rows = $wpdb->get_results("SELECT * FROM $table $where ORDER BY created_at DESC", ARRAY_A);

    $headers = ['id','was_angefragt','kontext','name_hochschule','tn_anzahl','zeitpunkt','auftragdetails','wer_macht','archived','created_at','updated_at'];

    $xml  = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    $xml .= "<?mso-application progid=\"Excel.Sheet\"?>\n";
    $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet" xmlns:html="http://www.w3.org/TR/REC-html40">';
    $xml .= '<Worksheet ss:Name="Events">';
    $xml .= '<Table>';
    $xml .= '<Row>';
    foreach ($headers as $h) {
        $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars($h, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</Data></Cell>';
    }
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
    $xml .= '</Table>';
    $xml .= '</Worksheet>';
    $xml .= '</Workbook>';
    return $xml;
}

/**
 * Admin-post handler to download events as Excel without relying on REST cookie auth quirks.
 */
function task_table_admin_post_export_events() {
    if (!is_user_logged_in() || !current_user_can('manage_task_table')) {
        wp_die(__('You do not have permission to export events.', 'task-table'), 403);
    }
    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field($_GET['_wpnonce']), 'task_table_export_events')) {
        wp_die(__('Security check failed.', 'task-table'), 403);
    }
    $archived = isset($_GET['archived']) ? sanitize_text_field($_GET['archived']) : null;
        $format = isset($_GET['format']) ? sanitize_text_field($_GET['format']) : 'xlsx';
        $sheet = task_table_generate_events_spreadsheet($archived, $format);
        nocache_headers();
        header('Content-Type: '.$sheet['content_type']);
        header('Content-Disposition: attachment; filename="'.$sheet['filename'].'"');
        header('X-Content-Type-Options: nosniff');
        echo $sheet['content']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    exit;
}
add_action('admin_post_task_table_export_events', 'task_table_admin_post_export_events');
