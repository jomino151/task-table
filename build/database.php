<?php
/**
 * Database setup and utilities for task table plugin
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Create the necessary database tables during plugin activation
 */
function task_table_create_database_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    
    // Create events table
    $events_table = $wpdb->prefix . 'task_table_events';
    $events_sql = "CREATE TABLE $events_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        was_angefragt text NOT NULL,
        kontext text NOT NULL,
        name_hochschule text NOT NULL,
        tn_anzahl text NOT NULL,
        zeitpunkt text NOT NULL,
        auftragdetails text NOT NULL,
        wer_macht text NOT NULL,
        archived tinyint(1) DEFAULT 0 NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        KEY archived (archived)
    ) $charset_collate;";
    
    // Create signups table
    $signups_table = $wpdb->prefix . 'task_table_signups';
    $signups_sql = "CREATE TABLE $signups_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        event_id mediumint(9) NOT NULL,
        user_name varchar(100) NOT NULL,
        user_email varchar(100) NOT NULL,
        free_text text NULL,
        status varchar(20) NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        KEY event_id (event_id)
    ) $charset_collate;";
    
    // Create subscribers table
    $subscribers_table = $wpdb->prefix . 'task_table_subscribers';
    // Added confirmation_token column for double opt-in (v1.1) and updated_at column (v1.2)
    $subscribers_sql = "CREATE TABLE $subscribers_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        email varchar(100) NOT NULL,
        confirmed tinyint(1) DEFAULT 0 NOT NULL,
        confirmation_token varchar(64) DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY email (email),
        KEY confirmation_token (confirmation_token)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($events_sql);
    dbDelta($signups_sql);
    dbDelta($subscribers_sql);
    
    // Set version to track database changes
    update_option('task_table_db_version', '1.4');
}

/**
 * Migrate existing data from block attributes to database tables
 */
function task_table_migrate_existing_data() {
    global $wpdb;
    
    // Only run migration once
    if (get_option('task_table_migration_complete')) {
        return;
    }
    
    // Get all task-table blocks from posts
    $posts_with_blocks = $wpdb->get_results(
        "SELECT ID, post_content FROM {$wpdb->posts} 
        WHERE post_content LIKE '%wp:task-table/task-table%' 
        AND post_status = 'publish'"
    );
    
    $events_table = $wpdb->prefix . 'task_table_events';
    $migrated_count = 0;
    
    foreach ($posts_with_blocks as $post) {
        // Parse blocks from content
        $blocks = parse_blocks($post->post_content);
        
        foreach ($blocks as $block) {
            if ($block['blockName'] === 'task-table/task-table' && !empty($block['attrs']['rows'])) {
                $rows = $block['attrs']['rows'];
                
                foreach ($rows as $row) {
                    // Skip rows without ID
                    if (empty($row['id'])) {
                        continue;
                    }
                    
                    // Check if this event is already migrated
                    $exists = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM $events_table WHERE id = %d",
                        $row['id']
                    ));
                    
                    if ($exists) {
                        continue;
                    }
                    
                    // Insert into events table
                    $wpdb->insert($events_table, array(
                        'id' => $row['id'],
                        'was_angefragt' => $row['wasAngefragt'] ?? '',
                        'kontext' => $row['kontext'] ?? '',
                        'name_hochschule' => $row['nameHochschule'] ?? '',
                        'tn_anzahl' => $row['tnAnzahl'] ?? '',
                        'zeitpunkt' => $row['zeitpunkt'] ?? '',
                        'auftragdetails' => $row['auftragdetails'] ?? '',
                        'wer_macht' => $row['werMacht'] ?? '',
                        'created_at' => current_time('mysql'),
                        'updated_at' => current_time('mysql')
                    ));
                    
                    $migrated_count++;
                }
            }
        }
    }
    
    // Migrate existing signups table if it exists
    $old_signups_table = $wpdb->prefix . 'event_signups';
    $new_signups_table = $wpdb->prefix . 'task_table_signups';
    
    if ($wpdb->get_var("SHOW TABLES LIKE '$old_signups_table'") === $old_signups_table) {
        $signups = $wpdb->get_results("SELECT * FROM $old_signups_table");
        
        foreach ($signups as $signup) {
            $wpdb->insert($new_signups_table, array(
                'event_id' => $signup->event_id,
                'user_name' => $signup->user_name,
                'user_email' => $signup->user_email,
                'status' => $signup->status,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ));
        }
    }
    
    update_option('task_table_migration_complete', true);
    update_option('task_table_migrated_events', $migrated_count);
}

// Use the activation hook to create tables
register_activation_hook(TASK_TABLE_PLUGIN_FILE, 'task_table_create_database_tables');

// Hook for migrating existing data
add_action('admin_init', 'task_table_migrate_existing_data');

/**
 * Perform database schema upgrades for existing installations.
 * Currently ensures the confirmation_token column exists for subscribers table (v1.1).
 */
function task_table_maybe_upgrade_schema() {
    global $wpdb;
    $current_version = get_option('task_table_db_version', '1.0');
    $subscribers_table = $wpdb->prefix . 'task_table_subscribers';
    $signups_table = $wpdb->prefix . 'task_table_signups';
    $events_table = $wpdb->prefix . 'task_table_events';

    // Upgrade to 1.1: add confirmation_token
    if (version_compare($current_version, '1.1', '<')) {
        $col = $wpdb->get_results("SHOW COLUMNS FROM $subscribers_table LIKE 'confirmation_token'");
        if (empty($col)) {
            $wpdb->query("ALTER TABLE $subscribers_table ADD COLUMN confirmation_token varchar(64) DEFAULT NULL");
            $wpdb->query("ALTER TABLE $subscribers_table ADD KEY confirmation_token (confirmation_token)");
        }
        update_option('task_table_db_version', '1.1');
        $current_version = '1.1';
    }

    // Upgrade to 1.2: add updated_at
    if (version_compare($current_version, '1.2', '<')) {
        $col2 = $wpdb->get_results("SHOW COLUMNS FROM $subscribers_table LIKE 'updated_at'");
        if (empty($col2)) {
            $wpdb->query("ALTER TABLE $subscribers_table ADD COLUMN updated_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL AFTER created_at");
        }
        update_option('task_table_db_version', '1.2');
        $current_version = '1.2';
    }

    // Upgrade to 1.3: add free_text column to signups
    if (version_compare($current_version, '1.3', '<')) {
        $col3 = $wpdb->get_results("SHOW COLUMNS FROM $signups_table LIKE 'free_text'");
        if (empty($col3)) {
            $wpdb->query("ALTER TABLE $signups_table ADD COLUMN free_text text NULL AFTER user_email");
        }
        update_option('task_table_db_version', '1.3');
        $current_version = '1.3';
    }

    // Upgrade to 1.4: add archived column to events
    if (version_compare($current_version, '1.4', '<')) {
        $col4 = $wpdb->get_results("SHOW COLUMNS FROM $events_table LIKE 'archived'");
        if (empty($col4)) {
            $wpdb->query("ALTER TABLE $events_table ADD COLUMN archived tinyint(1) DEFAULT 0 NOT NULL AFTER wer_macht");
            $wpdb->query("ALTER TABLE $events_table ADD KEY archived (archived)");
        }
        update_option('task_table_db_version', '1.4');
    }
}
add_action('plugins_loaded', 'task_table_maybe_upgrade_schema');
