<?php
/**
 * Email notification system for Task Table plugin
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Get default email templates
 * 
 * @return array Array of email templates
 */
function task_table_get_email_templates() {
    return [
        'subscription_confirmation' => [
            'subject' => 'Bitte bestätige dein Abo – {site_name}',
            'message' => "Hallo,\n\nbitte bestätige, dass du Benachrichtigungen über neue Veranstaltungen erhalten möchtest.\nKlicke dazu auf den folgenden Link (oder kopiere ihn in deinen Browser):\n\n{confirmation_link}\n\nFalls du dich nicht angemeldet hast, kannst du diese E-Mail ignorieren.\n\nVielen Dank!\n{site_name}"
        ],
        'signup_approved' => [
            'subject' => 'Deine Anmeldung für "{event_title}" wurde angenommen',
            'message' => "Hallo {user_name},\n\ndeine Anmeldung für die Veranstaltung \"{event_title}\" wurde angenommen.\n\n{event_details}\n\nVielen Dank!\n{site_name}"
        ],
        'signup_denied' => [
            'subject' => 'Aktualisierung zu deiner Anmeldung für "{event_title}"',
            'message' => "Hallo {user_name},\n\nleider können wir deine Anmeldung für die Veranstaltung \"{event_title}\" derzeit nicht annehmen.\n\n{event_details}\n\nVielen Dank für dein Interesse.\n{site_name}"
        ],
        'new_signup' => [
            'subject' => 'Neue Veranstaltungs-Anmeldung',
            'message' => "Es ist eine neue Anmeldung eingegangen:\n\nEvent-ID: {event_id}\nName: {user_name}\nE-Mail: {user_email}\n{free_text_section}\nBitte prüfe diese Anfrage im Administrationsbereich."
        ],
        'new_event' => [
            'subject' => 'Neue Veranstaltung veröffentlicht: {event_title}',
            'message' => "Es wurde eine neue Veranstaltung veröffentlicht:\n\n{event_details}\n\nViele Grüße\n{site_name}"
        ]
    ];
}

/**
 * Get email template content with placeholders replaced
 * 
 * @param string $template_key The template key
 * @param array $data Data to replace placeholders
 * @return array The processed email template
 */
function task_table_get_email_content($template_key, $data = []) {
    $templates = task_table_get_email_templates();
    
    if (!isset($templates[$template_key])) {
        return false;
    }
    
    $template = $templates[$template_key];
    
    // Get stored custom templates if available
    $custom_subject = get_option("task_table_email_{$template_key}_subject", '');
    $custom_message = get_option("task_table_email_{$template_key}_message", '');
    
    if (!empty($custom_subject)) {
        $template['subject'] = $custom_subject;
    }
    
    if (!empty($custom_message)) {
        $template['message'] = $custom_message;
    }
    
    // Add site name to data
    $data['site_name'] = get_bloginfo('name');
    
    // Replace placeholders
    foreach ($data as $key => $value) {
        $template['subject'] = str_replace("{{$key}}", $value, $template['subject']);
        $template['message'] = str_replace("{{$key}}", $value, $template['message']);
    }
    
    return $template;
}

/**
 * Send email notification
 * 
 * @param string $to Recipient email
 * @param string $template_key Template key
 * @param array $data Data for email content
 * @return bool Whether the email was sent
 */
function task_table_send_notification($to, $template_key, $data = []) {
    $email = task_table_get_email_content($template_key, $data);
    
    if (!$email) {
        return false;
    }
    
    $headers = ['Content-Type: text/plain; charset=UTF-8'];
    
    return wp_mail($to, $email['subject'], $email['message'], $headers);
}

/**
 * Send a subscription confirmation email containing the confirmation link
 */
function task_table_send_subscription_confirmation_email($email, $token) {
    $link = add_query_arg([
        'task_table_confirm_subscription' => '1',
        'token' => $token,
        'email' => rawurlencode($email)
    ], site_url('/'));

    return task_table_send_notification($email, 'subscription_confirmation', [
        'confirmation_link' => $link
    ]);
}

