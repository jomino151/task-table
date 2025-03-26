<?php
/**
 * Database related functions
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Create the table for storing event signups
 */
function task_table_create_signups_table() {
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
