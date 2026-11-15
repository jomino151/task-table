<?php
/**
 * Template for the event signup dialog.
 *
 * A native <dialog> opened with showModal(): the browser makes the rest of the page inert
 * (focus trap) and closes it on Escape. view.js returns focus to the button that opened it.
 * The data- attributes pass the REST endpoint, nonce and messages to view.js.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$task_table_signup_i18n = array(
	'fillAll' => __( 'Please fill in all fields.', 'task-table' ),
	'success' => __( 'Your signup was sent and will be reviewed.', 'task-table' ),
	'error'   => __( 'Something went wrong. Please try again later.', 'task-table' ),
	/* translators: Prefix added to the notes of a signup made as a backup for an already confirmed person. */
	'backup'  => __( '[Backup signup]', 'task-table' ),
);
?>
<dialog
	id="signup-modal"
	class="tt-signup-dialog"
	aria-labelledby="signup-modal-title"
	data-rest-url="<?php echo esc_url( rest_url( 'event-signups/v1/submit' ) ); ?>"
	data-rest-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>"
	data-i18n="<?php echo esc_attr( wp_json_encode( $task_table_signup_i18n ) ); ?>"
>
	<div class="tt-signup-dialog__inner">
		<button type="button" id="close-modal" class="close-modal" data-close-dialog aria-label="<?php esc_attr_e( 'Close', 'task-table' ); ?>">&times;</button>
		<h3 id="signup-modal-title"><?php esc_html_e( 'Sign up for event', 'task-table' ); ?></h3>

		<div id="signup-feedback" class="signup-feedback" role="status" aria-live="polite"></div>
		<p id="replacement-disclaimer" class="replacement-disclaimer" hidden>
			<?php esc_html_e( 'Note: someone has already been confirmed for this event. Your signup counts as a backup in case they drop out.', 'task-table' ); ?>
		</p>

		<form id="signup-form">
			<input type="hidden" id="event-id" name="event_id">
			<input type="hidden" id="replacement-signup-flag" name="replacement_signup" value="">

			<div class="form-group">
				<label for="user-name"><?php esc_html_e( 'Your name', 'task-table' ); ?></label>
				<input type="text" id="user-name" name="user_name" autocomplete="name" required>
			</div>

			<div class="form-group">
				<label for="user-email"><?php esc_html_e( 'Your email', 'task-table' ); ?></label>
				<input type="email" id="user-email" name="user_email" autocomplete="email" required>
			</div>

			<div class="form-group">
				<label for="free-text"><?php esc_html_e( 'More information (optional)', 'task-table' ); ?></label>
				<textarea id="free-text" name="free_text" rows="4" placeholder="<?php esc_attr_e( 'Additional notes or questions…', 'task-table' ); ?>"></textarea>
			</div>

			<div class="tt-button-group">
				<button type="button" id="cancel-signup" class="cancel-btn" data-close-dialog><?php esc_html_e( 'Cancel', 'task-table' ); ?></button>
				<button type="submit" id="submit-signup"><?php esc_html_e( 'Sign up', 'task-table' ); ?></button>
			</div>
		</form>
	</div>
</dialog>