/**
 * Get event details by event ID - Updated to use database table
 * 
 * @param int $event_id The event ID
 * @return array|bool Event details array or false if not found
 */
function task_table_get_event_details($event_id) {
    global $wpdb;
    $events_table = $wpdb->prefix . 'task_table_events';
    
    $event = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $events_table WHERE id = %d",
        $event_id
    ), ARRAY_A);
    
    if (!$event) {
        error_log('Could not find event data for event ID ' . $event_id);
        return false;
    }
    
    // Convert database fields to match expected format for compatibility
    return [
        'id' => $event['id'],
        'wasAngefragt' => $event['was_angefragt'],
        'kontext' => $event['kontext'],
        'nameHochschule' => $event['name_hochschule'],
        'tnAnzahl' => $event['tn_anzahl'],
        'zeitpunkt' => $event['zeitpunkt'],
        'auftragdetails' => $event['auftragdetails'],
        'werMacht' => $event['wer_macht'],
        'category_id' => isset($event['category_id']) ? (int) $event['category_id'] : 0
    ];
}

/**
 * Format event details for email
 * 
 * @param array $event_data The event data
 * @return string Formatted event details
 */
function task_table_format_event_details($event_data) {
    if (!$event_data) {
        return 'Veranstaltungsdetails nicht verfügbar';
    }
    
    $details = "Veranstaltungsinformationen:\n";
    
    if (!empty($event_data['wasAngefragt'])) {
        $details .= "Anfrage: " . strip_tags(wp_unslash($event_data['wasAngefragt'])) . "\n";
    }
    
    if (!empty($event_data['kontext'])) {
        $details .= "Kontext: " . strip_tags(wp_unslash($event_data['kontext'])) . "\n";
    }
    
    if (!empty($event_data['nameHochschule'])) {
            $details .= "Hochschule: " . strip_tags(wp_unslash($event_data['nameHochschule'])) . "\n";
    }
    
    if (!empty($event_data['tnAnzahl'])) {
        $details .= "Teilnehmerzahl: " . strip_tags(wp_unslash($event_data['tnAnzahl'])) . "\n";
    }
    
    if (!empty($event_data['zeitpunkt'])) {
            $details .= "Zeit: " . strip_tags(wp_unslash($event_data['zeitpunkt'])) . "\n";
    }
    
    if (!empty($event_data['auftragdetails'])) {
        $details .= "Auftragsdetails: " . strip_tags(wp_unslash($event_data['auftragdetails'])) . "\n";
    }

    $category_id = 0;
    if (isset($event_data['category_id'])) {
        $category_id = (int) $event_data['category_id'];
    } elseif (isset($event_data['categoryId'])) {
        $category_id = (int) $event_data['categoryId'];
    }

    if ($category_id) {
        $category = task_table_get_category($category_id);
        if ($category) {
                $details .= "Kategorie: " . $category['name'] . "\n";
        }
    }
    
    return $details;
}

/**
 * Send signup approval notification
 * 
 * @param object $signup The signup database object
 * @return bool Whether the email was sent
 */
function task_table_send_approval_notification($signup) {
    // Get event details from database
    $event_data = task_table_get_event_details($signup->event_id);
    $event_details = task_table_format_event_details($event_data);
    
    // Add event title if available
    $event_title = !empty($event_data['wasAngefragt']) ? 
    strip_tags(wp_unslash($event_data['wasAngefragt'])) : 
    "Veranstaltung #" . $signup->event_id;
    
    // Log for debugging
    error_log('Sending approval email for event: ' . $event_title);
    
    return task_table_send_notification(
        $signup->user_email,
        'signup_approved',
        [
            'user_name' => $signup->user_name,
            'event_id' => $signup->event_id,
            'event_details' => $event_details,
            'event_title' => $event_title
        ]
    );
}

/**
 * Send signup denial notification
 * 
 * @param object $signup The signup database object
 * @return bool Whether the email was sent
 */
