<?php
/**
 * Self-service subscription management: change categories or unsubscribe.
 *
 * Links are authenticated with an HMAC over the subscriber ID and email address
 * (keyed with the site's auth salt), so no extra token needs to be stored and every
 * notification email can carry a link that stays valid until the address is removed.
 * Changing the salts invalidates all links.
 *
 * Also implements RFC 8058 one-click unsubscribe (List-Unsubscribe-Post).
 *
 * @package TaskTable
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Signature for a subscriber's management link.
 */
function task_table_subscription_signature($subscriber_id, $email) {
    return hash_hmac('sha256', 'task-table-manage|' . (int) $subscriber_id . '|' . strtolower((string) $email), wp_salt('auth'));
}

/**
 * Public URL where a subscriber can manage or cancel their subscription.
 *
 * @param bool $one_click Link for the List-Unsubscribe header (unsubscribes on POST without a form).
 */
function task_table_get_subscription_manage_url($subscriber_id, $email, $one_click = false) {
    $args = array(
        'task_table_subscription' => $one_click ? 'unsubscribe' : 'manage',
        'sid' => (int) $subscriber_id,
        'sig' => task_table_subscription_signature($subscriber_id, $email),
    );
    return add_query_arg($args, home_url('/'));
}

/**
 * Load the subscriber a signed link refers to, or null if the signature does not match.
 */
function task_table_get_subscriber_from_signed_request($subscriber_id, $signature) {
    global $wpdb;
    $subscriber_id = (int) $subscriber_id;
    if ($subscriber_id <= 0 || !is_string($signature) || $signature === '') {
        return null;
    }

    $table = $wpdb->prefix . 'task_table_subscribers';
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $subscriber_id));
    if (!$row || !hash_equals(task_table_subscription_signature($row->id, $row->email), $signature)) {
        return null;
    }
    return $row;
}

/**
 * Remove a subscriber and their category assignments.
 */
function task_table_delete_subscriber($subscriber_id) {
    global $wpdb;
    $wpdb->delete($wpdb->prefix . 'task_table_subscriber_categories', array('subscriber_id' => (int) $subscriber_id));
    return (bool) $wpdb->delete($wpdb->prefix . 'task_table_subscribers', array('id' => (int) $subscriber_id));
}

/**
 * Extra mail headers for notifications to a subscriber (RFC 2369 / RFC 8058).
 */
function task_table_get_list_unsubscribe_headers($subscriber_id, $email) {
    $url = task_table_get_subscription_manage_url($subscriber_id, $email, true);
    return array(
        'List-Unsubscribe: <' . esc_url_raw($url) . '>',
        'List-Unsubscribe-Post: List-Unsubscribe=One-Click',
    );
}

/**
 * Handle ?task_table_subscription=manage|unsubscribe requests.
 */
function task_table_handle_subscription_management() {
    if (!isset($_GET['task_table_subscription'])) {
        return;
    }

    $mode = sanitize_key(wp_unslash($_GET['task_table_subscription']));
    if (!in_array($mode, array('manage', 'unsubscribe'), true)) {
        return;
    }

    nocache_headers();
    header('X-Robots-Tag: noindex, nofollow');
    header('Referrer-Policy: no-referrer');

    $subscriber_id = isset($_GET['sid']) ? absint($_GET['sid']) : 0;
    $signature = isset($_GET['sig']) ? sanitize_text_field(wp_unslash($_GET['sig'])) : '';
    $subscriber = task_table_get_subscriber_from_signed_request($subscriber_id, $signature);
    $is_post = isset($_SERVER['REQUEST_METHOD']) && strtoupper(sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD']))) === 'POST';

    if (!$subscriber) {
        wp_die(
            esc_html__('Dieser Link ist ungültig oder das Abo existiert nicht mehr.', 'task-table'),
            esc_html__('Abo verwalten', 'task-table'),
            array('response' => 404)
        );
    }

    // RFC 8058 one-click: mail clients POST "List-Unsubscribe=One-Click" without user interaction.
    if ($mode === 'unsubscribe' && $is_post) {
        task_table_delete_subscriber($subscriber->id);
        status_header(200);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Unsubscribed';
        exit;
    }

    // Changes only happen on POST, so link scanners that prefetch GET URLs can't unsubscribe anyone.
    if ($is_post) {
        $action = isset($_POST['task_table_action']) ? sanitize_key(wp_unslash($_POST['task_table_action'])) : '';

        if ($action === 'unsubscribe') {
            task_table_delete_subscriber($subscriber->id);
            wp_die(
                esc_html__('Du wurdest abgemeldet und deine E-Mail-Adresse wurde gelöscht.', 'task-table'),
                esc_html__('Abgemeldet', 'task-table'),
                array('response' => 200)
            );
        }

        if ($action === 'update') {
            $categories = isset($_POST['categories']) ? array_map('absint', (array) wp_unslash($_POST['categories'])) : array();
            task_table_sync_subscriber_categories((int) $subscriber->id, $categories);
            $saved = true;
        }
    }

    task_table_render_subscription_manage_page($subscriber, $mode, !empty($saved));
}
add_action('template_redirect', 'task_table_handle_subscription_management', 5);

/**
 * Minimal page with the category form and an unsubscribe button.
 */
function task_table_render_subscription_manage_page($subscriber, $mode, $saved) {
    $action_url = task_table_get_subscription_manage_url($subscriber->id, $subscriber->email, false);
    $selected = array_map('intval', task_table_get_subscriber_categories($subscriber->id));
    $categories = task_table_get_categories();

    ob_start();
    ?>
    <h1><?php esc_html_e('Abo verwalten', 'task-table'); ?></h1>
    <p><?php
        /* translators: %s: email address */
        printf(esc_html__('Benachrichtigungen für %s', 'task-table'), '<strong>' . esc_html($subscriber->email) . '</strong>');
    ?></p>
    <?php if ($saved) : ?>
        <p><strong><?php esc_html_e('Deine Auswahl wurde gespeichert.', 'task-table'); ?></strong></p>
    <?php endif; ?>
    <?php if ((int) $subscriber->confirmed === 1 && $mode === 'manage' && !empty($categories)) : ?>
        <form method="post" action="<?php echo esc_url($action_url); ?>">
            <input type="hidden" name="task_table_action" value="update">
            <fieldset>
                <legend><?php esc_html_e('Kategorien', 'task-table'); ?></legend>
                <?php foreach ($categories as $category) : ?>
                    <label style="display:block;margin:.3em 0">
                        <input type="checkbox" name="categories[]" value="<?php echo esc_attr($category['id']); ?>" <?php checked(in_array((int) $category['id'], $selected, true)); ?>>
                        <?php echo esc_html($category['name']); ?>
                    </label>
                <?php endforeach; ?>
            </fieldset>
            <p><button type="submit" class="button"><?php esc_html_e('Auswahl speichern', 'task-table'); ?></button></p>
        </form>
    <?php endif; ?>
    <form method="post" action="<?php echo esc_url($action_url); ?>">
        <input type="hidden" name="task_table_action" value="unsubscribe">
        <p><button type="submit" class="button"><?php esc_html_e('Abmelden und E-Mail-Adresse löschen', 'task-table'); ?></button></p>
    </form>
    <?php
    wp_die(ob_get_clean(), esc_html__('Abo verwalten', 'task-table'), array('response' => 200)); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above
}
