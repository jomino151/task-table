<?php
/**
 * Database setup and utilities for task table plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! defined( 'TASK_TABLE_DEFAULT_CATEGORY_OPTION' ) ) {
	define( 'TASK_TABLE_DEFAULT_CATEGORY_OPTION', 'task_table_default_category_id' );
}

/**
 * Create the necessary database tables during plugin activation
 */
function task_table_create_database_tables() {
	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();

	// Create events table
	$events_table = $wpdb->prefix . 'task_table_events';
	$events_sql   = "CREATE TABLE $events_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        was_angefragt text NOT NULL,
        kontext text NOT NULL,
        name_hochschule text NOT NULL,
        tn_anzahl text NOT NULL,
        zeitpunkt text NOT NULL,
        auftragdetails text NOT NULL,
        wer_macht text NOT NULL,
        category_id mediumint(9) NULL,
        archived tinyint(1) DEFAULT 0 NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        KEY archived (archived),
        KEY category_id (category_id)
    ) $charset_collate;";

	// Create signups table
	$signups_table = $wpdb->prefix . 'task_table_signups';
	$signups_sql   = "CREATE TABLE $signups_table (
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

	$categories_table = $wpdb->prefix . 'task_table_categories';
	$categories_sql   = "CREATE TABLE $categories_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name varchar(190) NOT NULL,
        slug varchar(190) NOT NULL,
        description text NULL,
        sort_order smallint(6) DEFAULT 0 NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY slug (slug)
    ) $charset_collate;";

	$subscriber_categories_table = $wpdb->prefix . 'task_table_subscriber_categories';
	$subscriber_categories_sql   = "CREATE TABLE $subscriber_categories_table (
        subscriber_id mediumint(9) NOT NULL,
        category_id mediumint(9) NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY (subscriber_id, category_id),
        KEY category_id (category_id)
    ) $charset_collate;";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $events_sql );
	dbDelta( $signups_sql );
	dbDelta( $subscribers_sql );
	dbDelta( $categories_sql );
	dbDelta( $subscriber_categories_sql );

	$default_category_id = task_table_ensure_default_category();

	if ( $default_category_id ) {
		// Ensure every event belongs to a valid category
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE $events_table SET category_id = %d WHERE category_id IS NULL OR category_id = 0",
				$default_category_id
			)
		);

		// Assign subscribers to the default category if none stored yet
		$wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO $subscriber_categories_table (subscriber_id, category_id)
             SELECT id, %d FROM $subscribers_table",
				$default_category_id
			)
		);
	}

	// Set version to track database changes
	update_option( 'task_table_db_version', '1.6' );
}

/**
 * Migrate existing data from block attributes to database tables
 */
