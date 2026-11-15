<?php
/**
 * Helpers shared by the admin screens.
 *
 * @package TaskTable
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Print a dismissible admin notice.
 *
 * @param array $notice Array with 'type' (success, info, error) and 'message' (plain text).
 */
function task_table_admin_print_notice( $notice ) {
	printf(
		'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
		esc_attr( $notice['type'] ),
		esc_html( $notice['message'] )
	);
}

/**
 * Turn an event text field into markup for an admin table cell: sanitized, with links made clickable.
 *
 * @param mixed $value Raw field value.
 * @return string Markup, or '' for an empty value.
 */
function task_table_admin_render_event_cell( $value ) {
	$content = is_scalar( $value ) ? (string) $value : '';

	if ( $content === '' ) {
		return '';
	}

	if ( function_exists( 'task_table_sanitize_event_text' ) ) {
		$content = task_table_sanitize_event_text( $content );
		$content = make_clickable( $content );

		return task_table_sanitize_event_text( $content );
	}

	return wp_kses_post( make_clickable( $content ) );
}

/**
 * Parse a string containing multiple email addresses.
 * Accepts comma-separated or line-break-separated lists.
 *
 * @param string $email_string String containing multiple email addresses
 * @return array Array of valid email addresses
 */
function task_table_parse_email_list( $email_string ) {
	// Replace commas with line breaks to standardize the separator
	$email_string = str_replace( ',', "\n", $email_string );

	// Split the string by line breaks
	$emails = explode( "\n", $email_string );

	// Clean and validate each email
	$valid_emails = array();
	foreach ( $emails as $email ) {
		$email = trim( $email );
		if ( ! empty( $email ) && is_email( $email ) ) {
			$valid_emails[] = sanitize_email( $email );
		}
	}

	return $valid_emails;
}

/**
 * Get all notification emails for new signups
 * This function ensures emails are sent individually to protect recipient privacy
 */
function task_table_get_signup_notification_emails() {
	$emails = array();

	foreach ( task_table_get_signup_notification_recipients() as $recipient ) {
		$email = isset( $recipient['email'] ) ? sanitize_email( $recipient['email'] ) : '';
		if ( is_email( $email ) ) {
			$emails[ strtolower( $email ) ] = $email;
		}
	}

	return array_values( $emails );
}
