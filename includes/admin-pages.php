<?php
/**
 * Admin pages for task table plugin
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Register admin menu pages
 */
function task_table_register_admin_pages() {
    add_menu_page(
        'Event Sign-Ups',
        'Event Sign-Ups',
        'manage_options',
        'event-signups',
        'task_table_render_signups_page',
        'dashicons-list-view',
        30
    );
    add_submenu_page(
        'event-signups',
        'Manage Sign-Ups',
        'Manage Sign-Ups',
        'manage_options',
        'manage-signups',
        'task_table_render_manage_signups_page'
    );
}
add_action('admin_menu', 'task_table_register_admin_pages');

/**
 * Render the pending signups page
 */
function task_table_render_signups_page() {
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

/**
 * Render the manage signups page (approved/denied)
 */
function task_table_render_manage_signups_page() {
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

/**
 * Handle admin actions (approve/deny/delete)
 */
function task_table_handle_admin_actions() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'event_signups';
    
    // Handle approve action
    if (isset($_GET['approve']) && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'approve_signup_' . $_GET['approve'])) {
        $signup_id = intval($_GET['approve']);
        $wpdb->update($table_name, array('status' => 'approved'), array('id' => $signup_id));
        
        // Send approval email to user
        $signup = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $signup_id));
        if ($signup) {
            wp_mail($signup->user_email, 'Sign-Up Approved', 'Your sign-up has been approved!');
        }
        
        wp_safe_redirect(wp_get_referer() ?: admin_url('admin.php?page=event-signups'));
        exit;
    }
    
    // Handle deny action
    if (isset($_GET['deny']) && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'deny_signup_' . $_GET['deny'])) {
        $signup_id = intval($_GET['deny']);
        $wpdb->update($table_name, array('status' => 'denied'), array('id' => $signup_id));
        
        // Send denial email to user
        $signup = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $signup_id));
        if ($signup) {
            wp_mail($signup->user_email, 'Sign-Up Denied', 'Your sign-up has been denied.');
        }
        
        wp_safe_redirect(wp_get_referer() ?: admin_url('admin.php?page=event-signups'));
        exit;
    }
    
    // Handle delete action
    if (isset($_GET['delete']) && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'delete_signup_' . $_GET['delete'])) {
        $signup_id = intval($_GET['delete']);
        $wpdb->delete($table_name, array('id' => $signup_id));
        
        wp_safe_redirect(wp_get_referer() ?: admin_url('admin.php?page=manage-signups'));
        exit;
    }
}
add_action('admin_init', 'task_table_handle_admin_actions');