function task_table_migrate_existing_data() {
	global $wpdb;

	// Only run migration once
	if ( get_option( 'task_table_migration_complete' ) ) {
		return;
	}

	// Get all task-table blocks from posts
	$posts_with_blocks = $wpdb->get_results(
		"SELECT ID, post_content FROM {$wpdb->posts} 
        WHERE post_content LIKE '%wp:task-table/task-table%' 
        AND post_status = 'publish'"
	);

	$events_table   = $wpdb->prefix . 'task_table_events';
	$migrated_count = 0;

	foreach ( $posts_with_blocks as $post ) {
		// Parse blocks from content
		$blocks = parse_blocks( $post->post_content );

		foreach ( $blocks as $block ) {
			if ( $block['blockName'] === 'task-table/task-table' && ! empty( $block['attrs']['rows'] ) ) {
				$rows = $block['attrs']['rows'];

				foreach ( $rows as $row ) {
					// Skip rows without ID
					if ( empty( $row['id'] ) ) {
						continue;
					}

					// Check if this event is already migrated
					$exists = $wpdb->get_var(
						$wpdb->prepare(
							"SELECT COUNT(*) FROM $events_table WHERE id = %d",
							$row['id']
						)
					);

					if ( $exists ) {
						continue;
					}

					// Insert into events table
					$wpdb->insert(
						$events_table,
						array(
							'id'              => $row['id'],
							'was_angefragt'   => $row['wasAngefragt'] ?? '',
							'kontext'         => $row['kontext'] ?? '',
							'name_hochschule' => $row['nameHochschule'] ?? '',
							'tn_anzahl'       => $row['tnAnzahl'] ?? '',
							'zeitpunkt'       => $row['zeitpunkt'] ?? '',
							'auftragdetails'  => $row['auftragdetails'] ?? '',
							'wer_macht'       => $row['werMacht'] ?? '',
							'created_at'      => current_time( 'mysql' ),
							'updated_at'      => current_time( 'mysql' ),
						)
					);

					++$migrated_count;
				}
			}
		}
	}

	// Migrate existing signups table if it exists
	$old_signups_table = $wpdb->prefix . 'event_signups';
	$new_signups_table = $wpdb->prefix . 'task_table_signups';

	if ( $wpdb->get_var( "SHOW TABLES LIKE '$old_signups_table'" ) === $old_signups_table ) {
		$signups = $wpdb->get_results( "SELECT * FROM $old_signups_table" );

		foreach ( $signups as $signup ) {
			$wpdb->insert(
				$new_signups_table,
				array(
					'event_id'   => $signup->event_id,
					'user_name'  => $signup->user_name,
					'user_email' => $signup->user_email,
					'status'     => $signup->status,
					'created_at' => current_time( 'mysql' ),
					'updated_at' => current_time( 'mysql' ),
				)
			);
		}
	}

	$default_category_id = task_table_get_default_category_id();
	if ( $default_category_id ) {
		$subscriber_categories_table = $wpdb->prefix . 'task_table_subscriber_categories';
		$events_table                = $wpdb->prefix . 'task_table_events';
		$subscribers_table           = $wpdb->prefix . 'task_table_subscribers';

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE $events_table SET category_id = %d WHERE category_id IS NULL OR category_id = 0",
				$default_category_id
			)
		);

		$wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO $subscriber_categories_table (subscriber_id, category_id)
             SELECT id, %d FROM $subscribers_table",
				$default_category_id
			)
		);
	}

	update_option( 'task_table_migration_complete', true );
	update_option( 'task_table_migrated_events', $migrated_count );
}

// Use the activation hook to create tables
register_activation_hook( TASK_TABLE_PLUGIN_FILE, 'task_table_create_database_tables' );

// Hook for migrating existing data
add_action( 'admin_init', 'task_table_migrate_existing_data' );

/**
 * Perform database schema upgrades for existing installations.
 * Currently ensures the confirmation_token column exists for subscribers table (v1.1).
 */
