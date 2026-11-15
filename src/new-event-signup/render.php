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

$display_categories = task_table_get_categories();

ob_start();
?>
<div
    class="wp-block-task-table-new-event-signup"
    data-rest-url="<?php echo esc_attr($rest_url); ?>"
    data-rest-nonce="<?php echo esc_attr($rest_nonce); ?>"
>
    <h3><?php esc_html_e('Neue Veranstaltungen abonnieren', 'task-table'); ?></h3>
    <p><?php esc_html_e('Erhalte E-Mail-Benachrichtigungen, wenn neue Veranstaltungen hinzugefügt werden.', 'task-table'); ?></p>
    <form class="event-notification-form">
        <input type="email" class="subscriber-email" placeholder="<?php esc_attr_e('Deine E-Mail-Adresse', 'task-table'); ?>" aria-label="<?php esc_attr_e('Deine E-Mail-Adresse', 'task-table'); ?>" required>
        <?php if (!empty($display_categories)) : ?>
            <fieldset class="subscription-categories">
                <legend><?php esc_html_e('Für welche Kategorien möchtest du Benachrichtigungen erhalten?', 'task-table'); ?></legend>
                <?php foreach ($display_categories as $category) :
                    $category_id = (int) $category['id'];
                    $category_name = $category['name'];
                    $input_id = 'subscription-category-' . $category_id;
                ?>
                    <label for="<?php echo esc_attr($input_id); ?>" class="subscription-category">
                        <input type="checkbox" class="subscriber-category" id="<?php echo esc_attr($input_id); ?>" value="<?php echo esc_attr($category_id); ?>">
                        <span><?php echo esc_html($category_name); ?></span>
                    </label>
                <?php endforeach; ?>
                <p class="subscription-hint"><?php esc_html_e('Du kannst mehrere Kategorien wählen. Mindestens eine Auswahl ist erforderlich.', 'task-table'); ?></p>
            </fieldset>
        <?php else : ?>
            <p class="subscription-categories-empty"><?php esc_html_e('Momentan sind keine abonnierbaren Kategorien verfügbar. Bitte versuche es später erneut oder kontaktiere das Team.', 'task-table'); ?></p>
        <?php endif; ?>
        <button type="submit" class="subscription-button"><?php esc_html_e('Abonnieren', 'task-table'); ?></button>
    </form>
    <div class="subscription-message" role="status" aria-live="polite"></div>
</div>
<?php
return ob_get_clean();
