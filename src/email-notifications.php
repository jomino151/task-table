<?php
/**
 * Email notification system for Task Table plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Get default email templates
 *
 * @return array Array of email templates
 */
function task_table_get_email_templates() {
	return array(
		'subscription_confirmation' => array(
			'subject' => __( 'Please confirm your subscription – {site_name}', 'task-table' ),
			'message' => __( "Hello,\n\nplease confirm that you want to receive notifications about new events.\nTo do so, click the link below (or copy it into your browser):\n\n{confirmation_link}\n\nIf you did not sign up, you can ignore this email.\n\nThank you!\n{site_name}", 'task-table' ),
		),
		'subscription_manage'       => array(
			'subject' => __( 'Manage your subscription – {site_name}', 'task-table' ),
			'message' => __( "Hello,\n\nthis email address is already subscribed. Use the link below to change your categories or unsubscribe:\n\n{manage_link}\n\nIf you did not request this, you can ignore this email.\n\n{site_name}", 'task-table' ),
		),
		'signup_approved'           => array(
			'subject' => __( 'Your signup for "{event_title}" has been approved', 'task-table' ),
			'message' => __( "Hello {user_name},\n\nyour signup for the event \"{event_title}\" has been approved.\n\n{event_details}\n\nThank you!\n{site_name}", 'task-table' ),
		),
		'signup_denied'             => array(
			'subject' => __( 'Update on your signup for "{event_title}"', 'task-table' ),
			'message' => __( "Hello {user_name},\n\nunfortunately we cannot accept your signup for the event \"{event_title}\" at this time.\n\n{event_details}\n\nThank you for your interest.\n{site_name}", 'task-table' ),
		),
		'new_signup'                => array(
			'subject' => __( 'New event signup', 'task-table' ),
			'message' => __( "A new signup has arrived:\n\nEvent ID: {event_id}\n{event_category_line}Name: {user_name}\nEmail: {user_email}\n{free_text_section}\nPlease review this request in the admin area.", 'task-table' ),
		),
		'new_event'                 => array(
			'subject' => __( 'New event published: {event_title}', 'task-table' ),
			'message' => __( "A new event has been published:\n\n{event_details}\n\nBest regards\n{site_name}\n\n--\nManage or cancel your subscription: {manage_link}", 'task-table' ),
		),
	);
}

/**
 * Get email template content with placeholders replaced
 *
 * @param string $template_key The template key
 * @param array $data Data to replace placeholders
 * @return array The processed email template
 */