function task_table_send_denial_notification($signup) {
    // Get event details from database
    $event_data = task_table_get_event_details($signup->event_id);
    $event_title = !empty($event_data['wasAngefragt']) ? 
    strip_tags(wp_unslash($event_data['wasAngefragt'])) :
    "Veranstaltung #" . $signup->event_id;
    
    // Include basic event details in denial emails too
    $event_details = "";
    if (!empty($event_data['nameHochschule'])) {
    $event_details .= "Hochschule: " . strip_tags(wp_unslash($event_data['nameHochschule'])) . "\n";
    }
    if (!empty($event_data['zeitpunkt'])) {
    $event_details .= "Zeit: " . strip_tags(wp_unslash($event_data['zeitpunkt'])) . "\n";
    }
    
    return task_table_send_notification(
        $signup->user_email,
        'signup_denied',
        [
            'user_name' => $signup->user_name,
            'event_id' => $signup->event_id,
            'event_title' => $event_title,
            'event_details' => $event_details
        ]
    );
}

/**
 * Send new signup notification to admin
 * 
 * @param array $signup_data The signup data
 * @return bool Whether the email was sent
 */
function task_table_send_new_signup_notification($signup_data) {
    $moderator_email = get_option('moderator_email', get_option('admin_email'));
    $free_text_section = '';
    if (!empty($signup_data['free_text'])) {
        $clean = trim(wp_strip_all_tags(wp_unslash($signup_data['free_text'])));
        if ($clean !== '') {
                $free_text_section = "Zusätzliche Hinweise:\n" . $clean . "\n\n";
        }
    }
    return task_table_send_notification(
        $moderator_email,
        'new_signup',
        [
            'user_name' => $signup_data['user_name'],
            'user_email' => $signup_data['user_email'],
            'event_id' => $signup_data['event_id'],
            'free_text_section' => $free_text_section
        ]
    );
}

/**
 * Send notification for new events to subscribers
 * 
 * @param array $event_data The event data
 * @return array Result of the notification process
 */
function task_table_send_new_event_notification($event_data) {
    global $wpdb;
    $subscribers_table = $wpdb->prefix . 'task_table_subscribers';
    
    // Normalize event data (support DB and frontend formats)
    $normalized_event = $event_data;
    if (isset($event_data['was_angefragt']) && !isset($event_data['wasAngefragt'])) {
        $normalized_event = [
            'id' => $event_data['id'],
            'wasAngefragt' => $event_data['was_angefragt'],
            'kontext' => $event_data['kontext'],
            'nameHochschule' => $event_data['name_hochschule'],
            'tnAnzahl' => $event_data['tn_anzahl'],
            'zeitpunkt' => $event_data['zeitpunkt'],
            'auftragdetails' => $event_data['auftragdetails'],
            'werMacht' => $event_data['wer_macht'],
            'category_id' => isset($event_data['category_id']) ? (int) $event_data['category_id'] : 0
        ];
    }

    // Build recipient list filtered by category subscription
    $recipient_emails = [];
    $subscriber_categories_table = $wpdb->prefix . 'task_table_subscriber_categories';
    $events_table = $wpdb->prefix . 'task_table_events';

    $event_category_id = 0;
    if (isset($normalized_event['category_id'])) {
        $event_category_id = (int) $normalized_event['category_id'];
    } elseif (isset($normalized_event['categoryId'])) {
        $event_category_id = (int) $normalized_event['categoryId'];
    }

    if (!$event_category_id && !empty($normalized_event['id'])) {
        $event_category_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT category_id FROM $events_table WHERE id = %d",
            (int) $normalized_event['id']
        ));
    }

    if (!$event_category_id) {
        $event_category_id = task_table_get_default_category_id();
    }

    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $subscribers_table)) === $subscribers_table) {
        $recipient_query = "SELECT email FROM $subscribers_table WHERE confirmed = 1 AND email <> ''";

        if ($event_category_id && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $subscriber_categories_table)) === $subscriber_categories_table) {
            $recipient_query = $wpdb->prepare(
                "SELECT s.email FROM $subscribers_table s
                 INNER JOIN $subscriber_categories_table sc ON sc.subscriber_id = s.id
                 WHERE s.confirmed = 1 AND sc.category_id = %d AND s.email <> ''",
                $event_category_id
            );
        }

        $recipient_emails = $wpdb->get_col($recipient_query);
    }

    // Deduplicate emails (case-insensitive)
    $unique_map = [];
    foreach ($recipient_emails as $e) {
        $key = strtolower(trim($e));
        if ($key !== '' && is_email($e)) {
            $unique_map[$key] = $e; // preserve original casing
        }
    }
    $recipient_emails = array_values($unique_map);

    // Nothing to do
    if (empty($recipient_emails)) {
        return [
            'success' => true,
            'message' => 'Keine Abonnenten zu benachrichtigen',
            'notification_sent' => false,
            'subscriber_count' => 0
        ];
    }

        // Prepare email contents
        $event_title = !empty($normalized_event['wasAngefragt']) ?
            strip_tags($normalized_event['wasAngefragt']) :
            'Neue Veranstaltung #' . ($normalized_event['id'] ?? '');

    $event_details = task_table_format_event_details($normalized_event);

    // Send emails
    $sent_count = 0;
    foreach ($recipient_emails as $to) {
        $sent = task_table_send_notification(
            $to,
            'new_event',
            [
                'event_title' => $event_title,
                'event_details' => $event_details,
                'event_id' => $normalized_event['id'] ?? 0
            ]
        );
        if ($sent) {
            $sent_count++;
        }
    }

        return [
            'success' => true,
            'message' => 'Benachrichtigung an ' . $sent_count . ' Abonnenten gesendet',
            'notification_sent' => ($sent_count > 0),
            'subscriber_count' => $sent_count
        ];
}

