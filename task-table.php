<?php
/**
 * Plugin Name:       task-Table
 * Description:       Creates a dynamic table to communicate task to multis.
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

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// create a table to store signups
function create_signups_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'event_signups';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        event_id mediumint(9) NOT NULL,
        user_name varchar(255) NOT NULL,
        user_email varchar(255) NOT NULL,
        status varchar(50) DEFAULT 'pending',
        timestamp datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'create_signups_table');

// Add this to the main plugin file
add_action('rest_api_init', function () {
    register_rest_route('event-signups/v1', '/submit', array(
        'methods' => 'POST',
        'callback' => 'handle_signup_submission',
        'permission_callback' => '__return_true' // Allow public access
    ));
});

// handle the form submission via AJAX
function handle_signup_submission(WP_REST_Request $request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'event_signups';

    $data = $request->get_json_params();
    $event_id = intval($data['event_id']); // Ensure event_id is an integer within the mediumint range
    $user_name = sanitize_text_field($data['user_name']);
    $user_email = sanitize_email($data['user_email']);

    // Insert into the database
    $result = $wpdb->insert($table_name, array(
        'event_id' => $event_id,
        'user_name' => $user_name,
        'user_email' => $user_email,
        'status' => 'pending'
    ));

    if ($result === false) {
        error_log('Database insert failed: ' . $wpdb->last_error);
        return new WP_REST_Response(array('success' => false, 'message' => 'Database insert failed'), 500);
    }

    // Send email to moderator
    $moderator_email = get_option('moderator_email', 'admin@example.com');
    $subject = 'New Sign-Up for Event';
    $message = "A new sign-up has been received:\n\nEvent ID: $event_id\nName: $user_name\nEmail: $user_email";
    wp_mail($moderator_email, $subject, $message);

    return new WP_REST_Response(array('success' => true), 200);
}

// create admin page to manage sign-ups
add_action('admin_menu', function () {
    add_menu_page(
        'Event Sign-Ups',
        'Event Sign-Ups',
        'manage_options',
        'event-signups',
        'render_signups_page'
    );
    add_submenu_page(
        'event-signups',
        'Manage Sign-Ups',
        'Manage Sign-Ups',
        'manage_options',
        'manage-signups',
        'render_manage_signups_page'
    );
});

function render_signups_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'event_signups';
    $signups = $wpdb->get_results("SELECT * FROM $table_name WHERE status = 'pending'");

    echo '<div class="wrap"><h1>Pending Sign-Ups</h1>';
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th>Event ID</th><th>Name</th><th>Email</th><th>Action</th></tr></thead>';
    echo '<tbody>';

    if (!empty($signups)) {
        foreach ($signups as $signup) {
            echo '<tr>';
            echo '<td>' . esc_html($signup->event_id) . '</td>';
            echo '<td>' . esc_html($signup->user_name) . '</td>';
            echo '<td>' . esc_html($signup->user_email) . '</td>';
            echo '<td><a href="' . wp_nonce_url(admin_url('admin.php?page=event-signups&approve=' . $signup->id), 'approve_signup_' . $signup->id) . '">Approve</a> | <a href="' . wp_nonce_url(admin_url('admin.php?page=event-signups&deny=' . $signup->id), 'deny_signup_' . $signup->id) . '">Deny</a></td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="4">No pending sign-ups found.</td></tr>';
    }

    echo '</tbody></table></div>';
}

function render_manage_signups_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'event_signups';
    $signups = $wpdb->get_results("SELECT * FROM $table_name WHERE status IN ('approved', 'denied')");

    echo '<div class="wrap"><h1>Manage Sign-Ups</h1>';
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th>Event ID</th><th>Name</th><th>Email</th><th>Status</th><th>Action</th></tr></thead>';
    echo '<tbody>';

    if (!empty($signups)) {
        foreach ($signups as $signup) {
            echo '<tr>';
            echo '<td>' . esc_html($signup->event_id) . '</td>';
            echo '<td>' . esc_html($signup->user_name) . '</td>';
            echo '<td>' . esc_html($signup->user_email) . '</td>';
            echo '<td>' . esc_html($signup->status) . '</td>';
            echo '<td><a href="' . wp_nonce_url(admin_url('admin.php?page=manage-signups&approve=' . $signup->id), 'approve_signup_' . $signup->id) . '">Approve</a> | <a href="' . wp_nonce_url(admin_url('admin.php?page=manage-signups&deny=' . $signup->id), 'deny_signup_' . $signup->id) . '">Deny</a> | <a href="' . wp_nonce_url(admin_url('admin.php?page=manage-signups&delete=' . $signup->id), 'delete_signup_' . $signup->id) . '">Delete</a></td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="5">No approved or denied sign-ups found.</td></tr>';
    }

    echo '</tbody></table></div>';
}

add_action('admin_init', function () {
    if (isset($_GET['approve'])) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'event_signups';
        $wpdb->update($table_name, array('status' => 'approved'), array('id' => intval($_GET['approve'])));
        // Send approval email to user
        $signup = $wpdb->get_row("SELECT * FROM $table_name WHERE id = " . intval($_GET['approve']));
        wp_mail($signup->user_email, 'Sign-Up Approved', 'Your sign-up has been approved!');
    }

    if (isset($_GET['deny'])) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'event_signups';
        $wpdb->update($table_name, array('status' => 'denied'), array('id' => intval($_GET['deny'])));
        // Send denial email to user
        $signup = $wpdb->get_row("SELECT * FROM $table_name WHERE id = " . intval($_GET['deny']));
        wp_mail($signup->user_email, 'Sign-Up Denied', 'Your sign-up has been denied.');
    }

    if (isset($_GET['delete'])) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'event_signups';
        $wpdb->delete($table_name, array('id' => intval($_GET['delete'])));
    }
});

/**
 * Registers the block using the metadata loaded from the `block.json` file.
 * Behind the scenes, it registers also all assets so they can be enqueued
 * through the block editor in the corresponding context.
 *
 * @see https://developer.wordpress.org/reference/functions/register_block_type/
 */
function task_table_task_table_block_init() {
    register_block_type( __DIR__ . '/build/task-table' );
}
add_action( 'init', 'task_table_task_table_block_init' );
