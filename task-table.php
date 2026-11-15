<?php
/**
 * Plugin Name:       Task Table
 * Description:       Publish a table of open tasks or event requests, let volunteers sign up, and notify subscribers about new entries.
 * Version:           1.2.0
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

// Define plugin constants
define( 'TASK_TABLE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TASK_TABLE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'TASK_TABLE_PLUGIN_FILE', __FILE__ );
define( 'TASK_TABLE_SIGNUP_NOTIFICATIONS_OPTION', 'task_table_signup_notification_emails' );
define( 'TASK_TABLE_SIGNUP_NOTIFICATION_RECIPIENTS_OPTION', 'task_table_signup_notification_recipients' );

// Include required files
require_once TASK_TABLE_PLUGIN_DIR . 'build/database.php';
require_once TASK_TABLE_PLUGIN_DIR . 'build/email-notifications.php';
require_once TASK_TABLE_PLUGIN_DIR . 'build/api.php';
require_once TASK_TABLE_PLUGIN_DIR . 'build/subscriptions.php';
require_once TASK_TABLE_PLUGIN_DIR . 'build/admin-pages.php';

/**
 * Load translations shipped in /languages (de_DE for the English admin strings).
 */
function task_table_load_textdomain() {
	load_plugin_textdomain( 'task-table', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'task_table_load_textdomain', 1 );

/**
 * Column labels for the event table, notification emails and the export.
 *
 * The defaults fit the original use case (requests from universities). Sites with a
 * different context can rename the columns with the `task_table_column_labels` filter:
 *
 *     add_filter('task_table_column_labels', function ($labels) {
 *         $labels['name_hochschule'] = 'Organisation';
 *         return $labels;
 *     });
 *
 * @return array<string, string> Keyed by database column.
 */
function task_table_get_column_labels() {
	$labels = array(
		'was_angefragt'   => __( 'What is requested?', 'task-table' ),
		'kontext'         => __( 'Context', 'task-table' ),
		'name_hochschule' => __( 'University name', 'task-table' ),
		'tn_anzahl'       => __( 'Participants', 'task-table' ),
		'zeitpunkt'       => __( 'Planned date', 'task-table' ),
		'auftragdetails'  => __( 'Request details', 'task-table' ),
		'wer_macht'       => __( 'Who\'s doing it? / Sign up', 'task-table' ),
	);
	return (array) apply_filters( 'task_table_column_labels', $labels );
}

// Register activation hook
register_activation_hook( __FILE__, 'task_table_create_database_tables' );
register_activation_hook( __FILE__, 'task_table_initialize_options' );

/**
 * Initialize plugin options on activation
 */
function task_table_initialize_options() {
	// Only set default notification emails if the option doesn't exist
	if ( ! get_option( TASK_TABLE_SIGNUP_NOTIFICATIONS_OPTION ) ) {
		// Initialize with empty array
		update_option( TASK_TABLE_SIGNUP_NOTIFICATIONS_OPTION, array() );
	}
}

/**
 * Register the block using the metadata loaded from the `block.json` file.
 */
function task_table_register_blocks() {
	register_block_type( __DIR__ . '/build/task-table' );
	register_block_type(
		__DIR__ . '/build/new-event-signup',
		array(
			'render_callback' => 'task_table_render_new_event_signup_block',
		)
	);
}
add_action( 'init', 'task_table_register_blocks' );

/**
 * Load the block scripts' translations from the plugin's own languages/ folder.
 *
 * block.json registration only looks in wp-content/languages/plugins. The JSON files
 * are built by dev/make-translations.py.
 */
function task_table_set_script_translations() {
	$scripts = array(
		'task-table/task-table'       => array( 'editorScript' ),
		'task-table/new-event-signup' => array( 'editorScript', 'viewScript' ),
	);
	foreach ( $scripts as $block => $fields ) {
		foreach ( $fields as $field ) {
			wp_set_script_translations( generate_block_asset_handle( $block, $field ), 'task-table', TASK_TABLE_PLUGIN_DIR . 'languages' );
		}
	}
}
add_action( 'init', 'task_table_set_script_translations', 20 );

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
function task_table_render_new_event_signup_block( $attributes = array(), $content = '', $block = null ) {
	$template_paths = array(
		TASK_TABLE_PLUGIN_DIR . 'build/new-event-signup/render.php',
		TASK_TABLE_PLUGIN_DIR . 'src/new-event-signup/render.php',
	);

	foreach ( $template_paths as $template ) {
		if ( ! file_exists( $template ) ) {
			continue;
		}

		ob_start();
		$returned        = include $template;
		$buffered_output = ob_get_clean();

		if ( is_string( $returned ) && $returned !== '' ) {
			return $returned;
		}

		if ( $buffered_output !== false ) {
			return $buffered_output;
		}
	}

	return '';
}

/**
 * Enqueue frontend styles for the modal
 */
function task_table_enqueue_frontend_styles() {
	if ( is_admin() ) {
		return;
	}

	$styles = array(
		'task-table-frontend'         => 'build/task-table/style-index.css',
		'task-table-new-event-signup' => 'build/new-event-signup/style-index.css',
	);

	foreach ( $styles as $handle => $relative_path ) {
		$full_path = TASK_TABLE_PLUGIN_DIR . $relative_path;

		if ( ! file_exists( $full_path ) ) {
			continue;
		}

		wp_enqueue_style(
			$handle,
			TASK_TABLE_PLUGIN_URL . $relative_path,
			array(),
			filemtime( $full_path )
		);
	}
}
add_action( 'wp_enqueue_scripts', 'task_table_enqueue_frontend_styles' );

/**
 * Enqueue admin scripts and styles
 */
function task_table_admin_enqueue_scripts( $hook ) {
	if ( strpos( $hook, 'task-table' ) !== false ) {
		wp_enqueue_script(
			'task-table-admin',
			TASK_TABLE_PLUGIN_URL . 'admin/admin.js',
			array( 'jquery' ),
			filemtime( TASK_TABLE_PLUGIN_DIR . 'admin/admin.js' ),
			true
		);
	}
}
add_action( 'admin_enqueue_scripts', 'task_table_admin_enqueue_scripts' );

/**
 * Pass AJAX URL to front-end scripts
 */
function task_table_localize_scripts() {
	$localization = array(
		'ajaxurl' => admin_url( 'admin-ajax.php' ),
		'resturl' => esc_url_raw( rest_url() ),
		'nonce'   => wp_create_nonce( 'wp_rest' ),
	);

	if ( ! class_exists( 'WP_Block_Type_Registry' ) ) {
		return;
	}

	$registry    = WP_Block_Type_Registry::get_instance();
	$block_names = array( 'task-table/task-table', 'task-table/new-event-signup' );

	foreach ( $block_names as $block_name ) {
		$block_type = $registry->get_registered( $block_name );

		if ( ! $block_type || empty( $block_type->view_script_handles ) ) {
			continue;
		}

		foreach ( $block_type->view_script_handles as $handle ) {
			if ( wp_script_is( $handle, 'registered' ) ) {
				wp_localize_script( $handle, 'taskTableSettings', $localization );
			}
		}
	}
}
add_action( 'wp_enqueue_scripts', 'task_table_localize_scripts' );

/**
 * Add custom capabilities on plugin activation
 */
function task_table_add_capabilities() {
	$admin_role = get_role( 'administrator' );

	if ( $admin_role ) {
		$admin_role->add_cap( 'manage_task_table' );
		$admin_role->add_cap( 'edit_task_table_events' );
	}
}
register_activation_hook( __FILE__, 'task_table_add_capabilities' );

/**
 * Clean up on plugin deactivation
 */
function task_table_deactivation() {
	// Nothing to do yet
}
register_deactivation_hook( __FILE__, 'task_table_deactivation' );

/**
 * Handle front-end confirmation links (non-REST fallback)
 */
function task_table_handle_frontend_confirmation() {
	// phpcs:disable WordPress.Security.NonceVerification -- authenticated by the double opt-in token from the email; a nonce would expire before people click the link.
	if ( ! isset( $_GET['task_table_confirm_subscription'] ) ) {
		return;
	}
	$email = isset( $_GET['email'] ) ? sanitize_email( wp_unslash( $_GET['email'] ) ) : '';
	$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
	if ( ! $email || ! $token ) {
		return; // Silent fail to avoid revealing info
	}
	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- constant read by page-cache plugins.
	}
	nocache_headers();
	$result = task_table_confirm_subscription_token( $email, $token );
	if ( $result === 'confirmed' ) {
		wp_die( esc_html__( 'Thank you! Your subscription is confirmed.', 'task-table' ), esc_html__( 'Subscription confirmed', 'task-table' ), array( 'response' => 200 ) );
	} elseif ( $result === 'error' ) {
		wp_die( esc_html__( 'Technical error: the confirmation could not be saved.', 'task-table' ), esc_html__( 'Error', 'task-table' ), array( 'response' => 500 ) );
	}
	wp_die( esc_html__( 'This confirmation link is invalid, expired or has already been used.', 'task-table' ), esc_html__( 'Invalid link', 'task-table' ), array( 'response' => 400 ) );
	// phpcs:enable WordPress.Security.NonceVerification
}
add_action( 'template_redirect', 'task_table_handle_frontend_confirmation' );