/**
 * Send notification when a new signup is received
 */
function task_table_send_signup_notification($signup_data) {
    $notification_emails = get_option(TASK_TABLE_SIGNUP_NOTIFICATIONS_OPTION, array());
    
    if (empty($notification_emails)) {
        return false;
    }
    
    $event_id = isset($signup_data['event_id']) ? intval($signup_data['event_id']) : 0;
    $user_name = isset($signup_data['user_name']) ? sanitize_text_field($signup_data['user_name']) : 'Unknown';
    $user_email = isset($signup_data['user_email']) ? sanitize_email($signup_data['user_email']) : 'Unknown';
    
    // Get event details if available
    global $wpdb;
    $events_table = $wpdb->prefix . 'task_table_events';
    $event = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $events_table WHERE id = %d",
        $event_id
    ));
    
    $event_title = $event ? $event->was_angefragt : 'Unknown Event';
    
    $subject = sprintf('New signup received for event: %s', $event_title);
    
    $message = sprintf(
        "Hello,\n\nA new signup has been received for the event.\n\n" .
        "Event Details:\n" .
        "- Event: %s\n" .
        "- Event ID: %d\n\n" .
        "Signup Details:\n" .
        "- Name: %s\n" .
        "- Email: %s\n\n" .
        "You can review this signup in the WordPress admin area.\n\n" .
        "Regards,\n" .
        "Task Table Plugin",
        $event_title,
        $event_id,
        $user_name,
        $user_email
    );
    
    $headers = array('Content-Type: text/plain; charset=UTF-8');
    
    // Send email to each notification recipient
    $sent = false;
    foreach ($notification_emails as $email) {
        $result = wp_mail($email, $subject, $message, $headers);
        if ($result) {
            $sent = true;
        }
    }
    
    return $sent;
}

/**
 * Hook to send notifications when a new signup is processed
 */
function task_table_process_new_signup($data, $status) {
    // Only send notification for new signups
    if ($status === 'pending') {
        task_table_send_signup_notification($data);
    }
    
    return $data;
}
add_filter('task_table_process_signup', 'task_table_process_new_signup', 10, 2);
