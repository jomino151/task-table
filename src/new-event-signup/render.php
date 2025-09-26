<?php
/**
 * Render callback for the new event signup (notification) block.
 *
 * @package TaskTable
 */

if (!defined('ABSPATH')) {
    exit;
}

$rest_url = esc_url_raw(rest_url('task-table/v1/subscribers'));
$rest_nonce = wp_create_nonce('wp_rest');

ob_start();
?>
<div
    class="wp-block-task-table-new-event-signup"
    data-rest-url="<?php echo esc_attr($rest_url); ?>"
    data-rest-nonce="<?php echo esc_attr($rest_nonce); ?>"
>
    <h3>Neue Veranstaltungen abonnieren</h3>
    <p>Erhalte E-Mail-Benachrichtigungen, wenn neue Veranstaltungen hinzugefügt werden.</p>
    <form class="event-notification-form">
        <input type="email" class="subscriber-email" placeholder="Deine E-Mail-Adresse" required>
        <button type="submit" class="subscription-button">Abonnieren</button>
    </form>
    <div class="subscription-message" role="status" aria-live="polite"></div>
</div>
<?php
return ob_get_clean();