function task_table_maybe_upgrade_schema() {
	global $wpdb;
	$current_version   = get_option( 'task_table_db_version', '1.0' );
	$subscribers_table = $wpdb->prefix . 'task_table_subscribers';
	$signups_table     = $wpdb->prefix . 'task_table_signups';
	$events_table      = $wpdb->prefix . 'task_table_events';

	// Upgrade to 1.1: add confirmation_token
	if ( version_compare( $current_version, '1.1', '<' ) ) {
		$col = $wpdb->get_results( "SHOW COLUMNS FROM $subscribers_table LIKE 'confirmation_token'" );
		if ( empty( $col ) ) {
			$wpdb->query( "ALTER TABLE $subscribers_table ADD COLUMN confirmation_token varchar(64) DEFAULT NULL" );
			$wpdb->query( "ALTER TABLE $subscribers_table ADD KEY confirmation_token (confirmation_token)" );
		}
		update_option( 'task_table_db_version', '1.1' );
		$current_version = '1.1';
	}

	// Upgrade to 1.2: add updated_at
	if ( version_compare( $current_version, '1.2', '<' ) ) {
		$col2 = $wpdb->get_results( "SHOW COLUMNS FROM $subscribers_table LIKE 'updated_at'" );
		if ( empty( $col2 ) ) {
			$wpdb->query( "ALTER TABLE $subscribers_table ADD COLUMN updated_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL AFTER created_at" );
		}
		update_option( 'task_table_db_version', '1.2' );
		$current_version = '1.2';
	}

	// Upgrade to 1.3: add free_text column to signups
	if ( version_compare( $current_version, '1.3', '<' ) ) {
		$col3 = $wpdb->get_results( "SHOW COLUMNS FROM $signups_table LIKE 'free_text'" );
		if ( empty( $col3 ) ) {
			$wpdb->query( "ALTER TABLE $signups_table ADD COLUMN free_text text NULL AFTER user_email" );
		}
		update_option( 'task_table_db_version', '1.3' );
		$current_version = '1.3';
	}

	// Upgrade to 1.4: add archived column to events
	if ( version_compare( $current_version, '1.4', '<' ) ) {
		$col4 = $wpdb->get_results( "SHOW COLUMNS FROM $events_table LIKE 'archived'" );
		if ( empty( $col4 ) ) {
			$wpdb->query( "ALTER TABLE $events_table ADD COLUMN archived tinyint(1) DEFAULT 0 NOT NULL AFTER wer_macht" );
			$wpdb->query( "ALTER TABLE $events_table ADD KEY archived (archived)" );
		}
		update_option( 'task_table_db_version', '1.4' );
		$current_version = '1.4';
	}

	// Upgrade to 1.5: introduce categories and event linkage
	if ( version_compare( $current_version, '1.5', '<' ) ) {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$categories_table = $wpdb->prefix . 'task_table_categories';
		$categories_sql   = "CREATE TABLE $categories_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(190) NOT NULL,
            slug varchar(190) NOT NULL,
            description text NULL,
            sort_order smallint(6) DEFAULT 0 NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY slug (slug)
        ) {$wpdb->get_charset_collate()};";
		dbDelta( $categories_sql );

		$col_category = $wpdb->get_results( "SHOW COLUMNS FROM $events_table LIKE 'category_id'" );
		if ( empty( $col_category ) ) {
			$wpdb->query( "ALTER TABLE $events_table ADD COLUMN category_id mediumint(9) NULL AFTER wer_macht" );
			$wpdb->query( "ALTER TABLE $events_table ADD KEY category_id (category_id)" );
		}

		$default_category_id = task_table_ensure_default_category();
		if ( $default_category_id ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE $events_table SET category_id = %d WHERE category_id IS NULL OR category_id = 0",
					$default_category_id
				)
			);
		}

		update_option( 'task_table_db_version', '1.5' );
		$current_version = '1.5';
	}

	// Upgrade to 1.6: subscriber category pivot and default assignments
	if ( version_compare( $current_version, '1.6', '<' ) ) {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$subscriber_categories_table = $wpdb->prefix . 'task_table_subscriber_categories';
		$subscriber_categories_sql   = "CREATE TABLE $subscriber_categories_table (
            subscriber_id mediumint(9) NOT NULL,
            category_id mediumint(9) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY (subscriber_id, category_id),
            KEY category_id (category_id)
        ) {$wpdb->get_charset_collate()};";
		dbDelta( $subscriber_categories_sql );

		$default_category_id = task_table_get_default_category_id();
		if ( $default_category_id ) {
			$subscribers_table = $wpdb->prefix . 'task_table_subscribers';
			$wpdb->query(
				$wpdb->prepare(
					"INSERT IGNORE INTO $subscriber_categories_table (subscriber_id, category_id)
                 SELECT id, %d FROM $subscribers_table",
					$default_category_id
				)
			);
		}

		update_option( 'task_table_db_version', '1.6' );
		$current_version = '1.6';
	}

	// Upgrade to 1.7: email templates used to be saved without wp_unslash(), so quotes were
	// stored as \" and sent to people that way. Remove that one level of escaping once.
	if ( version_compare( $current_version, '1.7', '<' ) ) {
		$template_options = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( 'task_table_email_' ) . '%'
			)
		);
		foreach ( $template_options as $option_name ) {
			$value = get_option( $option_name );
			if ( is_string( $value ) && preg_match( '/\\\\[\'"\\\\]/', $value ) ) {
				update_option( $option_name, wp_unslash( $value ) );
			}
		}
		update_option( 'task_table_db_version', '1.7' );
		$current_version = '1.7';
	}
}
add_action( 'plugins_loaded', 'task_table_maybe_upgrade_schema' );

