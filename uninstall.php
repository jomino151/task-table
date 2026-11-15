<?php
/**
 * Runs when the plugin is deleted from the Plugins screen (not on deactivation).
 * Removes the plugin's tables, options, transients and capabilities, including all
 * personal data (signups and subscriber addresses).
 *
 * The legacy, unprefixed `moderator_email` option is left alone because other plugins may use it.
 *
 * @package TaskTable
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

foreach ( array( 'task_table_subscriber_categories', 'task_table_subscribers', 'task_table_signups', 'task_table_events', 'task_table_categories' ) as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- constant names
}

$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( 'task_table_' ) . '%',
		$wpdb->esc_like( '_transient_tt_rl_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_tt_rl_' ) . '%'
	)
);

foreach ( wp_roles()->role_objects as $role ) {
	$role->remove_cap( 'manage_task_table' );
	$role->remove_cap( 'edit_task_table_events' );
}