function task_table_get_email_content( $template_key, $data = array() ) {
	$templates = task_table_get_email_templates();

	if ( ! isset( $templates[ $template_key ] ) ) {
		return false;
	}

	$template = $templates[ $template_key ];

	// Get stored custom templates if available
	$custom_subject = get_option( "task_table_email_{$template_key}_subject", '' );
	$custom_message = get_option( "task_table_email_{$template_key}_message", '' );

	if ( ! empty( $custom_subject ) ) {
		$template['subject'] = $custom_subject;
	}

	if ( ! empty( $custom_message ) ) {
		$template['message'] = $custom_message;
	}

	// Add site name to data
	$data['site_name'] = get_bloginfo( 'name' );

	// Replace placeholders
	foreach ( $data as $key => $value ) {
		$template['subject'] = str_replace( "{{$key}}", $value, $template['subject'] );
		$template['message'] = str_replace( "{{$key}}", $value, $template['message'] );
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
function task_table_send_notification( $to, $template_key, $data = array(), $extra_headers = array() ) {
	$email = task_table_get_email_content( $template_key, $data );

	if ( ! $email ) {
		return false;
	}

	// Custom templates saved before {manage_link} existed must still carry the unsubscribe link.
	if ( ! empty( $data['manage_link'] ) && strpos( $email['message'], (string) $data['manage_link'] ) === false ) {
		$email['message'] .= "\n\n--\n" . __( 'Manage or cancel your subscription:', 'task-table' ) . ' ' . $data['manage_link'];
	}

	$headers = array_merge( array( 'Content-Type: text/plain; charset=UTF-8' ), $extra_headers );

	return wp_mail( $to, $email['subject'], $email['message'], $headers );
}

/**
 * Email an already subscribed address its management link (used when it subscribes again).
 */
function task_table_send_subscription_manage_email( $subscriber_id, $email ) {
	return task_table_send_notification(
		$email,
		'subscription_manage',
		array(
			'manage_link' => task_table_get_subscription_manage_url( $subscriber_id, $email ),
		)
	);
}

/**
 * Send a subscription confirmation email containing the confirmation link
 */
function task_table_send_subscription_confirmation_email( $email, $token ) {
	$link = add_query_arg(
		array(
			'task_table_confirm_subscription' => '1',
			'token'                           => $token,
			'email'                           => rawurlencode( $email ),
		),
		site_url( '/' )
	);

	return task_table_send_notification(
		$email,
		'subscription_confirmation',
		array(
			'confirmation_link' => $link,
		)
	);
}

/**
 * Get event details by event ID - Updated to use database table
 *
 * @param int $event_id The event ID
 * @return array|bool Event details array or false if not found
 */
function task_table_get_event_details( $event_id ) {
	global $wpdb;
	$events_table = $wpdb->prefix . 'task_table_events';

	$event = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM $events_table WHERE id = %d",
			$event_id
		),
		ARRAY_A
	);

	if ( ! $event ) {
		error_log( 'Could not find event data for event ID ' . $event_id ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- operational log for site admins.
		return false;
	}

	// Convert database fields to match expected format for compatibility
	return array(
		'id'             => $event['id'],
		'wasAngefragt'   => $event['was_angefragt'],
		'kontext'        => $event['kontext'],
		'nameHochschule' => $event['name_hochschule'],
		'tnAnzahl'       => $event['tn_anzahl'],
		'zeitpunkt'      => $event['zeitpunkt'],
		'auftragdetails' => $event['auftragdetails'],
		'werMacht'       => $event['wer_macht'],
		'category_id'    => isset( $event['category_id'] ) ? (int) $event['category_id'] : 0,
	);
}

/**
 * Format event details for email
 *
 * @param array $event_data The event data
 * @return string Formatted event details
 */
function task_table_email_label( $column ) {
	$labels = task_table_get_column_labels();
	$label  = isset( $labels[ $column ] ) ? $labels[ $column ] : $column;
	return rtrim( wp_strip_all_tags( $label ), ' ?:' ) . ': ';
}

function task_table_format_event_details( $event_data ) {
	if ( ! $event_data ) {
		return __( 'Event details not available', 'task-table' );
	}

	$details = __( 'Event information:', 'task-table' ) . "\n";

	if ( ! empty( $event_data['wasAngefragt'] ) ) {
		$details .= task_table_email_label( 'was_angefragt' ) . wp_strip_all_tags( wp_unslash( $event_data['wasAngefragt'] ) ) . "\n";
	}

	if ( ! empty( $event_data['kontext'] ) ) {
		$details .= task_table_email_label( 'kontext' ) . wp_strip_all_tags( wp_unslash( $event_data['kontext'] ) ) . "\n";
	}

	if ( ! empty( $event_data['nameHochschule'] ) ) {
			$details .= task_table_email_label( 'name_hochschule' ) . wp_strip_all_tags( wp_unslash( $event_data['nameHochschule'] ) ) . "\n";
	}

	if ( ! empty( $event_data['tnAnzahl'] ) ) {
		$details .= task_table_email_label( 'tn_anzahl' ) . wp_strip_all_tags( wp_unslash( $event_data['tnAnzahl'] ) ) . "\n";
	}

	if ( ! empty( $event_data['zeitpunkt'] ) ) {
			$details .= task_table_email_label( 'zeitpunkt' ) . wp_strip_all_tags( wp_unslash( $event_data['zeitpunkt'] ) ) . "\n";
	}

	if ( ! empty( $event_data['auftragdetails'] ) ) {
		$details .= task_table_email_label( 'auftragdetails' ) . wp_strip_all_tags( wp_unslash( $event_data['auftragdetails'] ) ) . "\n";
	}

	$category_id = 0;
	if ( isset( $event_data['category_id'] ) ) {
		$category_id = (int) $event_data['category_id'];
	} elseif ( isset( $event_data['categoryId'] ) ) {
		$category_id = (int) $event_data['categoryId'];
	}

	if ( $category_id ) {
		$category = task_table_get_category( $category_id );
		if ( $category ) {
				$details .= __( 'Category', 'task-table' ) . ': ' . $category['name'] . "\n";
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
function task_table_send_approval_notification( $signup ) {
	// Get event details from database
	$event_data    = task_table_get_event_details( $signup->event_id );
	$event_details = task_table_format_event_details( $event_data );

	// Add event title if available
	$event_title = ! empty( $event_data['wasAngefragt'] ) ?
	wp_strip_all_tags( wp_unslash( $event_data['wasAngefragt'] ) ) :
	sprintf( /* translators: %d: event ID */ __( 'Event #%d', 'task-table' ), $signup->event_id );

	// Log for debugging
	error_log( 'Sending approval email for event: ' . $event_title ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- operational log for site admins.

	return task_table_send_notification(
		$signup->user_email,
		'signup_approved',
		array(
			'user_name'     => $signup->user_name,
			'event_id'      => $signup->event_id,
			'event_details' => $event_details,
			'event_title'   => $event_title,
		)
	);
}

/**
 * Send signup denial notification
 *
 * @param object $signup The signup database object
 * @return bool Whether the email was sent
 */
function task_table_send_denial_notification( $signup ) {
	// Get event details from database
	$event_data  = task_table_get_event_details( $signup->event_id );
	$event_title = ! empty( $event_data['wasAngefragt'] ) ?
	wp_strip_all_tags( wp_unslash( $event_data['wasAngefragt'] ) ) :
	sprintf( /* translators: %d: event ID */ __( 'Event #%d', 'task-table' ), $signup->event_id );

	// Include basic event details in denial emails too
	$event_details = '';
	if ( ! empty( $event_data['nameHochschule'] ) ) {
		$event_details .= task_table_email_label( 'name_hochschule' ) . wp_strip_all_tags( wp_unslash( $event_data['nameHochschule'] ) ) . "\n";
	}
	if ( ! empty( $event_data['zeitpunkt'] ) ) {
		$event_details .= task_table_email_label( 'zeitpunkt' ) . wp_strip_all_tags( wp_unslash( $event_data['zeitpunkt'] ) ) . "\n";
	}

	return task_table_send_notification(
		$signup->user_email,
		'signup_denied',
		array(
			'user_name'     => $signup->user_name,
			'event_id'      => $signup->event_id,
			'event_title'   => $event_title,
			'event_details' => $event_details,
		)
	);
}

/**
 * Return the option name used for signup notification recipient preferences.
 *
 * @return string
 */
function task_table_get_signup_notification_recipients_option_name() {
	if ( defined( 'TASK_TABLE_SIGNUP_NOTIFICATION_RECIPIENTS_OPTION' ) ) {
		return TASK_TABLE_SIGNUP_NOTIFICATION_RECIPIENTS_OPTION;
	}

	return 'task_table_signup_notification_recipients';
}

/**
 * Normalize recipient preferences for signup notifications.
 *
 * Expected shape per recipient:
 * [
 *   'email' => 'person@example.com',
 *   'category_ids' => [1, 2] // empty means all categories
 * ]
 *
 * @param mixed $raw_recipients Raw recipients from option or request payload.
 * @return array<int, array<string, mixed>>
 */
function task_table_normalize_signup_notification_recipients( $raw_recipients ) {
	$valid_category_ids = array();
	foreach ( task_table_get_categories() as $category ) {
		$valid_category_ids[ (int) $category['id'] ] = true;
	}

	$normalized = array();
	foreach ( (array) $raw_recipients as $recipient ) {
		if ( ! is_array( $recipient ) ) {
			continue;
		}

		$email = isset( $recipient['email'] ) ? sanitize_email( wp_unslash( $recipient['email'] ) ) : '';
		if ( ! is_email( $email ) ) {
			continue;
		}

		$raw_category_ids = array();
		if ( isset( $recipient['category_ids'] ) && is_array( $recipient['category_ids'] ) ) {
			$raw_category_ids = $recipient['category_ids'];
		} elseif ( isset( $recipient['categories'] ) && is_array( $recipient['categories'] ) ) {
			// Backward/alternate payload key support.
			$raw_category_ids = $recipient['categories'];
		}

		$all_categories = ! empty( $recipient['all_categories'] );
		if ( ! $all_categories && ! isset( $recipient['all_categories'] ) && empty( $raw_category_ids ) ) {
			$all_categories = true;
		}

		$clean_category_ids = array();
		if ( ! $all_categories ) {
			foreach ( $raw_category_ids as $category_id ) {
				$category_id = (int) $category_id;
				if ( $category_id > 0 && isset( $valid_category_ids[ $category_id ] ) ) {
					$clean_category_ids[] = $category_id;
				}
			}
			$clean_category_ids = array_values( array_unique( $clean_category_ids ) );
		}

		$normalized[ strtolower( $email ) ] = array(
			'email'        => $email,
			'category_ids' => $clean_category_ids,
		);
	}

	return array_values( $normalized );
}

/**
 * Read legacy plain-email options used before category-based filtering.
 *
 * @return string[]
 */
function task_table_get_legacy_signup_notification_emails() {
	$emails = array();

	$moderator_email = get_option( 'moderator_email', '' );
	if ( ! empty( $moderator_email ) && is_string( $moderator_email ) ) {
		$raw_values = str_replace( array( "\r\n", "\r" ), "\n", $moderator_email );
		foreach ( explode( "\n", str_replace( ',', "\n", $raw_values ) ) as $candidate ) {
			$candidate = sanitize_email( trim( $candidate ) );
			if ( is_email( $candidate ) ) {
				$emails[] = $candidate;
			}
		}
	}

	if ( defined( 'TASK_TABLE_SIGNUP_NOTIFICATIONS_OPTION' ) ) {
		$legacy_option = get_option( TASK_TABLE_SIGNUP_NOTIFICATIONS_OPTION, array() );
		if ( is_array( $legacy_option ) ) {
			foreach ( $legacy_option as $candidate ) {
				$candidate = sanitize_email( trim( (string) $candidate ) );
				if ( is_email( $candidate ) ) {
					$emails[] = $candidate;
				}
			}
		}
	}

	if ( empty( $emails ) ) {
		$admin_email = sanitize_email( get_option( 'admin_email' ) );
		if ( is_email( $admin_email ) ) {
			$emails[] = $admin_email;
		}
	}

	$deduplicated = array();
	foreach ( $emails as $email ) {
		$deduplicated[ strtolower( $email ) ] = $email;
	}

	return array_values( $deduplicated );
}

/**
 * Get signup notification recipients with normalized category targeting.
 *
 * @return array<int, array<string, mixed>>
 */
function task_table_get_signup_notification_recipients() {
	$option_name  = task_table_get_signup_notification_recipients_option_name();
	$stored_value = get_option( $option_name, null );
	if ( $stored_value !== null ) {
		return task_table_normalize_signup_notification_recipients( $stored_value );
	}

	$fallback = array();
	foreach ( task_table_get_legacy_signup_notification_emails() as $email ) {
		$fallback[] = array(
			'email'        => $email,
			'category_ids' => array(),
		);
	}

	$normalized_fallback = task_table_normalize_signup_notification_recipients( $fallback );
	update_option( $option_name, $normalized_fallback );

	return $normalized_fallback;
}

/**
 * Persist signup notification recipients and keep legacy options in sync.
 *
 * @param mixed $raw_recipients Raw recipient payload.
 * @return array<int, array<string, mixed>>
 */
function task_table_save_signup_notification_recipients( $raw_recipients ) {
	$normalized  = task_table_normalize_signup_notification_recipients( $raw_recipients );
	$option_name = task_table_get_signup_notification_recipients_option_name();
	update_option( $option_name, $normalized );

	$emails = array();
	foreach ( $normalized as $recipient ) {
		if ( ! empty( $recipient['email'] ) && is_email( $recipient['email'] ) ) {
			$emails[] = $recipient['email'];
		}
	}

	update_option( 'moderator_email', implode( ',', $emails ) );

	if ( defined( 'TASK_TABLE_SIGNUP_NOTIFICATIONS_OPTION' ) ) {
		update_option( TASK_TABLE_SIGNUP_NOTIFICATIONS_OPTION, $emails );
	}

	return $normalized;
}

/**
 * Resolve signup notification emails matching a category.
 *
 * Empty category list on recipient means: all categories.
 *
 * @param int $category_id Event category ID.
 * @return string[]
 */
function task_table_get_signup_notification_emails_for_category( $category_id ) {
	$category_id = (int) $category_id;
	$emails      = array();

	foreach ( task_table_get_signup_notification_recipients() as $recipient ) {
		$recipient_email = isset( $recipient['email'] ) ? sanitize_email( $recipient['email'] ) : '';
		if ( ! is_email( $recipient_email ) ) {
			continue;
		}

		$recipient_category_ids = isset( $recipient['category_ids'] ) ? array_map( 'intval', (array) $recipient['category_ids'] ) : array();
		$matches_category       = empty( $recipient_category_ids ) || $category_id <= 0 || in_array( $category_id, $recipient_category_ids, true );

		if ( $matches_category ) {
			$emails[ strtolower( $recipient_email ) ] = $recipient_email;
		}
	}

	return array_values( $emails );
}

/**
 * Resolve event category ID from signup payload and database fallback.
 *
 * @param array $signup_data Signup payload.
 * @return int
 */
function task_table_get_signup_notification_event_category_id( $signup_data ) {
	$category_id = 0;
	if ( isset( $signup_data['category_id'] ) ) {
		$category_id = (int) $signup_data['category_id'];
	} elseif ( isset( $signup_data['categoryId'] ) ) {
		$category_id = (int) $signup_data['categoryId'];
	}

	if ( $category_id > 0 ) {
		return $category_id;
	}

	$event_id = isset( $signup_data['event_id'] ) ? (int) $signup_data['event_id'] : 0;
	if ( $event_id <= 0 ) {
		return task_table_get_default_category_id();
	}

	global $wpdb;
	$events_table = $wpdb->prefix . 'task_table_events';
	$category_id  = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT category_id FROM $events_table WHERE id = %d",
			$event_id
		)
	);

	if ( $category_id > 0 ) {
		return $category_id;
	}

	return task_table_get_default_category_id();
}

/**
 * Send new signup notification to admin
 *
 * @param array $signup_data The signup data
 * @return bool Whether the email was sent
 */
function task_table_send_new_signup_notification( $signup_data ) {
	$event_category_id = task_table_get_signup_notification_event_category_id( $signup_data );
	$recipient_emails  = task_table_get_signup_notification_emails_for_category( $event_category_id );

	if ( empty( $recipient_emails ) ) {
		return false;
	}

	$event_category_line = '';
	if ( $event_category_id > 0 ) {
		$category = task_table_get_category( $event_category_id );
		if ( ! empty( $category['name'] ) ) {
			$event_category_line = __( 'Category', 'task-table' ) . ': ' . $category['name'] . "\n";
		}
	}

	$free_text_section = '';
	if ( ! empty( $signup_data['free_text'] ) ) {
		$clean = trim( wp_strip_all_tags( wp_unslash( $signup_data['free_text'] ) ) );
		if ( $clean !== '' ) {
				$free_text_section = __( 'Additional notes:', 'task-table' ) . "\n" . $clean . "\n\n";
		}
	}

	$sent = false;
	foreach ( $recipient_emails as $email ) {
		$result = task_table_send_notification(
			$email,
			'new_signup',
			array(
				'user_name'           => $signup_data['user_name'],
				'user_email'          => $signup_data['user_email'],
				'event_id'            => $signup_data['event_id'],
				'event_category_line' => $event_category_line,
				'free_text_section'   => $free_text_section,
			)
		);

		if ( $result ) {
			$sent = true;
		}
	}

	return $sent;
}

/**
 * Send notification for new events to subscribers
 *
 * @param array $event_data The event data
 * @return array Result of the notification process
 */
function task_table_send_new_event_notification( $event_data ) {
	global $wpdb;
	$subscribers_table = $wpdb->prefix . 'task_table_subscribers';

	// Normalize event data (support DB and frontend formats)
	$normalized_event = $event_data;
	if ( isset( $event_data['was_angefragt'] ) && ! isset( $event_data['wasAngefragt'] ) ) {
		$normalized_event = array(
			'id'             => $event_data['id'],
			'wasAngefragt'   => $event_data['was_angefragt'],
			'kontext'        => $event_data['kontext'],
			'nameHochschule' => $event_data['name_hochschule'],
			'tnAnzahl'       => $event_data['tn_anzahl'],
			'zeitpunkt'      => $event_data['zeitpunkt'],
			'auftragdetails' => $event_data['auftragdetails'],
			'werMacht'       => $event_data['wer_macht'],
			'category_id'    => isset( $event_data['category_id'] ) ? (int) $event_data['category_id'] : 0,
		);
	}

	// Build recipient list filtered by category subscription
	$recipient_emails            = array();
	$subscriber_categories_table = $wpdb->prefix . 'task_table_subscriber_categories';
	$events_table                = $wpdb->prefix . 'task_table_events';

	$event_category_id = 0;
	if ( isset( $normalized_event['category_id'] ) ) {
		$event_category_id = (int) $normalized_event['category_id'];
	} elseif ( isset( $normalized_event['categoryId'] ) ) {
		$event_category_id = (int) $normalized_event['categoryId'];
	}

	if ( ! $event_category_id && ! empty( $normalized_event['id'] ) ) {
		$event_category_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT category_id FROM $events_table WHERE id = %d",
				(int) $normalized_event['id']
			)
		);
	}

	if ( ! $event_category_id ) {
		$event_category_id = task_table_get_default_category_id();
	}

	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $subscribers_table ) ) === $subscribers_table ) {
		$recipient_query = "SELECT id, email FROM $subscribers_table WHERE confirmed = 1 AND email <> ''";

		if ( $event_category_id && $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $subscriber_categories_table ) ) === $subscriber_categories_table ) {
			$recipient_query = $wpdb->prepare(
				"SELECT s.id, s.email FROM $subscribers_table s
                 INNER JOIN $subscriber_categories_table sc ON sc.subscriber_id = s.id
                 WHERE s.confirmed = 1 AND sc.category_id = %d AND s.email <> ''",
				$event_category_id
			);
		}

		$recipient_emails = $wpdb->get_results( $recipient_query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- WHERE is built from constant fragments with placeholders; values are bound via prepare().
	}

	// Deduplicate emails (case-insensitive)
	$unique_map = array();
	foreach ( $recipient_emails as $row ) {
		$key = strtolower( trim( $row->email ) );
		if ( $key !== '' && is_email( $row->email ) ) {
			$unique_map[ $key ] = $row; // preserve original casing
		}
	}
	$recipient_emails = array_values( $unique_map );

	// Nothing to do
	if ( empty( $recipient_emails ) ) {
		return array(
			'success'           => true,
			'message'           => 'Keine Abonnenten zu benachrichtigen',
			'notification_sent' => false,
			'subscriber_count'  => 0,
		);
	}

		// Prepare email contents
		$event_title = ! empty( $normalized_event['wasAngefragt'] ) ?
			wp_strip_all_tags( $normalized_event['wasAngefragt'] ) :
			sprintf( /* translators: %d: event ID */ __( 'New event #%d', 'task-table' ), (int) ( $normalized_event['id'] ?? 0 ) );

	$event_details = task_table_format_event_details( $normalized_event );

	// Send emails
	$sent_count = 0;
	foreach ( $recipient_emails as $recipient ) {
		$sent = task_table_send_notification(
			$recipient->email,
			'new_event',
			array(
				'event_title'   => $event_title,
				'event_details' => $event_details,
				'event_id'      => $normalized_event['id'] ?? 0,
				'manage_link'   => task_table_get_subscription_manage_url( $recipient->id, $recipient->email ),
			),
			task_table_get_list_unsubscribe_headers( $recipient->id, $recipient->email )
		);
		if ( $sent ) {
			++$sent_count;
		}
	}

		return array(
			'success'           => true,
			'message'           => 'Benachrichtigung an ' . $sent_count . ' Abonnenten gesendet',
			'notification_sent' => ( $sent_count > 0 ),
			'subscriber_count'  => $sent_count,
		);
}

/**
 * Send notification when a new signup is received
 */
function task_table_send_signup_notification( $signup_data ) {
	return task_table_send_new_signup_notification( $signup_data );
}

/**
 * Hook to send notifications when a new signup is processed
 */
function task_table_process_new_signup( $data, $status ) {
	// Only send notification for new signups
	if ( $status === 'pending' ) {
		task_table_send_signup_notification( $data );
	}

	return $data;
}
add_filter( 'task_table_process_signup', 'task_table_process_new_signup', 10, 2 );
