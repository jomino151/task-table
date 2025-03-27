<?php
/**
 * Template for event signup modal
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
?>
<!-- Modal is placed outside the main block container -->
<div id="signup-modal-overlay" class="modal-overlay">
    <div id="signup-modal" class="modal">
        <button type="button" id="close-modal" class="close-modal">&times;</button>
        <h3>Für Veranstaltung anmelden</h3>
        
        <div id="signup-feedback" class="signup-feedback"></div>
        
        <form id="signup-form">
            <input type="hidden" id="event-id" name="event_id">
            
            <div class="form-group">
                <label for="user-name">Dein Name</label>
                <input type="text" id="user-name" name="user_name" required>
            </div>
            
            <div class="form-group">
                <label for="user-email">Deine E-Mail</label>
                <input type="email" id="user-email" name="user_email" required>
            </div>
            
            <div class="button-group">
                <button type="button" id="cancel-signup" class="cancel-btn">Abbrechen</button>
                <button type="submit" id="submit-signup">Anmelden</button>
            </div>
        </form>
    </div>
</div>
