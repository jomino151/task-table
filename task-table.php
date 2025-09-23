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

// Include required files
require_once TASK_TABLE_PLUGIN_DIR . 'src/database.php';
require_once TASK_TABLE_PLUGIN_DIR . 'src/email-notifications.php';
require_once TASK_TABLE_PLUGIN_DIR . 'src/api.php';
require_once TASK_TABLE_PLUGIN_DIR . 'src/admin-pages.php';

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
}
add_action('init', 'task_table_register_blocks');

/**
 * Enqueue frontend styles for the modal
 */
function task_table_enqueue_frontend_styles() {
    // Only enqueue if we're not in admin
    if (!is_admin()) {
        wp_enqueue_style(
            'task-table-frontend',
            TASK_TABLE_PLUGIN_URL . 'build/task-table/style-index.css',
            array(),
            filemtime(TASK_TABLE_PLUGIN_DIR . 'build/task-table/style-index.css')
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
            TASK_TABLE_PLUGIN_URL . 'js/admin.js',
            array('jquery'),
            filemtime(TASK_TABLE_PLUGIN_DIR . 'js/admin.js'),
            true
        );
    }
}
add_action('admin_enqueue_scripts', 'task_table_admin_enqueue_scripts');

/**
 * Pass AJAX URL to front-end scripts
 */
function task_table_localize_scripts() {
    wp_localize_script('task-table-view', 'taskTableSettings', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'resturl' => esc_url_raw(rest_url()),
        'nonce' => wp_create_nonce('wp_rest')
    ));
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
    global $wpdb; 
    $table = $wpdb->prefix . 'task_table_subscribers';
    $row = $wpdb->get_row($wpdb->prepare("SELECT id, confirmed, confirmation_token FROM $table WHERE email = %s", $email));
    if ($row && (int)$row->confirmed === 0 && hash_equals((string)$row->confirmation_token, (string)$token)) {
        $updated = $wpdb->update($table, [
            'confirmed' => 1,
            'confirmation_token' => null,
            'updated_at' => current_time('mysql')
        ], ['id' => $row->id]);
        $verify = (int)$wpdb->get_var($wpdb->prepare("SELECT confirmed FROM $table WHERE id = %d", $row->id));
        if ($updated !== false && $verify === 1) {
            wp_die(__('Danke! Dein Abo wurde bestätigt.', 'task-table'), __('Abo bestätigt', 'task-table'), ['response' => 200]);
        } else {
            wp_die(__('Technischer Fehler: Bestätigung konnte nicht gespeichert werden.', 'task-table'), __('Fehler', 'task-table'), ['response' => 500]);
        }
    } elseif ($row && (int)$row->confirmed === 1) {
        wp_die(__('Dieses Abo ist bereits bestätigt.', 'task-table'), __('Bereits bestätigt', 'task-table'), ['response' => 200]);
    } else {
        wp_die(__('Bestätigungslink ist ungültig oder abgelaufen.', 'task-table'), __('Ungültiger Link', 'task-table'), ['response' => 400]);
    }
}
add_action('template_redirect', 'task_table_handle_frontend_confirmation');