/**
 * Ensure the categories table exists before running queries.
 *
 * @return bool
 */
function task_table_categories_table_exists() {
	global $wpdb;
	$categories_table = $wpdb->prefix . 'task_table_categories';
	return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $categories_table ) ) === $categories_table;
}

/**
 * Create or retrieve the default "General" category.
 *
 * @return int Default category ID or 0 on failure.
 */
function task_table_ensure_default_category() {
	global $wpdb;

	if ( ! task_table_categories_table_exists() ) {
		return 0;
	}

	$categories_table = $wpdb->prefix . 'task_table_categories';
	$default_id       = (int) get_option( TASK_TABLE_DEFAULT_CATEGORY_OPTION, 0 );

	if ( $default_id > 0 ) {
		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $categories_table WHERE id = %d", $default_id ) );
		if ( $exists ) {
			return $default_id;
		}
	}

	$default_slug = 'general';
	$existing_id  = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $categories_table WHERE slug = %s", $default_slug ) );
	if ( $existing_id ) {
		update_option( TASK_TABLE_DEFAULT_CATEGORY_OPTION, (int) $existing_id );
		return (int) $existing_id;
	}

	$inserted = $wpdb->insert(
		$categories_table,
		array(
			'name'        => __( 'General', 'task-table' ),
			'slug'        => $default_slug,
			'description' => '',
			'sort_order'  => 0,
			'created_at'  => current_time( 'mysql' ),
			'updated_at'  => current_time( 'mysql' ),
		)
	);

	if ( $inserted !== false ) {
		$new_id = (int) $wpdb->insert_id;
		update_option( TASK_TABLE_DEFAULT_CATEGORY_OPTION, $new_id );
		return $new_id;
	}

	return 0;
}

/**
 * Retrieve the default category ID, ensuring it exists.
 *
 * @return int
 */
function task_table_get_default_category_id() {
	$default_id = (int) get_option( TASK_TABLE_DEFAULT_CATEGORY_OPTION, 0 );
	if ( $default_id > 0 ) {
		return $default_id;
	}

	return task_table_ensure_default_category();
}

/**
 * Generate a unique slug for a category.
 *
 * @param string $desired_slug
 * @param int    $exclude_id Optional category ID to exclude from uniqueness check.
 * @return string
 */
function task_table_get_unique_category_slug( $desired_slug, $exclude_id = 0 ) {
	global $wpdb;

	if ( ! task_table_categories_table_exists() ) {
		return sanitize_title( $desired_slug );
	}

	$slug = sanitize_title( $desired_slug );
	if ( $slug === '' ) {
		$slug = 'category';
	}

	$categories_table = $wpdb->prefix . 'task_table_categories';
	$base_slug        = $slug;
	$counter          = 2;

	while ( $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM $categories_table WHERE slug = %s AND id <> %d",
			$slug,
			(int) $exclude_id
		)
	) ) {
		$slug = $base_slug . '-' . $counter;
		++$counter;
		if ( $counter > 200 ) {
			break;
		}
	}

	return $slug;
}

/**
 * Retrieve all categories ordered by sort order and name.
 *
 * @return array<int, array<string, mixed>>
 */
function task_table_get_categories() {
	global $wpdb;

	if ( ! task_table_categories_table_exists() ) {
		return array();
	}

	$categories_table = $wpdb->prefix . 'task_table_categories';
	return $wpdb->get_results( "SELECT * FROM $categories_table ORDER BY sort_order ASC, name ASC", ARRAY_A );
}

