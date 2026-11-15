<?php
/**
 * Template for event signup modal
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>
<!-- Modal is placed outside the main block container -->
<div id="signup-modal-overlay" class="modal-overlay">
	<div id="signup-modal" class="modal">
		<button type="button" id="close-modal" class="close-modal" aria-label="<?php esc_attr_e( 'Schließen', 'task-table' ); ?>">&times;</button>
		<h3><?php esc_html_e( 'Für Veranstaltung anmelden', 'task-table' ); ?></h3>
		
		<div id="signup-feedback" class="signup-feedback"></div>
		<div id="replacement-disclaimer" class="replacement-disclaimer" style="display:none;">
			<?php esc_html_e( 'Hinweis: Für diese Veranstaltung gibt es bereits eine bestätigte Person. Deine Anmeldung gilt als Ersatz, falls die bestätigte Person ausfällt.', 'task-table' ); ?>
		</div>
		
		<form id="signup-form">
			<input type="hidden" id="event-id" name="event_id">
			<input type="hidden" id="replacement-signup-flag" name="replacement_signup" value="">
			
			<div class="form-group">
				<label for="user-name"><?php esc_html_e( 'Dein Name', 'task-table' ); ?></label>
				<input type="text" id="user-name" name="user_name" required>
			</div>
			
			<div class="form-group">
				<label for="user-email"><?php esc_html_e( 'Deine E-Mail', 'task-table' ); ?></label>
				<input type="email" id="user-email" name="user_email" required>
			</div>
			
			<div class="form-group">
				<label for="free-text"><?php esc_html_e( 'Weitere Informationen (optional)', 'task-table' ); ?></label>
				<textarea id="free-text" name="free_text" rows="4" placeholder="<?php esc_attr_e( 'Zusätzliche Hinweise oder Fragen …', 'task-table' ); ?>"></textarea>
			</div>
			
			<div class="tt-button-group">
				<button type="button" id="cancel-signup" class="cancel-btn"><?php esc_html_e( 'Abbrechen', 'task-table' ); ?></button>
				<button type="submit" id="submit-signup"><?php esc_html_e( 'Anmelden', 'task-table' ); ?></button>
			</div>
		</form>
	</div>
</div>
