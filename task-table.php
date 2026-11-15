<?php
/**
 * Plugin Name:       Task Table
 * Description:       Creates a dynamic table to communicate tasks to multis.
 * Version:           0.1.0
 * Requires at least: 6.7
 * Requires PHP:      7.4
 * Author:            Jonas Morawietz
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       task-table
 *
 * @package TaskTable
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Define plugin constants
define('TASK_TABLE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TASK_TABLE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('TASK_TABLE_PLUGIN_FILE', __FILE__);
define('TASK_TABLE_SIGNUP_NOTIFICATIONS_OPTION', 'task_table_signup_notification_emails');
define('TASK_TABLE_SIGNUP_NOTIFICATION_RECIPIENTS_OPTION', 'task_table_signup_notification_recipients');

// Include required files
require_once TASK_TABLE_PLUGIN_DIR . 'build/database.php';
require_once TASK_TABLE_PLUGIN_DIR . 'build/email-notifications.php';
require_once TASK_TABLE_PLUGIN_DIR . 'build/api.php';
require_once TASK_TABLE_PLUGIN_DIR . 'build/admin-pages.php';
require_once TASK_TABLE_PLUGIN_DIR . 'build/translation-overrides.php';

// Register activation hook
register_activation_hook(__FILE__, 'task_table_create_database_tables');
register_activation_hook(__FILE__, 'task_table_initialize_options');

/**
 * Initialize plugin options on activation
 */
function task_table_initialize_options() {
    // Only set default notification emails if the option doesn't exist
    if (!get_option(TASK_TABLE_SIGNUP_NOTIFICATIONS_OPTION)) {
        // Initialize with empty array
        update_option(TASK_TABLE_SIGNUP_NOTIFICATIONS_OPTION, array());
    }

}

/**
 * Register the block using the metadata loaded from the `block.json` file.
 */
function task_table_register_blocks() {
    register_block_type(__DIR__ . '/build/task-table');
    register_block_type(
        __DIR__ . '/build/new-event-signup',
        array(
            'render_callback' => 'task_table_render_new_event_signup_block',
        )
    );
}
add_action('init', 'task_table_register_blocks');

/**
 * Render callback for the new event signup block.
 *
 * This wrapper ensures we always return markup even if the metadata render file
 * cannot be automatically discovered (e.g. when the block is registered before
 * build assets are finalized). It prefers the compiled build template but will
 * gracefully fall back to the source version during development.
 *
 * @return string
 */
function task_table_render_new_event_signup_block($attributes = array(), $content = '', $block = null) {
    $template_paths = array(
        TASK_TABLE_PLUGIN_DIR . 'build/new-event-signup/render.php',
        TASK_TABLE_PLUGIN_DIR . 'src/new-event-signup/render.php',
    );

    foreach ($template_paths as $template) {
        if (!file_exists($template)) {
            continue;
        }

        ob_start();
        $returned = include $template;
        $buffered_output = ob_get_clean();

        if (is_string($returned) && $returned !== '') {
            return $returned;
        }

        if ($buffered_output !== false) {
            return $buffered_output;
        }
    }

    return '';
}

/**
 * Enqueue frontend styles for the modal
 */
function task_table_enqueue_frontend_styles() {
    if (is_admin()) {
        return;
    }

    $styles = array(
        'task-table-frontend' => 'build/task-table/style-index.css',
        'task-table-new-event-signup' => 'build/new-event-signup/style-index.css',
    );

    foreach ($styles as $handle => $relative_path) {
        $full_path = TASK_TABLE_PLUGIN_DIR . $relative_path;

        if (!file_exists($full_path)) {
            continue;
        }

        wp_enqueue_style(
            $handle,
            TASK_TABLE_PLUGIN_URL . $relative_path,
            array(),
            filemtime($full_path)
        );
    }
}
add_action('wp_enqueue_scripts', 'task_table_enqueue_frontend_styles');

/**
 * Enqueue admin scripts and styles
 */
function task_table_admin_enqueue_scripts($hook) {
    if (strpos($hook, 'task-table') !== false) {
        wp_enqueue_script(
            'task-table-admin',
            TASK_TABLE_PLUGIN_URL . 'admin/admin.js',
            array('jquery'),
            filemtime(TASK_TABLE_PLUGIN_DIR . 'admin/admin.js'),
            true
        );
    }
}
add_action('admin_enqueue_scripts', 'task_table_admin_enqueue_scripts');

/**
 * Pass AJAX URL to front-end scripts
 */
function task_table_localize_scripts() {
    $localization = array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'resturl' => esc_url_raw(rest_url()),
        'nonce' => wp_create_nonce('wp_rest'),
    );

    if (!class_exists('WP_Block_Type_Registry')) {
        return;
    }

    $registry = WP_Block_Type_Registry::get_instance();
    $block_names = array('task-table/task-table', 'task-table/new-event-signup');

    foreach ($block_names as $block_name) {
        $block_type = $registry->get_registered($block_name);

        if (!$block_type || empty($block_type->view_script_handles)) {
            continue;
        }

        foreach ($block_type->view_script_handles as $handle) {
            if (wp_script_is($handle, 'registered')) {
                wp_localize_script($handle, 'taskTableSettings', $localization);
            }
        }
    }
}
add_action('wp_enqueue_scripts', 'task_table_localize_scripts');

/**
 * Add custom capabilities on plugin activation
 */
function task_table_add_capabilities() {
    $admin_role = get_role('administrator');
    
    if ($admin_role) {
        $admin_role->add_cap('manage_task_table');
        $admin_role->add_cap('edit_task_table_events');
    }
}
register_activation_hook(__FILE__, 'task_table_add_capabilities');

/**
 * Clean up on plugin deactivation
 */
function task_table_deactivation() {
    // Nothing to do yet
}
register_deactivation_hook(__FILE__, 'task_table_deactivation');

/**
 * Handle front-end confirmation links (non-REST fallback)
 */
function task_table_handle_frontend_confirmation() {
    if (!isset($_GET['task_table_confirm_subscription'])) {
        return;
    }
    $email = isset($_GET['email']) ? sanitize_email(wp_unslash($_GET['email'])) : '';
    $token = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';
    if (!$email || !$token) {
        return; // Silent fail to avoid revealing info
    }
    $result = task_table_confirm_subscription_token($email, $token);
    if ($result === 'confirmed') {
        wp_die(esc_html__('Danke! Dein Abo wurde bestätigt.', 'task-table'), esc_html__('Abo bestätigt', 'task-table'), ['response' => 200]);
    } elseif ($result === 'error') {
        wp_die(esc_html__('Technischer Fehler: Bestätigung konnte nicht gespeichert werden.', 'task-table'), esc_html__('Fehler', 'task-table'), ['response' => 500]);
    }
    wp_die(esc_html__('Bestätigungslink ist ungültig, abgelaufen oder wurde bereits verwendet.', 'task-table'), esc_html__('Ungültiger Link', 'task-table'), ['response' => 400]);
}
add_action('template_redirect', 'task_table_handle_frontend_confirmation');
