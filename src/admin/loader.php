<?php
/**
 * Admin screens for the Task Table plugin: menu registration, assets and the screen files.
 *
 * Each screen lives in its own file and is split into "handle the request", "query the view data"
 * and "render". The page callbacks run inside WordPress's admin page output, so request handlers
 * return notices instead of redirecting.
 *
 * @package TaskTable
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/events.php';
require_once __DIR__ . '/signups.php';
require_once __DIR__ . '/subscribers.php';
require_once __DIR__ . '/categories.php';
require_once __DIR__ . '/email-settings.php';

/**
 * Register admin pages
 */
function task_table_register_admin_pages() {
	add_menu_page(
		__( 'Task Table', 'task-table' ),
		__( 'Task Table', 'task-table' ),
		'manage_task_table',
		'task-table',
		'task_table_admin_dashboard_page',
		'dashicons-list-view',
		30
	);

	add_submenu_page(
		'task-table',
		__( 'Events', 'task-table' ),
		__( 'Events', 'task-table' ),
		'manage_task_table',
		'task-table',
		'task_table_admin_dashboard_page'
	);

	add_submenu_page(
		'task-table',
		__( 'Signups', 'task-table' ),
		__( 'Signups', 'task-table' ),
		'manage_task_table',
		'task-table-signups',
		'task_table_admin_signups_page'
	);

	add_submenu_page(
		'task-table',
		__( 'Subscribers', 'task-table' ),
		__( 'Subscribers', 'task-table' ),
		'manage_task_table',
		'task-table-subscribers',
		'task_table_admin_subscribers_page'
	);

	add_submenu_page(
		'task-table',
		__( 'Categories', 'task-table' ),
		__( 'Categories', 'task-table' ),
		'manage_task_table',
		'task-table-categories',
		'task_table_admin_categories_page'
	);

	add_submenu_page(
		'task-table',
		__( 'Email Settings', 'task-table' ),
		__( 'Email Settings', 'task-table' ),
		'manage_task_table',
		'task-table-email-settings',
		'task_table_admin_email_settings_page'
	);
}
add_action( 'admin_menu', 'task_table_register_admin_pages' );

/**
 * Enqueue the admin stylesheet and script on the plugin's own screens only.
 *
 * @param string $hook Current admin page hook suffix.
 */
function task_table_admin_enqueue_scripts( $hook ) {
	if ( strpos( $hook, 'task-table' ) === false ) {
		return;
	}

	wp_enqueue_style(
		'task-table-admin',
		TASK_TABLE_PLUGIN_URL . 'admin/admin.css',
		array(),
		filemtime( TASK_TABLE_PLUGIN_DIR . 'admin/admin.css' )
	);

	wp_enqueue_script(
		'task-table-admin',
		TASK_TABLE_PLUGIN_URL . 'admin/admin.js',
		array( 'jquery' ),
		filemtime( TASK_TABLE_PLUGIN_DIR . 'admin/admin.js' ),
		true
	);

	if ( strpos( $hook, 'task-table-email-settings' ) !== false ) {
		wp_localize_script( 'task-table-admin', 'taskTableAdminEmail', task_table_admin_email_settings_script_data() );
	}
}
add_action( 'admin_enqueue_scripts', 'task_table_admin_enqueue_scripts' );