/**
 * Retrieve a single category by ID.
 *
 * @param int $category_id
 * @return array<string, mixed>|null
 */
function task_table_get_category( $category_id ) {
	global $wpdb;

	if ( ! task_table_categories_table_exists() ) {
		return null;
	}

	$categories_table = $wpdb->prefix . 'task_table_categories';
	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $categories_table WHERE id = %d", (int) $category_id ), ARRAY_A );
}

/**
 * Retrieve category IDs selected for a subscriber.
 *
 * @param int $subscriber_id
 * @return int[]
 */
function task_table_get_subscriber_categories( $subscriber_id ) {
	global $wpdb;

	if ( ! task_table_categories_table_exists() ) {
		return array();
	}

	$subscriber_categories_table = $wpdb->prefix . 'task_table_subscriber_categories';
	$subscribers_table           = $wpdb->prefix . 'task_table_subscribers';

	// Ensure pivot table exists
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $subscriber_categories_table ) ) !== $subscriber_categories_table ) {
		return array();
	}

	$ids = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT category_id FROM $subscriber_categories_table WHERE subscriber_id = %d",
			(int) $subscriber_id
		)
	);

	if ( empty( $ids ) ) {
		// Fallback: if subscriber exists but no categories stored, assume default
		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $subscribers_table WHERE id = %d", (int) $subscriber_id ) );
		if ( $exists ) {
			$default_id = task_table_get_default_category_id();
			return $default_id ? array( $default_id ) : array();
		}
	}

	return array_map( 'intval', $ids );
}

/**
 * Return a comma-separated list of category names for a subscriber.
 *
 * @param int $subscriber_id
 * @return string
 */
function task_table_get_subscriber_category_names( $subscriber_id ) {
	global $wpdb;

	if ( ! task_table_categories_table_exists() ) {
		return '';
	}

	$subscriber_categories_table = $wpdb->prefix . 'task_table_subscriber_categories';
	$categories_table            = $wpdb->prefix . 'task_table_categories';

	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $subscriber_categories_table ) ) !== $subscriber_categories_table ) {
		return '';
	}

	$names = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT c.name FROM $categories_table c
         INNER JOIN $subscriber_categories_table sc ON sc.category_id = c.id
         WHERE sc.subscriber_id = %d
         ORDER BY c.sort_order ASC, c.name ASC",
			(int) $subscriber_id
		)
	);

	return implode( ', ', $names );
}

/**
 * Sync the categories assigned to a subscriber.
 *
 * @param int   $subscriber_id
 * @param int[] $category_ids
 * @return void
 */
function task_table_sync_subscriber_categories( $subscriber_id, $category_ids ) {
	global $wpdb;

	if ( ! task_table_categories_table_exists() ) {
		return;
	}

	$subscriber_categories_table = $wpdb->prefix . 'task_table_subscriber_categories';
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $subscriber_categories_table ) ) !== $subscriber_categories_table ) {
		return;
	}

	$clean_ids = array();
	foreach ( (array) $category_ids as $id ) {
		$id = (int) $id;
		if ( $id > 0 && task_table_get_category( $id ) ) {
			$clean_ids[] = $id;
		}
	}

	if ( empty( $clean_ids ) ) {
		$default_id = task_table_get_default_category_id();
		if ( $default_id ) {
			$clean_ids[] = $default_id;
		}
	}

	$clean_ids = array_values( array_unique( $clean_ids ) );

	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM $subscriber_categories_table WHERE subscriber_id = %d",
			(int) $subscriber_id
		)
	);

	foreach ( $clean_ids as $category_id ) {
		$wpdb->insert(
			$subscriber_categories_table,
			array(
				'subscriber_id' => (int) $subscriber_id,
				'category_id'   => (int) $category_id,
				'created_at'    => current_time( 'mysql' ),
			)
		);
	}
}
