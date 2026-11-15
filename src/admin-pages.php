<?php
/**
 * Admin pages for Task Table plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

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
		__( 'E-Mail-Einstellungen', 'task-table' ),
		__( 'E-Mail-Einstellungen', 'task-table' ),
		'manage_task_table',
		'task-table-email-settings',
		'task_table_admin_email_settings_page'
	);
}
add_action( 'admin_menu', 'task_table_register_admin_pages' );

/**
 * Print shared styles for Task Table admin pages.
 */
function task_table_print_admin_styles() {
	static $printed = false;

	if ( $printed ) {
		return;
	}

	$printed = true;
	?>
	<style>
		.task-table-admin {
			--tt-surface: #ffffff;
			--tt-surface-muted: #f6f7f7;
			--tt-border: #d1d5db;
			--tt-border-strong: #b6bec9;
			--tt-text: #1d2327;
			--tt-text-muted: #5f6c81;
			--tt-primary: #2271b1;
			--tt-primary-muted: #e1effa;
			--tt-radius: 10px;
			color: var(--tt-text);
		}

		.task-table-admin .tt-page-header {
			display: flex;
			flex-wrap: wrap;
			justify-content: space-between;
			align-items: flex-start;
			gap: 24px;
			margin-bottom: 28px;
		}

		.task-table-admin .tt-page-title h1 {
			margin: 0;
			font-size: 26px;
			line-height: 1.2;
		}

		.task-table-admin .tt-page-title p {
			margin-top: 6px;
			margin-bottom: 0;
			color: var(--tt-text-muted);
			max-width: 460px;
		}

		.task-table-admin .tt-page-actions {
			display: flex;
			flex-wrap: wrap;
			gap: 10px;
		}

		.task-table-admin .tt-card-grid {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
			gap: 18px;
			margin-bottom: 28px;
		}

		.task-table-admin .tt-card {
			background: var(--tt-surface);
			border: 1px solid var(--tt-border);
			border-radius: var(--tt-radius);
			padding: 18px 20px;
			box-shadow: 0 6px 16px rgba(15, 23, 42, 0.06);
			position: relative;
		}

		.task-table-admin .tt-card h3 {
			margin: 0;
			font-size: 14px;
			font-weight: 600;
			color: var(--tt-text-muted);
			text-transform: uppercase;
			letter-spacing: 0.04em;
		}

		.task-table-admin .tt-card-value {
			font-size: 32px;
			font-weight: 600;
			margin-top: 12px;
			display: flex;
			align-items: baseline;
			gap: 8px;
		}

		.task-table-admin .tt-card-value small {
			font-size: 13px;
			font-weight: 500;
			color: var(--tt-text-muted);
		}

		.task-table-admin .tt-card-meta {
			margin-top: 14px;
			font-size: 13px;
			color: var(--tt-text-muted);
		}

		.task-table-admin .tt-toolbar {
			display: flex;
			justify-content: space-between;
			align-items: center;
			gap: 16px;
			margin-bottom: 18px;
			flex-wrap: wrap;
		}

		.task-table-admin .tt-pill-toggle {
			display: inline-flex;
			background: var(--tt-surface);
			border: 1px solid var(--tt-border);
			border-radius: 999px;
			padding: 4px;
			gap: 4px;
		}

		.task-table-admin .tt-pill-toggle a {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			gap: 6px;
			padding: 6px 16px;
			font-size: 13px;
			font-weight: 500;
			border-radius: 999px;
			color: var(--tt-text-muted);
			text-decoration: none;
		}

		.task-table-admin .tt-pill-toggle a.is-active {
			background: var(--tt-primary);
			color: #ffffff;
		}

		.task-table-admin .tt-toolbar .tt-actions {
			display: flex;
			gap: 8px;
			flex-wrap: wrap;
		}

		.task-table-admin .tt-filters {
			background: var(--tt-surface);
			border: 1px solid var(--tt-border);
			border-radius: var(--tt-radius);
			padding: 18px 20px;
			display: flex;
			flex-wrap: wrap;
			gap: 16px;
			align-items: flex-end;
			margin-bottom: 22px;
		}

		.task-table-admin .tt-filter-group {
			display: flex;
			flex-direction: column;
			gap: 6px;
			min-width: 200px;
		}

		.task-table-admin .tt-filter-group label {
			font-size: 13px;
			font-weight: 500;
			color: var(--tt-text-muted);
			text-transform: uppercase;
			letter-spacing: 0.04em;
		}

		.task-table-admin .tt-filter-group input,
		.task-table-admin .tt-filter-group select {
			min-height: 36px;
		}

		.task-table-admin .tt-filter-actions {
			display: flex;
			gap: 8px;
			align-items: center;
		}

		.task-table-admin .tt-filter-actions .button {
			box-shadow: none;
			min-height: 36px;
			display: inline-flex;
			align-items: center;
		}

		.task-table-admin .tt-filter-actions .button:focus,
		.task-table-admin .tt-filter-actions .button:focus-visible {
			box-shadow: 0 0 0 2px #ffffff, 0 0 0 4px rgba(34, 113, 177, 0.45);
		}

		.task-table-admin .tt-filter-actions .button-link {
			padding-left: 0;
		}

		.task-table-admin .tt-empty-state {
			background: var(--tt-surface);
			border: 1px dashed var(--tt-border-strong);
			border-radius: var(--tt-radius);
			padding: 36px 40px;
			text-align: center;
		}

		.task-table-admin .tt-empty-state h3 {
			margin: 0 0 12px;
			font-size: 20px;
		}

		.task-table-admin .tt-empty-state p {
			max-width: 420px;
			margin: 0 auto 18px;
			color: var(--tt-text-muted);
		}

		.task-table-admin .tt-empty-state .button {
			margin: 0 6px;
		}

		.task-table-admin table.tt-table {
			border: 1px solid var(--tt-border);
			border-radius: var(--tt-radius);
			overflow: hidden;
			background: var(--tt-surface);
		}

		.task-table-admin table.tt-table thead th {
			background: var(--tt-surface-muted);
			font-weight: 600;
			color: var(--tt-text-muted);
		}

		.task-table-admin table.tt-table tbody tr:hover {
			background: #f8fbff;
		}

		.task-table-admin .tt-pill {
			display: inline-flex;
			align-items: center;
			gap: 6px;
			padding: 3px 12px;
			border-radius: 999px;
			font-size: 12px;
			font-weight: 600;
			text-transform: capitalize;
		}

		.task-table-admin .tt-pill.is-pending {
			background: #fff4de;
			color: #8a5300;
		}

		.task-table-admin .tt-pill.is-approved {
			background: #e6f4ea;
			color: #0b6b34;
		}

		.task-table-admin .tt-pill.is-denied {
			background: #fde8e8;
			color: #b42318;
		}

		.task-table-admin .tt-pill.is-archived {
			background: #edf2f7;
			color: #4a5667;
		}

		.task-table-admin .tt-pill.is-active {
			background: var(--tt-primary-muted);
			color: var(--tt-primary);
		}

		.task-table-admin .tt-pill.is-unconfirmed {
			background: #ebe9fe;
			color: #4338ca;
		}

		.task-table-admin .tt-actions {
			display: inline-flex;
			flex-wrap: wrap;
			gap: 6px;
		}

		.task-table-admin .tt-actions .button {
			display: inline-flex;
			align-items: center;
			gap: 4px;
		}

		.task-table-admin .tt-actions .button .dashicons {
			font-size: 16px;
			line-height: 1;
		}

		.task-table-admin .tt-actions .button.is-destructive {
			background: #d63638;
			color: #fff;
			border-color: #b32d2e;
		}

		.task-table-admin .tt-section {
			margin-bottom: 32px;
		}

		.task-table-admin .tt-section h2 {
			margin-bottom: 16px;
		}

		.task-table-admin .tt-inline-notice {
			border-radius: 8px;
			padding: 12px 16px;
			margin-bottom: 20px;
		}

		.task-table-admin .tt-inline-notice.success {
			background: #effaf3;
			border-left: 4px solid #1a7f37;
		}

		.task-table-admin .tt-inline-notice.error {
			background: #fdf3f1;
			border-left: 4px solid #d63638;
		}

		.task-table-admin .tt-columns {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
			gap: 20px;
			margin-bottom: 32px;
		}

		.task-table-admin .tt-form-card {
			background: var(--tt-surface);
			border: 1px solid var(--tt-border);
			border-radius: var(--tt-radius);
			padding: 20px 22px;
			box-shadow: 0 6px 18px rgba(15, 23, 42, 0.05);
		}

		.task-table-admin .tt-form-card h2,
		.task-table-admin .tt-form-card h3 {
			margin-top: 0;
		}

		.task-table-admin .tt-status-tabs {
			display: inline-flex;
			flex-wrap: wrap;
			gap: 6px;
			margin: 0;
			padding: 0;
			list-style: none;
		}

		.task-table-admin .tt-status-tabs a {
			display: inline-flex;
			align-items: center;
			gap: 6px;
			padding: 6px 14px;
			border-radius: 999px;
			border: 1px solid transparent;
			background: var(--tt-surface-muted);
			color: var(--tt-text-muted);
			text-decoration: none;
			font-weight: 500;
		}

		.task-table-admin .tt-status-tabs a.is-current {
			background: var(--tt-primary);
			color: #fff;
			border-color: var(--tt-primary);
		}

		.task-table-admin .tt-tag {
			display: inline-flex;
			align-items: center;
			gap: 6px;
			background: var(--tt-primary-muted);
			color: var(--tt-primary);
			border-radius: 999px;
			padding: 4px 10px;
			font-size: 12px;
			font-weight: 600;
		}

		.task-table-admin .tt-tag button {
			border: none;
			background: none;
			color: inherit;
			cursor: pointer;
			padding: 0;
		}

		.task-table-admin .email-tags-container {
			display: flex;
			flex-wrap: wrap;
			gap: 8px;
			border: 1px solid var(--tt-border);
			border-radius: var(--tt-radius);
			padding: 10px 12px;
			min-height: 44px;
			background: var(--tt-surface-muted);
		}

		.task-table-admin .email-tag {
			background: var(--tt-primary-muted);
			border: 1px solid var(--tt-primary);
			border-radius: 999px;
			padding: 4px 10px;
			display: inline-flex;
			align-items: center;
			gap: 6px;
			font-size: 12px;
		}

		.task-table-admin .email-tag .remove-email {
			border: none;
			background: none;
			color: var(--tt-primary);
			font-size: 16px;
			line-height: 1;
			cursor: pointer;
		}

		.task-table-admin .email-input-container {
			display: flex;
			flex-wrap: wrap;
			gap: 8px;
			margin-top: 12px;
		}

		.task-table-admin .email-input-container input[type="email"] {
			min-width: 240px;
		}

		.task-table-admin .signup-free-text {
			max-width: 320px;
			white-space: normal;
			word-break: break-word;
		}

		.task-table-admin .tt-table-scroll {
			overflow-x: auto;
			border-radius: var(--tt-radius);
		}

		.task-table-admin .tt-signups-table {
			table-layout: auto;
			min-width: 1000px;
		}

		.task-table-admin .tt-signups-table th:nth-child(1),
		.task-table-admin .tt-signups-table td:nth-child(1) {
			width: 15%;
			min-width: 170px;
		}

		.task-table-admin .tt-signups-table th:nth-child(2),
		.task-table-admin .tt-signups-table td:nth-child(2) {
			width: 15%;
			min-width: 180px;
		}

		.task-table-admin .tt-signups-table th:nth-child(3),
		.task-table-admin .tt-signups-table td:nth-child(3) {
			width: 42%;
			min-width: 240px;
		}

		.task-table-admin .tt-signups-table th:nth-child(4),
		.task-table-admin .tt-signups-table td:nth-child(4) {
			width: 8%;
			min-width: 110px;
		}

		.task-table-admin .tt-signups-table th:nth-child(5),
		.task-table-admin .tt-signups-table td:nth-child(5) {
			width: 10%;
			min-width: 130px;
		}

		.task-table-admin .tt-signups-table th:nth-child(6),
		.task-table-admin .tt-signups-table td:nth-child(6) {
			width: 10%;
			min-width: 170px;
		}

		.task-table-admin .tt-signups-table .signup-free-text {
			max-width: none;
		}

		.task-table-admin .tt-export-modal {
			position: fixed;
			inset: 0;
			z-index: 100000;
			display: flex;
			align-items: center;
			justify-content: center;
			padding: 24px;
			opacity: 0;
			pointer-events: none;
			transition: opacity 0.2s ease-in-out;
		}

		.task-table-admin .tt-export-modal.is-open {
			opacity: 1;
			pointer-events: auto;
		}

		.task-table-admin .tt-export-modal__overlay {
			position: absolute;
			inset: 0;
			background: rgba(15, 23, 42, 0.45);
		}

		.task-table-admin .tt-export-modal__dialog {
			position: relative;
			background: var(--tt-surface);
			border-radius: var(--tt-radius);
			border: 1px solid var(--tt-border);
			box-shadow: 0 18px 45px rgba(15, 23, 42, 0.22);
			max-width: 560px;
			width: 100%;
			padding: 28px 32px;
			z-index: 1;
			display: flex;
			flex-direction: column;
			gap: 18px;
		}

		.task-table-admin .tt-export-modal__close {
			position: absolute;
			top: 10px;
			right: 10px;
			border: 0;
			background: none;
			color: var(--tt-text-muted);
			font-size: 24px;
			line-height: 1;
			cursor: pointer;
		}

		.task-table-admin .tt-export-modal__close:hover,
		.task-table-admin .tt-export-modal__close:focus {
			color: var(--tt-text);
		}

		.task-table-admin .tt-export-modal__title {
			margin: 0;
			font-size: 22px;
		}

		.task-table-admin .tt-export-modal__intro {
			margin: 0;
			color: var(--tt-text-muted);
			font-size: 14px;
		}

		.task-table-admin .tt-export-fieldset {
			border: 1px solid var(--tt-border);
			border-radius: 8px;
			padding: 16px;
			margin: 0;
			display: flex;
			flex-direction: column;
			gap: 10px;
		}

		.task-table-admin .tt-export-fieldset legend {
			font-size: 12px;
			font-weight: 600;
			text-transform: uppercase;
			letter-spacing: 0.06em;
			color: var(--tt-text-muted);
			padding: 0 6px;
		}

		.task-table-admin .tt-export-option {
			display: flex;
			align-items: center;
			gap: 8px;
			font-size: 13px;
			font-weight: 500;
		}

		.task-table-admin .tt-export-field {
			display: flex;
			flex-direction: column;
			gap: 6px;
		}

		.task-table-admin .tt-export-field > label,
		.task-table-admin .tt-export-field legend {
			font-size: 12px;
			font-weight: 600;
			text-transform: uppercase;
			letter-spacing: 0.06em;
			color: var(--tt-text-muted);
		}

		.task-table-admin .tt-export-checkbox-grid {
			display: grid;
			grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
			gap: 8px;
		}

		.task-table-admin .tt-export-checkbox-grid label {
			display: flex;
			align-items: center;
			gap: 8px;
			font-size: 13px;
		}

		.task-table-admin .tt-export-help {
			font-size: 12px;
			color: var(--tt-text-muted);
			margin: 0;
		}

		.task-table-admin .tt-export-actions {
			display: flex;
			justify-content: flex-end;
			gap: 10px;
			margin-top: 16px;
		}

		.task-table-admin .tt-export-actions .button.button-primary {
			min-width: 120px;
		}

		body.tt-export-modal-open {
			overflow: hidden;
		}

		@media (max-width: 782px) {
			.task-table-admin .tt-page-actions,
			.task-table-admin .tt-toolbar {
				flex-direction: column;
				align-items: stretch;
			}

			.task-table-admin .tt-filters {
				flex-direction: column;
				align-items: stretch;
			}

			.task-table-admin .tt-filter-group,
			.task-table-admin .tt-filter-actions {
				width: 100%;
			}

			.task-table-admin table.tt-table {
				display: block;
				overflow-x: auto;
			}

			.task-table-admin .tt-export-modal__dialog {
				padding: 24px 20px;
				max-width: 100%;
			}
		}
	</style>
	<?php
}

/**
 * Admin dashboard page
 */
function task_table_admin_dashboard_page() {
	global $wpdb;
	$events_table  = $wpdb->prefix . 'task_table_events';
	$signups_table = $wpdb->prefix . 'task_table_signups';
	task_table_print_admin_styles();

	// Handle archive/unarchive (nonce protected via URL)
	if ( isset( $_GET['action'], $_GET['event_id'], $_GET['_wpnonce'] ) ) {
		$allowed  = array( 'archive', 'unarchive' );
		$action   = sanitize_text_field( wp_unslash( $_GET['action'] ) );
		$event_id = (int) $_GET['event_id'];

		if (
			in_array( $action, $allowed, true ) &&
			wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'task_table_event_' . $action . '_' . $event_id )
		) {
			$archived_val = $action === 'archive' ? 1 : 0;
			$wpdb->update(
				$events_table,
				array(
					'archived'   => $archived_val,
					'updated_at' => current_time( 'mysql' ),
				),
				array( 'id' => $event_id )
			);
			echo '<div class="notice notice-success is-dismissible"><p>' . ( $archived_val ? esc_html__( 'Event archived.', 'task-table' ) : esc_html__( 'Event restored.', 'task-table' ) ) . '</p></div>';
		}
	}

	$show_archived     = isset( $_GET['archived'] ) && $_GET['archived'] === '1';
	$categories        = task_table_get_categories();
	$selected_category = isset( $_GET['tt_category'] ) ? (int) $_GET['tt_category'] : 0;
	$search_term       = isset( $_GET['tt_search'] ) ? sanitize_text_field( wp_unslash( $_GET['tt_search'] ) ) : '';

	$conditions = array( 'e.archived = %d' );
	$params     = array( $show_archived ? 1 : 0 );

	if ( $selected_category ) {
		$conditions[] = 'e.category_id = %d';
		$params[]     = $selected_category;
	}

	if ( $search_term !== '' ) {
		$like         = '%' . $wpdb->esc_like( $search_term ) . '%';
		$conditions[] = '(e.was_angefragt LIKE %s OR e.name_hochschule LIKE %s OR e.wer_macht LIKE %s)';
		$params[]     = $like;
		$params[]     = $like;
		$params[]     = $like;
	}

	$where_clause = 'WHERE ' . implode( ' AND ', $conditions );

	$categories_table = $wpdb->prefix . 'task_table_categories';
	$sql              = "SELECT e.*, c.name AS category_name
            FROM $events_table e
            LEFT JOIN $categories_table c ON c.id = e.category_id
            $where_clause
            ORDER BY e.created_at DESC";

	$events = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- WHERE is built from constant fragments with placeholders; values are bound via prepare().

	// Aggregate stats
	$pending_signups = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $signups_table WHERE status = 'pending'" );
	$active_total    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $events_table WHERE archived = 0" );
	$archived_total  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $events_table WHERE archived = 1" );

	$latest_event_date = $wpdb->get_var( "SELECT created_at FROM $events_table ORDER BY created_at DESC LIMIT 1" );
	$latest_event_meta = '';
	if ( $latest_event_date ) {
		$latest_ts = strtotime( $latest_event_date );
		if ( $latest_ts ) {
			/* translators: %s: human readable time difference */
			$latest_event_meta = sprintf( /* translators: %s: human-readable time span */ __( 'Last event created %s ago', 'task-table' ), human_time_diff( $latest_ts, strtotime( current_time( 'mysql' ) ) ) );
		}
	}

	$base_args = array( 'page' => 'task-table' );
	if ( $show_archived ) {
		$base_args['archived'] = '1';
	}
	$base_admin_url  = add_query_arg( $base_args, admin_url( 'admin.php' ) );
	$filters_applied = $search_term !== '' || $selected_category;

	$render_event_cell = static function ( $value ) {
		$content = is_scalar( $value ) ? (string) $value : '';

		if ( $content === '' ) {
			return '';
		}

		if ( function_exists( 'task_table_sanitize_event_text' ) ) {
			$content = task_table_sanitize_event_text( $content );
			$content = make_clickable( $content );

			return task_table_sanitize_event_text( $content );
		}

		return wp_kses_post( make_clickable( $content ) );
	};

	$export_action_url          = admin_url( 'admin-post.php' );
	$export_selected_categories = $selected_category ? array( $selected_category ) : array();
	$export_default_archived    = $show_archived ? '1' : '0';
	$export_fallback_args       = array( 'action' => 'task_table_export_events' );
	if ( $show_archived ) {
		$export_fallback_args['archived'] = '1';
	}
	if ( $selected_category ) {
		$export_fallback_args['tt_category'] = $selected_category;
	}
	if ( $search_term !== '' ) {
		$export_fallback_args['tt_search'] = $search_term;
	}
	$export_fallback_url = wp_nonce_url( add_query_arg( $export_fallback_args, $export_action_url ), 'task_table_export_events' );

	?>
	<div class="wrap task-table-admin">
		<div class="tt-page-header">
			<div class="tt-page-title">
				<h1><?php esc_html_e( 'Task Table Dashboard', 'task-table' ); ?></h1>
				<p>
					<?php
					echo $show_archived
						? esc_html__( 'Browse your archive of completed and past events. Refine the list with filters or export a snapshot for your records.', 'task-table' )
						: esc_html__( 'Stay on top of upcoming and in-progress events. Filter, export, and act on signups without leaving this screen.', 'task-table' );
					?>
				</p>
			</div>
			<div class="tt-page-actions">
				<a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=task-table-signups' ) ); ?>">
					<?php esc_html_e( 'Manage Signups', 'task-table' ); ?>
				</a>
				<button type="button" class="button button-primary" id="tt-export-open">
					<?php esc_html_e( 'Download Excel', 'task-table' ); ?>
				</button>
				<noscript>
					<a class="button button-primary" href="<?php echo esc_url( $export_fallback_url ); ?>" target="_blank" rel="noopener">
						<?php esc_html_e( 'Download Excel', 'task-table' ); ?>
					</a>
				</noscript>
			</div>
		</div>

		<div class="tt-export-modal" id="tt-export-modal" aria-hidden="true">
			<div class="tt-export-modal__overlay" data-tt-export-close></div>
			<div class="tt-export-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="tt-export-modal-title">
				<button type="button" class="tt-export-modal__close" data-tt-export-close aria-label="<?php esc_attr_e( 'Close export dialog', 'task-table' ); ?>">&times;</button>
				<h2 class="tt-export-modal__title" id="tt-export-modal-title"><?php esc_html_e( 'Customize Excel export', 'task-table' ); ?></h2>
				<p class="tt-export-modal__intro"><?php esc_html_e( 'Choose exactly which events to include before downloading.', 'task-table' ); ?></p>
				<form method="get" action="<?php echo esc_url( $export_action_url ); ?>" target="_blank" class="tt-export-form">
					<?php wp_nonce_field( 'task_table_export_events' ); ?>
					<input type="hidden" name="action" value="task_table_export_events">

					<fieldset class="tt-export-fieldset">
						<legend><?php esc_html_e( 'Event status', 'task-table' ); ?></legend>
						<label class="tt-export-option">
							<input type="radio" name="archived" value="all">
							<span><?php esc_html_e( 'All events', 'task-table' ); ?></span>
						</label>
						<label class="tt-export-option">
							<input type="radio" name="archived" value="0" <?php checked( $export_default_archived, '0' ); ?>>
							<span><?php esc_html_e( 'Active events only', 'task-table' ); ?></span>
						</label>
						<label class="tt-export-option">
							<input type="radio" name="archived" value="1" <?php checked( $export_default_archived, '1' ); ?>>
							<span><?php esc_html_e( 'Archived events only', 'task-table' ); ?></span>
						</label>
					</fieldset>

					<div class="tt-export-field">
						<label for="tt-export-categories"><?php esc_html_e( 'Categories (optional)', 'task-table' ); ?></label>
						<p class="tt-export-help"><?php esc_html_e( 'Leave blank to include every category.', 'task-table' ); ?></p>
						<?php if ( ! empty( $categories ) ) : ?>
							<div class="tt-export-checkbox-grid" id="tt-export-categories">
								<?php foreach ( $categories as $category ) : ?>
									<label>
										<input type="checkbox" name="tt_categories[]" value="<?php echo esc_attr( $category['id'] ); ?>" <?php checked( in_array( (int) $category['id'], $export_selected_categories, true ) ); ?>>
										<?php echo esc_html( $category['name'] ); ?>
									</label>
								<?php endforeach; ?>
							</div>
						<?php else : ?>
							<p class="tt-export-help"><?php esc_html_e( 'No categories available yet.', 'task-table' ); ?></p>
						<?php endif; ?>
					</div>

					<div class="tt-export-field">
						<label for="tt-export-search"><?php esc_html_e( 'Search filter (optional)', 'task-table' ); ?></label>
						<input type="text" id="tt-export-search" name="tt_search" value="<?php echo esc_attr( $search_term ); ?>" placeholder="<?php esc_attr_e( 'Filter by keyword', 'task-table' ); ?>">
						<p class="tt-export-help"><?php esc_html_e( 'Matches event title, university, or assignee.', 'task-table' ); ?></p>
					</div>

					<div class="tt-export-field">
						<label for="tt-export-format"><?php esc_html_e( 'File format', 'task-table' ); ?></label>
						<select id="tt-export-format" name="format">
							<option value="xlsx"><?php esc_html_e( 'Excel (.xlsx)', 'task-table' ); ?></option>
							<option value="xml"><?php esc_html_e( 'Legacy Excel (.xls)', 'task-table' ); ?></option>
						</select>
					</div>

					<div class="tt-export-actions">
						<button type="button" class="button button-link" data-tt-export-close><?php esc_html_e( 'Cancel', 'task-table' ); ?></button>
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Download', 'task-table' ); ?></button>
					</div>
				</form>
			</div>
		</div>

		<div class="tt-card-grid">
			<div class="tt-card">
				<h3><?php echo $show_archived ? esc_html__( 'Archived in view', 'task-table' ) : esc_html__( 'Active in view', 'task-table' ); ?></h3>
				<div class="tt-card-value">
					<?php echo count( $events ); ?>
					<?php if ( $filters_applied ) : ?>
						<small><?php esc_html_e( 'matching filters', 'task-table' ); ?></small>
					<?php else : ?>
						<small><?php esc_html_e( 'total', 'task-table' ); ?></small>
					<?php endif; ?>
				</div>
				<?php if ( $latest_event_meta ) : ?>
					<div class="tt-card-meta"><?php echo esc_html( $latest_event_meta ); ?></div>
				<?php endif; ?>
			</div>
			<div class="tt-card">
				<h3><?php esc_html_e( 'Active Events', 'task-table' ); ?></h3>
				<div class="tt-card-value"><?php echo esc_html( $active_total ); ?></div>
				<div class="tt-card-meta"><?php esc_html_e( 'Currently visible to users', 'task-table' ); ?></div>
			</div>
			<div class="tt-card">
				<h3><?php esc_html_e( 'Archived Events', 'task-table' ); ?></h3>
				<div class="tt-card-value"><?php echo esc_html( $archived_total ); ?></div>
				<div class="tt-card-meta"><?php esc_html_e( 'Stored for historical reference', 'task-table' ); ?></div>
			</div>
			<div class="tt-card">
				<h3><?php esc_html_e( 'Pending Signups', 'task-table' ); ?></h3>
				<div class="tt-card-value">
					<?php echo esc_html( $pending_signups ); ?>
					<?php if ( $pending_signups > 0 ) : ?>
						<small><?php esc_html_e( 'needs review', 'task-table' ); ?></small>
					<?php else : ?>
						<small><?php esc_html_e( 'all caught up', 'task-table' ); ?></small>
					<?php endif; ?>
				</div>
				<div class="tt-card-meta">
					<?php if ( $pending_signups > 0 ) : ?>
						<a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=task-table-signups&status=pending' ) ); ?>">
							<?php esc_html_e( 'Review queue', 'task-table' ); ?>
						</a>
					<?php else : ?>
						<?php esc_html_e( 'Great job! Nothing waiting for approval.', 'task-table' ); ?>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<div class="tt-toolbar">
			<div class="tt-pill-toggle" role="tablist" aria-label="<?php esc_attr_e( 'Toggle between active and archived events', 'task-table' ); ?>">
				<a class="<?php echo $show_archived ? '' : 'is-active'; ?>" href="<?php echo esc_url( remove_query_arg( array( 'archived', 'tt_category', 'tt_search' ) ) ); ?>">
					<?php esc_html_e( 'Active Events', 'task-table' ); ?>
					<span class="tt-pill tt-pill-inline is-active"><?php echo esc_html( $active_total ); ?></span>
				</a>
				<a class="<?php echo $show_archived ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'archived' => '1' ), remove_query_arg( array( 'tt_category', 'tt_search' ) ) ) ); ?>">
					<?php esc_html_e( 'Archived Events', 'task-table' ); ?>
					<span class="tt-pill tt-pill-inline is-archived"><?php echo esc_html( $archived_total ); ?></span>
				</a>
			</div>
		</div>

		<form method="get" class="tt-filters" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
			<input type="hidden" name="page" value="task-table">
			<?php if ( $show_archived ) : ?>
				<input type="hidden" name="archived" value="1">
			<?php endif; ?>
			<div class="tt-filter-group">
				<label for="tt-dashboard-search"><?php esc_html_e( 'Search', 'task-table' ); ?></label>
				<input type="search" id="tt-dashboard-search" name="tt_search" value="<?php echo esc_attr( $search_term ); ?>" placeholder="<?php esc_attr_e( 'Search by event, university, or assignee', 'task-table' ); ?>">
			</div>
			<div class="tt-filter-group">
				<label for="tt-dashboard-category"><?php esc_html_e( 'Category', 'task-table' ); ?></label>
				<select id="tt-dashboard-category" name="tt_category">
					<option value="0"><?php esc_html_e( 'All categories', 'task-table' ); ?></option>
					<?php foreach ( $categories as $category ) : ?>
						<option value="<?php echo esc_attr( $category['id'] ); ?>" <?php selected( $selected_category === (int) $category['id'] ); ?>>
							<?php echo esc_html( $category['name'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="tt-filter-actions">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Apply Filters', 'task-table' ); ?></button>
				<?php if ( $filters_applied ) : ?>
					<a class="button button-link" href="<?php echo esc_url( $base_admin_url ); ?>"><?php esc_html_e( 'Reset', 'task-table' ); ?></a>
				<?php endif; ?>
			</div>
		</form>

		<?php if ( ! empty( $events ) ) : ?>
			<table class="wp-list-table widefat fixed striped tt-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Event', 'task-table' ); ?></th>
						<th scope="col"><?php esc_html_e( 'University', 'task-table' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Schedule', 'task-table' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Category', 'task-table' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Assignee', 'task-table' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Created', 'task-table' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Actions', 'task-table' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $events as $event ) : ?>
						<?php
						$created_timestamp = strtotime( $event['created_at'] );
						?>
						<tr>
							<td>
								<?php echo $event['was_angefragt'] !== '' ? wp_kses( $render_event_cell( $event['was_angefragt'] ), task_table_get_event_text_allowed_tags() ) : '&mdash;'; ?>
								<div class="description"><?php printf( /* translators: %s: event ID */ esc_html__( 'Event ID %s', 'task-table' ), esc_html( $event['id'] ) ); ?></div>
							</td>
							<td><?php echo $event['name_hochschule'] !== '' ? wp_kses( $render_event_cell( $event['name_hochschule'] ), task_table_get_event_text_allowed_tags() ) : '&mdash;'; ?></td>
							<td><?php echo $event['zeitpunkt'] !== '' ? wp_kses( $render_event_cell( $event['zeitpunkt'] ), task_table_get_event_text_allowed_tags() ) : '&mdash;'; ?></td>
							<td>
								<?php if ( ! empty( $event['category_name'] ) ) : ?>
									<span class="tt-pill is-active"><?php echo esc_html( $event['category_name'] ); ?></span>
								<?php else : ?>
									&mdash;
								<?php endif; ?>
							</td>
							<td>
								<?php if ( $event['wer_macht'] !== '' ) : ?>
									<span class="tt-pill is-approved"><?php echo wp_kses( $render_event_cell( $event['wer_macht'] ), task_table_get_event_text_allowed_tags() ); ?></span>
								<?php else : ?>
									<span class="tt-pill is-pending"><?php esc_html_e( 'Unassigned', 'task-table' ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( $created_timestamp ) : ?>
									<div><?php echo esc_html( date_i18n( get_option( 'date_format' ), $created_timestamp ) ); ?></div>
									<div class="description">
										<?php printf( /* translators: %s: human-readable time span, e.g. "3 days" */ esc_html__( '%s ago', 'task-table' ), esc_html( human_time_diff( $created_timestamp, strtotime( current_time( 'mysql' ) ) ) ) ); ?>
									</div>
								<?php else : ?>
									&mdash;
								<?php endif; ?>
							</td>
							<td class="tt-actions">
								<?php if ( ! $show_archived ) : ?>
									<a class="button button-secondary" href="
									<?php
									echo esc_url(
										wp_nonce_url(
											add_query_arg(
												array(
													'action'   => 'archive',
													'event_id' => $event['id'],
												)
											),
											'task_table_event_archive_' . $event['id']
										)
									);
									?>
																				" onclick="return confirm('<?php echo esc_js( __( 'Archive this event?', 'task-table' ) ); ?>');">
										<span class="dashicons dashicons-archive"></span>
										<?php esc_html_e( 'Archive', 'task-table' ); ?>
									</a>
								<?php else : ?>
									<a class="button button-secondary" href="
									<?php
									echo esc_url(
										wp_nonce_url(
											add_query_arg(
												array(
													'action'   => 'unarchive',
													'event_id' => $event['id'],
												)
											),
											'task_table_event_unarchive_' . $event['id']
										)
									);
									?>
																				" onclick="return confirm('<?php echo esc_js( __( 'Restore this event?', 'task-table' ) ); ?>');">
										<span class="dashicons dashicons-image-rotate"></span>
										<?php esc_html_e( 'Restore', 'task-table' ); ?>
									</a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<div class="tt-empty-state">
				<h3><?php echo $show_archived ? esc_html__( 'No archived events match your filters', 'task-table' ) : esc_html__( 'No events yet', 'task-table' ); ?></h3>
				<p>
					<?php if ( $filters_applied ) : ?>
						<?php esc_html_e( 'Try broadening your filters or reset them to see the full list again.', 'task-table' ); ?>
					<?php else : ?>
						<?php esc_html_e( 'New submissions will appear here automatically. Use the signup form to collect your first requests.', 'task-table' ); ?>
					<?php endif; ?>
				</p>
				<div class="tt-actions">
					<a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=task-table-signups' ) ); ?>"><?php esc_html_e( 'Go to Signups', 'task-table' ); ?></a>
					<?php if ( $filters_applied ) : ?>
						<a class="button" href="<?php echo esc_url( $base_admin_url ); ?>"><?php esc_html_e( 'Reset Filters', 'task-table' ); ?></a>
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>
	</div>
	<script>
		(function() {
			const modal = document.getElementById('tt-export-modal');
			const openButton = document.getElementById('tt-export-open');
			if (!modal || !openButton) {
				return;
			}

			const body = document.body;
			const overlay = modal.querySelector('.tt-export-modal__overlay');
			const closeElements = modal.querySelectorAll('[data-tt-export-close]');
			const form = modal.querySelector('form');
			const focusableSelector = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
			let lastFocusedElement = null;

			function getFocusableElements() {
				return Array.from(modal.querySelectorAll(focusableSelector)).filter(function(el) {
					return el.offsetParent !== null && !el.hasAttribute('disabled');
				});
			}

			function handleKeydown(event) {
				if (!modal.classList.contains('is-open')) {
					return;
				}

				if (event.key === 'Escape') {
					event.preventDefault();
					closeModal();
					return;
				}

				if (event.key === 'Tab') {
					const focusableElements = getFocusableElements();
					if (!focusableElements.length) {
						event.preventDefault();
						return;
					}
					const first = focusableElements[0];
					const last = focusableElements[focusableElements.length - 1];
					if (event.shiftKey) {
						if (document.activeElement === first || !modal.contains(document.activeElement)) {
							event.preventDefault();
							last.focus();
						}
					} else if (document.activeElement === last) {
						event.preventDefault();
						first.focus();
					}
				}
			}

			function openModal() {
				lastFocusedElement = document.activeElement;
				modal.classList.add('is-open');
				modal.setAttribute('aria-hidden', 'false');
				body.classList.add('tt-export-modal-open');
				const focusableElements = getFocusableElements();
				if (focusableElements.length) {
					focusableElements[0].focus();
				}
				document.addEventListener('keydown', handleKeydown);
			}

			function closeModal() {
				modal.classList.remove('is-open');
				modal.setAttribute('aria-hidden', 'true');
				body.classList.remove('tt-export-modal-open');
				document.removeEventListener('keydown', handleKeydown);
				if (lastFocusedElement && typeof lastFocusedElement.focus === 'function') {
					lastFocusedElement.focus();
				} else {
					openButton.focus();
				}
			}

			openButton.addEventListener('click', function() {
				openModal();
			});

			if (overlay) {
				overlay.addEventListener('click', closeModal);
			}

			closeElements.forEach(function(element) {
				element.addEventListener('click', function() {
					closeModal();
				});
			});

			if (form) {
				form.addEventListener('submit', function() {
					setTimeout(closeModal, 150);
				});
			}
		})();
	</script>
	<?php
}

/**
 * Admin signups page
 */
function task_table_admin_signups_page() {
	global $wpdb;
	$signups_table = $wpdb->prefix . 'task_table_signups';
	$events_table  = $wpdb->prefix . 'task_table_events';

	task_table_print_admin_styles();

	// Handle signup status changes
	if ( isset( $_GET['action'] ) && isset( $_GET['signup_id'] ) && isset( $_GET['_wpnonce'] ) ) {
		$action    = sanitize_text_field( wp_unslash( $_GET['action'] ) );
		$signup_id = intval( $_GET['signup_id'] );
		$nonce     = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );

		if ( wp_verify_nonce( $nonce, 'task_table_signup_' . $action . '_' . $signup_id ) ) {
			$signup = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM $signups_table WHERE id = %d",
					$signup_id
				)
			);

			if ( $signup ) {
				if ( $action === 'approve' ) {
					$wpdb->update(
						$signups_table,
						array(
							'status'     => 'approved',
							'updated_at' => current_time( 'mysql' ),
						),
						array( 'id' => $signup_id )
					);

					task_table_send_approval_notification( $signup );

					// Update the event's wer_macht field
					$wpdb->update(
						$events_table,
						array( 'wer_macht' => $signup->user_name ),
						array( 'id' => $signup->event_id )
					);

					echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Anmeldung angenommen.', 'task-table' ) . '</p></div>';
				} elseif ( $action === 'deny' ) {
					$wpdb->update(
						$signups_table,
						array(
							'status'     => 'denied',
							'updated_at' => current_time( 'mysql' ),
						),
						array( 'id' => $signup_id )
					);

					task_table_send_denial_notification( $signup );

					echo '<div class="notice notice-info is-dismissible"><p>' . esc_html__( 'Anmeldung abgelehnt.', 'task-table' ) . '</p></div>';
				} elseif ( $action === 'delete' ) {
					$wpdb->delete( $signups_table, array( 'id' => $signup_id ) );
					echo '<div class="notice notice-info is-dismissible"><p>' . esc_html__( 'Signup deleted.', 'task-table' ) . '</p></div>';
				}
			}
		}
	}

	$status_filter = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
	$search_term   = isset( $_GET['tt_search'] ) ? sanitize_text_field( wp_unslash( $_GET['tt_search'] ) ) : '';

	// Build query conditions
	$conditions = array();
	$params     = array();

	if ( $status_filter ) {
		$conditions[] = 's.status = %s';
		$params[]     = $status_filter;
	}

	if ( $search_term !== '' ) {
		$like         = '%' . $wpdb->esc_like( $search_term ) . '%';
		$conditions[] = '(s.user_name LIKE %s OR s.user_email LIKE %s OR e.was_angefragt LIKE %s OR e.name_hochschule LIKE %s)';
		$params[]     = $like;
		$params[]     = $like;
		$params[]     = $like;
		$params[]     = $like;
	}

	$where_clause = '';
	if ( ! empty( $conditions ) ) {
		$where_clause = 'WHERE ' . implode( ' AND ', $conditions );
	}

	$categories_table = $wpdb->prefix . 'task_table_categories';
	$sql              = "SELECT s.*, e.was_angefragt, e.name_hochschule, e.zeitpunkt, c.name AS category_name
            FROM $signups_table s
            LEFT JOIN $events_table e ON s.event_id = e.id
            LEFT JOIN $categories_table c ON c.id = e.category_id
            $where_clause
            ORDER BY s.created_at DESC";

	$signups = ! empty( $params ) ? $wpdb->get_results( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- WHERE is built from constant fragments with placeholders; values are bound via prepare().

	$status_counts_raw = $wpdb->get_results( "SELECT status, COUNT(*) AS total FROM $signups_table GROUP BY status", OBJECT_K );
	$status_totals     = array(
		'pending'  => isset( $status_counts_raw['pending'] ) ? (int) $status_counts_raw['pending']->total : 0,
		'approved' => isset( $status_counts_raw['approved'] ) ? (int) $status_counts_raw['approved']->total : 0,
		'denied'   => isset( $status_counts_raw['denied'] ) ? (int) $status_counts_raw['denied']->total : 0,
	);
	$total_signups     = array_sum( $status_totals );
	$filters_applied   = ( $status_filter !== '' && $status_filter !== 'all' ) || $search_term !== '';

	$status_labels = array(
		'pending'  => __( 'Pending', 'task-table' ),
		'approved' => __( 'Approved', 'task-table' ),
		'denied'   => __( 'Denied', 'task-table' ),
	);

	$base_args = array( 'page' => 'task-table-signups' );
	if ( $search_term !== '' ) {
		$base_args['tt_search'] = $search_term;
	}
	$base_url  = add_query_arg( $base_args, admin_url( 'admin.php' ) );
	$reset_url = admin_url( 'admin.php?page=task-table-signups' );

	$render_event_cell = static function ( $value ) {
		$content = is_scalar( $value ) ? (string) $value : '';

		if ( $content === '' ) {
			return '';
		}

		if ( function_exists( 'task_table_sanitize_event_text' ) ) {
			$content = task_table_sanitize_event_text( $content );
			$content = make_clickable( $content );

			return task_table_sanitize_event_text( $content );
		}

		return wp_kses_post( make_clickable( $content ) );
	};

	?>
	<div class="wrap task-table-admin">
		<div class="tt-page-header">
			<div class="tt-page-title">
				<h1><?php esc_html_e( 'Manage Event Signups', 'task-table' ); ?></h1>
				<p><?php esc_html_e( 'Review incoming signups, capture important details, and keep conversations moving with quick approve or deny actions.', 'task-table' ); ?></p>
			</div>
		</div>

		<div class="tt-card-grid">
			<div class="tt-card">
				<h3><?php esc_html_e( 'Total Signups', 'task-table' ); ?></h3>
				<div class="tt-card-value"><?php echo esc_html( $total_signups ); ?></div>
				<div class="tt-card-meta"><?php esc_html_e( 'Across all statuses', 'task-table' ); ?></div>
			</div>
			<div class="tt-card">
				<h3><?php esc_html_e( 'Pending', 'task-table' ); ?></h3>
				<div class="tt-card-value">
					<?php echo esc_html( $status_totals['pending'] ); ?>
					<?php if ( $status_totals['pending'] > 0 ) : ?>
						<small><?php esc_html_e( 'awaiting review', 'task-table' ); ?></small>
					<?php endif; ?>
				</div>
				<div class="tt-card-meta">
					<?php if ( $status_totals['pending'] > 0 ) : ?>
						<a class="button button-secondary" href="<?php echo esc_url( add_query_arg( array( 'status' => 'pending' ), $reset_url ) ); ?>"><?php esc_html_e( 'Jump to queue', 'task-table' ); ?></a>
					<?php else : ?>
						<?php esc_html_e( 'All requests are processed.', 'task-table' ); ?>
					<?php endif; ?>
				</div>
			</div>
			<div class="tt-card">
				<h3><?php esc_html_e( 'Approved', 'task-table' ); ?></h3>
				<div class="tt-card-value"><?php echo esc_html( $status_totals['approved'] ); ?></div>
				<div class="tt-card-meta"><?php esc_html_e( 'Assignments already confirmed', 'task-table' ); ?></div>
			</div>
			<div class="tt-card">
				<h3><?php esc_html_e( 'Denied', 'task-table' ); ?></h3>
				<div class="tt-card-value"><?php echo esc_html( $status_totals['denied'] ); ?></div>
				<div class="tt-card-meta"><?php esc_html_e( 'Requests you declined', 'task-table' ); ?></div>
			</div>
		</div>

		<div class="tt-toolbar">
			<div class="tt-status-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Filter signups by status', 'task-table' ); ?>">
				<?php
				$tab_filters = array( '' => __( 'All', 'task-table' ) ) + $status_labels;
				foreach ( $tab_filters as $status_key => $label ) :
					$tab_args = $base_args;
					if ( $status_key !== '' ) {
						$tab_args['status'] = $status_key;
					} else {
						unset( $tab_args['status'] );
					}
					$tab_url    = add_query_arg( $tab_args, admin_url( 'admin.php' ) );
					$is_current = ( $status_key === '' && $status_filter === '' ) || ( $status_key !== '' && $status_filter === $status_key );
					$count      = $status_key === '' ? $total_signups : $status_totals[ $status_key ];
					?>
					<a class="<?php echo $is_current ? 'is-current' : ''; ?>" href="<?php echo esc_url( $tab_url ); ?>">
						<?php echo esc_html( $label ); ?>
						<span class="tt-pill <?php echo $status_key ? 'is-' . esc_attr( $status_key ) : 'is-active'; ?>">
							<?php echo esc_html( $count ); ?>
						</span>
					</a>
				<?php endforeach; ?>
			</div>
		</div>

		<form method="get" class="tt-filters" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
			<input type="hidden" name="page" value="task-table-signups">
			<?php if ( $status_filter ) : ?>
				<input type="hidden" name="status" value="<?php echo esc_attr( $status_filter ); ?>">
			<?php endif; ?>
			<div class="tt-filter-group">
				<label for="tt-signup-search"><?php esc_html_e( 'Search', 'task-table' ); ?></label>
				<input type="search" id="tt-signup-search" name="tt_search" value="<?php echo esc_attr( $search_term ); ?>" placeholder="<?php esc_attr_e( 'Search by name, email, or event', 'task-table' ); ?>">
			</div>
			<div class="tt-filter-actions">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Apply Search', 'task-table' ); ?></button>
				<?php if ( $filters_applied ) : ?>
					<a class="button button-link" href="<?php echo esc_url( $reset_url ); ?>"><?php esc_html_e( 'Reset Filters', 'task-table' ); ?></a>
				<?php endif; ?>
			</div>
		</form>

		<?php if ( ! empty( $signups ) ) : ?>
			<div class="tt-table-scroll"><table class="wp-list-table widefat striped tt-table tt-signups-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Signup', 'task-table' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Event', 'task-table' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Details', 'task-table' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'task-table' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Submitted', 'task-table' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Actions', 'task-table' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $signups as $signup ) : ?>
						<?php
						$created_timestamp = strtotime( $signup->created_at );
						$status_key        = $signup->status;
						$status_label      = isset( $status_labels[ $status_key ] ) ? $status_labels[ $status_key ] : ucfirst( $status_key );
						$status_class      = 'is-' . sanitize_html_class( $status_key );

						$notes_markup = '&mdash;';
						if ( ! empty( $signup->free_text ) ) {
							$raw       = (string) $signup->free_text;
							$raw       = str_replace( array( "\r\n", "\r" ), "\n", $raw );
							$raw       = trim( $raw, " \t\n" );
							$lines     = explode( "\n", $raw );
							$processed = array();
							foreach ( $lines as $line ) {
								$line        = ltrim( $line, " \t" );
								$line        = preg_replace( '/[ \t]{2,}/', ' ', $line );
								$processed[] = rtrim( $line );
							}
							$raw        = implode( "\n", $processed );
							$raw        = preg_replace( "/\n{3,}/", "\n\n", $raw );
							$clean_text = trim( $raw );
							if ( $clean_text !== '' ) {
								$notes_markup = nl2br( esc_html( $clean_text ) );
							}
						}
						?>
						<tr>
							<td>
								<strong><?php echo esc_html( $signup->user_name ); ?></strong>
								<div class="description">
									<a href="mailto:<?php echo esc_attr( $signup->user_email ); ?>"><?php echo esc_html( $signup->user_email ); ?></a>
								</div>
							</td>
							<td>
								<?php
								$event_title_markup = $render_event_cell( $signup->was_angefragt );
								echo '' !== $event_title_markup ? wp_kses( $event_title_markup, task_table_get_event_text_allowed_tags() ) : '&mdash;';
								?>
							</td>
							<td class="signup-free-text">
								<?php echo wp_kses( $notes_markup, array( 'br' => array() ) ); ?>
							</td>
							<td>
								<span class="tt-pill <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status_label ); ?></span>
							</td>
							<td>
								<?php if ( $created_timestamp ) : ?>
									<div><?php echo esc_html( date_i18n( get_option( 'date_format' ), $created_timestamp ) ); ?></div>
									<div class="description"><?php printf( /* translators: %s: human-readable time span, e.g. "3 days" */ esc_html__( '%s ago', 'task-table' ), esc_html( human_time_diff( $created_timestamp, strtotime( current_time( 'mysql' ) ) ) ) ); ?></div>
								<?php else : ?>
									&mdash;
								<?php endif; ?>
							</td>
							<td class="tt-actions">
								<?php if ( $signup->status === 'pending' ) : ?>
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=task-table-signups&action=approve&signup_id=' . $signup->id ), 'task_table_signup_approve_' . $signup->id ) ); ?>" class="button button-secondary">
										<span class="dashicons dashicons-yes"></span>
										<?php esc_html_e( 'Approve', 'task-table' ); ?>
									</a>
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=task-table-signups&action=deny&signup_id=' . $signup->id ), 'task_table_signup_deny_' . $signup->id ) ); ?>" class="button button-secondary">
										<span class="dashicons dashicons-dismiss"></span>
										<?php esc_html_e( 'Deny', 'task-table' ); ?>
									</a>
								<?php endif; ?>
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=task-table-signups&action=delete&signup_id=' . $signup->id ), 'task_table_signup_delete_' . $signup->id ) ); ?>" class="button is-destructive" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete this signup?', 'task-table' ) ); ?>');">
									<span class="dashicons dashicons-trash"></span>
									<?php esc_html_e( 'Delete', 'task-table' ); ?>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table></div>
		<?php else : ?>
			<div class="tt-empty-state">
				<h3><?php esc_html_e( 'No signups match your filters', 'task-table' ); ?></h3>
				<p>
					<?php if ( $filters_applied ) : ?>
						<?php esc_html_e( 'Try adjusting your search or switch to the “All” tab to see every request.', 'task-table' ); ?>
					<?php else : ?>
						<?php esc_html_e( 'Once users submit the form, their requests will appear here instantly.', 'task-table' ); ?>
					<?php endif; ?>
				</p>
				<div class="tt-actions">
					<a class="button" href="<?php echo esc_url( $reset_url ); ?>"><?php esc_html_e( 'Reset Filters', 'task-table' ); ?></a>
				</div>
			</div>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Admin subscribers page
 */
function task_table_admin_subscribers_page() {
	global $wpdb;
	$subscribers_table           = $wpdb->prefix . 'task_table_subscribers';
	$subscriber_categories_table = $wpdb->prefix . 'task_table_subscriber_categories';
	$categories                  = task_table_get_categories();
	$default_category_id         = task_table_get_default_category_id();
	$category_update_notice      = '';
	task_table_print_admin_styles();

	if ( isset( $_POST['task_table_update_subscriber_categories'] ) && isset( $_POST['_wpnonce'] ) ) {
		if ( check_admin_referer( 'task_table_update_subscriber_categories' ) ) {
			$subscriber_id       = isset( $_POST['subscriber_id'] ) ? (int) $_POST['subscriber_id'] : 0;
			$selected_categories = isset( $_POST['subscriber_categories'] ) ? (array) wp_unslash( $_POST['subscriber_categories'] ) : array();

			if ( $subscriber_id > 0 ) {
				task_table_sync_subscriber_categories( $subscriber_id, $selected_categories );
				$category_update_notice = __( 'Subscriber categories updated.', 'task-table' );
			}
		}
	}

	// Handle subscriber deletion
	if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete' && isset( $_GET['subscriber_id'] ) && isset( $_GET['_wpnonce'] ) ) {
		$subscriber_id = intval( $_GET['subscriber_id'] );
		$nonce         = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );

		if ( wp_verify_nonce( $nonce, 'task_table_delete_subscriber_' . $subscriber_id ) ) {
			$wpdb->delete( $subscriber_categories_table, array( 'subscriber_id' => $subscriber_id ) );
			$wpdb->delete( $subscribers_table, array( 'id' => $subscriber_id ) );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Subscriber deleted.', 'task-table' ) . '</p></div>';
		}
	}

	// Handle resend confirmation
	if ( isset( $_GET['action'] ) && $_GET['action'] === 'resend' && isset( $_GET['subscriber_id'] ) && isset( $_GET['_wpnonce'] ) ) {
		$subscriber_id = intval( $_GET['subscriber_id'] );
		if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'task_table_resend_subscriber_' . $subscriber_id ) ) {
			$subscriber = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $subscribers_table WHERE id = %d", $subscriber_id ) );
			if ( $subscriber && (int) $subscriber->confirmed === 0 ) {
				// Generate new token
				$token = wp_generate_password( 32, false, false );
				$wpdb->update(
					$subscribers_table,
					array(
						'confirmation_token' => $token,
						'updated_at'         => current_time( 'mysql' ),
					),
					array( 'id' => $subscriber_id )
				);
				task_table_send_subscription_confirmation_email( $subscriber->email, $token );
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Confirmation email resent.', 'task-table' ) . '</p></div>';
			}
		}
	}

	$search_term       = isset( $_GET['tt_search'] ) ? sanitize_text_field( wp_unslash( $_GET['tt_search'] ) ) : '';
	$search_conditions = array();
	$search_params     = array();
	if ( $search_term !== '' ) {
		$like                = '%' . $wpdb->esc_like( $search_term ) . '%';
		$search_conditions[] = 'email LIKE %s';
		$search_params[]     = $like;
		if ( is_numeric( $search_term ) ) {
			$search_conditions[] = 'id = %d';
			$search_params[]     = (int) $search_term;
		}
	}

	$where_clause = '';
	if ( ! empty( $search_conditions ) ) {
		$where_clause = 'WHERE ' . implode( ' OR ', $search_conditions );
	}

	$subscribers_sql = "SELECT * FROM $subscribers_table $where_clause ORDER BY created_at DESC";
	$subscribers     = ! empty( $search_params )
		? $wpdb->get_results( $wpdb->prepare( $subscribers_sql, $search_params ) ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- WHERE is built from constant fragments with placeholders; values are bound via prepare().
		: $wpdb->get_results( $subscribers_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- WHERE is built from constant fragments with placeholders; values are bound via prepare().

	$total_subscribers     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $subscribers_table" );
	$confirmed_subscribers = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $subscribers_table WHERE confirmed = 1" );
	$awaiting_confirmation = $total_subscribers - $confirmed_subscribers;
	$last_subscriber_date  = $wpdb->get_var( "SELECT created_at FROM $subscribers_table ORDER BY created_at DESC LIMIT 1" );
	$filters_applied       = $search_term !== '';

	$editing_subscriber = null;
	$editing_categories = array();
	if ( isset( $_GET['action'] ) && $_GET['action'] === 'edit-categories' && isset( $_GET['subscriber_id'] ) ) {
		$editing_id         = (int) $_GET['subscriber_id'];
		$editing_subscriber = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $subscribers_table WHERE id = %d", $editing_id ) );
		if ( $editing_subscriber ) {
			$editing_categories = task_table_get_subscriber_categories( $editing_subscriber->id );
		}
	}

	?>
	<div class="wrap task-table-admin">
		<div class="tt-page-header">
			<div class="tt-page-title">
				<h1><?php esc_html_e( 'Manage Subscribers', 'task-table' ); ?></h1>
				<p><?php esc_html_e( 'Keep your mailing list clean and transparent. Tag subscribers with categories, resend confirmations, and add team members manually.', 'task-table' ); ?></p>
			</div>
		</div>

		<?php if ( ! empty( $category_update_notice ) ) : ?>
			<div class="tt-inline-notice success"><p><?php echo esc_html( $category_update_notice ); ?></p></div>
		<?php endif; ?>

		<div class="tt-card-grid">
			<div class="tt-card">
				<h3><?php esc_html_e( 'Total Subscribers', 'task-table' ); ?></h3>
				<div class="tt-card-value">
					<?php echo esc_html( $total_subscribers ); ?>
					<?php if ( $last_subscriber_date ) : ?>
						<?php $latest_ts = strtotime( $last_subscriber_date ); ?>
						<?php
						if ( $latest_ts ) :
							?>
							<small><?php printf( /* translators: %s: human-readable time span, e.g. "3 days" */ esc_html__( '%s ago', 'task-table' ), esc_html( human_time_diff( $latest_ts, strtotime( current_time( 'mysql' ) ) ) ) ); ?></small><?php endif; ?>
					<?php endif; ?>
				</div>
				<div class="tt-card-meta"><?php esc_html_e( 'Contacts who opted in', 'task-table' ); ?></div>
			</div>
			<div class="tt-card">
				<h3><?php esc_html_e( 'Confirmed', 'task-table' ); ?></h3>
				<div class="tt-card-value"><?php echo esc_html( $confirmed_subscribers ); ?></div>
				<div class="tt-card-meta"><?php esc_html_e( 'Actively receiving updates', 'task-table' ); ?></div>
			</div>
			<div class="tt-card">
				<h3><?php esc_html_e( 'Awaiting Confirmation', 'task-table' ); ?></h3>
				<div class="tt-card-value">
					<?php echo esc_html( $awaiting_confirmation ); ?>
					<?php if ( $awaiting_confirmation > 0 ) : ?>
						<small><?php esc_html_e( 'needs follow-up', 'task-table' ); ?></small>
					<?php endif; ?>
				</div>
				<div class="tt-card-meta"><?php esc_html_e( 'Invite them to confirm or resend the email.', 'task-table' ); ?></div>
			</div>
			<div class="tt-card">
				<h3><?php esc_html_e( 'Categories', 'task-table' ); ?></h3>
				<div class="tt-card-value"><?php echo esc_html( count( $categories ) ); ?></div>
				<div class="tt-card-meta"><?php esc_html_e( 'Ways to segment your audience', 'task-table' ); ?></div>
			</div>
		</div>

		<form method="get" class="tt-filters" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
			<input type="hidden" name="page" value="task-table-subscribers">
			<div class="tt-filter-group">
				<label for="tt-subscriber-search"><?php esc_html_e( 'Search', 'task-table' ); ?></label>
				<input type="search" id="tt-subscriber-search" name="tt_search" value="<?php echo esc_attr( $search_term ); ?>" placeholder="<?php esc_attr_e( 'Search by email or ID', 'task-table' ); ?>">
			</div>
			<div class="tt-filter-actions">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Apply Search', 'task-table' ); ?></button>
				<?php if ( $filters_applied ) : ?>
					<a class="button button-link" href="<?php echo esc_url( admin_url( 'admin.php?page=task-table-subscribers' ) ); ?>"><?php esc_html_e( 'Reset', 'task-table' ); ?></a>
				<?php endif; ?>
			</div>
		</form>

		<div class="tt-section">
			<h2><?php esc_html_e( 'Subscriber List', 'task-table' ); ?></h2>
			<?php if ( isset( $_GET['action'] ) && $_GET['action'] === 'edit-categories' && ! $editing_subscriber ) : ?>
				<div class="tt-inline-notice error"><p><?php esc_html_e( 'Subscriber not found.', 'task-table' ); ?></p></div>
			<?php endif; ?>

			<?php if ( ! empty( $subscribers ) ) : ?>
				<table class="wp-list-table widefat fixed striped tt-table">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Subscriber', 'task-table' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Categories', 'task-table' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Status', 'task-table' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Added', 'task-table' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Actions', 'task-table' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $subscribers as $subscriber ) : ?>
							<?php
							$confirmed      = (int) $subscriber->confirmed === 1;
							$status_class   = $confirmed ? 'is-active' : 'is-unconfirmed';
							$status_label   = $confirmed ? __( 'Confirmed', 'task-table' ) : __( 'Awaiting confirmation', 'task-table' );
							$category_names = task_table_get_subscriber_category_names( $subscriber->id );
							$category_tags  = array();
							if ( $category_names !== '' ) {
								$category_tags = array_map( 'trim', explode( ',', $category_names ) );
							}
							$created_timestamp = strtotime( $subscriber->created_at );
							?>
							<tr>
								<td>
									<strong><?php echo esc_html( $subscriber->email ); ?></strong>
								</td>
								<td>
									<?php if ( ! empty( $category_tags ) ) : ?>
										<?php foreach ( $category_tags as $tag ) : ?>
											<span class="tt-pill is-active"><?php echo esc_html( $tag ); ?></span>
										<?php endforeach; ?>
									<?php else : ?>
										&mdash;
									<?php endif; ?>
								</td>
								<td>
									<span class="tt-pill <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status_label ); ?></span>
								</td>
								<td>
									<?php if ( $created_timestamp ) : ?>
										<div><?php echo esc_html( date_i18n( get_option( 'date_format' ), $created_timestamp ) ); ?></div>
										<div class="description"><?php printf( /* translators: %s: human-readable time span, e.g. "3 days" */ esc_html__( '%s ago', 'task-table' ), esc_html( human_time_diff( $created_timestamp, strtotime( current_time( 'mysql' ) ) ) ) ); ?></div>
									<?php else : ?>
										&mdash;
									<?php endif; ?>
								</td>
								<td class="tt-actions">
									<?php if ( ! $confirmed ) : ?>
										<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=task-table-subscribers&action=resend&subscriber_id=' . $subscriber->id ), 'task_table_resend_subscriber_' . $subscriber->id ) ); ?>" class="button button-secondary">
											<span class="dashicons dashicons-email-alt"></span>
											<?php esc_html_e( 'Resend', 'task-table' ); ?>
										</a>
									<?php endif; ?>
									<a href="
									<?php
									echo esc_url(
										add_query_arg(
											array(
												'page'   => 'task-table-subscribers',
												'action' => 'edit-categories',
												'subscriber_id' => $subscriber->id,
											),
											admin_url( 'admin.php' )
										)
									);
									?>
												" class="button button-secondary">
										<span class="dashicons dashicons-category"></span>
										<?php esc_html_e( 'Categories', 'task-table' ); ?>
									</a>
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=task-table-subscribers&action=delete&subscriber_id=' . $subscriber->id ), 'task_table_delete_subscriber_' . $subscriber->id ) ); ?>" class="button is-destructive" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete this subscriber?', 'task-table' ) ); ?>');">
										<span class="dashicons dashicons-trash"></span>
										<?php esc_html_e( 'Delete', 'task-table' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<div class="tt-empty-state">
					<h3><?php esc_html_e( 'No subscribers found', 'task-table' ); ?></h3>
					<p>
						<?php if ( $filters_applied ) : ?>
							<?php esc_html_e( 'No subscriber matches your search. Try another email or reset the filters.', 'task-table' ); ?>
						<?php else : ?>
							<?php esc_html_e( 'Add your first subscriber manually or wait for someone to confirm via the signup form.', 'task-table' ); ?>
						<?php endif; ?>
					</p>
					<?php if ( $filters_applied ) : ?>
						<div class="tt-actions">
							<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=task-table-subscribers' ) ); ?>"><?php esc_html_e( 'Reset Filters', 'task-table' ); ?></a>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>

		<div class="tt-columns">
			<?php if ( $editing_subscriber ) : ?>
				<div class="tt-form-card">
					<h3><?php printf( /* translators: %s: subscriber email address */ esc_html__( 'Edit Categories for %s', 'task-table' ), esc_html( $editing_subscriber->email ) ); ?></h3>
					<form method="post" action="">
						<?php wp_nonce_field( 'task_table_update_subscriber_categories' ); ?>
						<input type="hidden" name="task_table_update_subscriber_categories" value="1">
						<input type="hidden" name="subscriber_id" value="<?php echo esc_attr( $editing_subscriber->id ); ?>">
						<fieldset>
							<legend class="screen-reader-text"><?php esc_html_e( 'Categories', 'task-table' ); ?></legend>
							<?php if ( ! empty( $categories ) ) : ?>
								<?php foreach ( $categories as $category ) : ?>
									<label>
										<input type="checkbox" name="subscriber_categories[]" value="<?php echo esc_attr( $category['id'] ); ?>" <?php checked( in_array( (int) $category['id'], $editing_categories, true ) ); ?>>
										<?php echo esc_html( $category['name'] ); ?>
									</label><br>
								<?php endforeach; ?>
							<?php else : ?>
								<p><?php esc_html_e( 'No categories available.', 'task-table' ); ?></p>
							<?php endif; ?>
						</fieldset>
						<p class="submit">
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Categories', 'task-table' ); ?></button>
							<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=task-table-subscribers' ) ); ?>"><?php esc_html_e( 'Cancel', 'task-table' ); ?></a>
						</p>
					</form>
				</div>
			<?php endif; ?>

			<div class="tt-form-card">
				<h3><?php esc_html_e( 'Add New Subscriber', 'task-table' ); ?></h3>
				<form method="post" action="">
					<?php wp_nonce_field( 'task_table_add_subscriber', 'task_table_add_subscriber_nonce' ); ?>
					<p>
						<label for="subscriber_email" class="screen-reader-text"><?php esc_html_e( 'Email', 'task-table' ); ?></label>
						<input type="email" name="subscriber_email" id="subscriber_email" class="regular-text" placeholder="<?php esc_attr_e( 'Email address', 'task-table' ); ?>" required>
					</p>
					<p>
						<label>
							<input type="checkbox" name="subscriber_confirmed" id="subscriber_confirmed" value="1">
							<?php esc_html_e( 'Mark as confirmed', 'task-table' ); ?>
						</label>
					</p>
					<p><?php esc_html_e( 'Categories', 'task-table' ); ?></p>
					<div>
						<?php
						$preselected = isset( $_POST['subscriber_categories'] ) ? array_map( 'intval', (array) $_POST['subscriber_categories'] ) : array();
						$preselected = ! empty( $preselected ) ? $preselected : array( $default_category_id );
						?>
						<?php if ( ! empty( $categories ) ) : ?>
							<?php foreach ( $categories as $category ) : ?>
								<label style="display:inline-block;margin-right:12px;margin-bottom:6px;">
									<input type="checkbox" name="subscriber_categories[]" value="<?php echo esc_attr( $category['id'] ); ?>" <?php checked( in_array( (int) $category['id'], $preselected, true ) ); ?>>
									<?php echo esc_html( $category['name'] ); ?>
								</label>
							<?php endforeach; ?>
						<?php else : ?>
							<p><?php esc_html_e( 'No categories available. Please create one first.', 'task-table' ); ?></p>
						<?php endif; ?>
					</div>
					<p class="submit">
						<input type="submit" name="submit" class="button button-primary" value="<?php esc_attr_e( 'Add Subscriber', 'task-table' ); ?>">
					</p>
				</form>
			</div>
		</div>
	</div>
	<?php

	// Handle form submission
	if ( isset( $_POST['submit'] ) && isset( $_POST['task_table_add_subscriber_nonce'] ) ) {
		if ( wp_verify_nonce( wp_unslash( $_POST['task_table_add_subscriber_nonce'] ), 'task_table_add_subscriber' ) ) {
			$email        = sanitize_email( wp_unslash( $_POST['subscriber_email'] ) );
			$confirmed    = isset( $_POST['subscriber_confirmed'] ) ? 1 : 0;
			$category_ids = isset( $_POST['subscriber_categories'] ) ? array_map( 'intval', (array) wp_unslash( $_POST['subscriber_categories'] ) ) : array();

			if ( is_email( $email ) ) {
				$exists = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM $subscribers_table WHERE email = %s",
						$email
					)
				);

				if ( $exists ) {
					echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'This email is already subscribed.', 'task-table' ) . '</p></div>';
				} else {
					$wpdb->insert(
						$subscribers_table,
						array(
							'email'      => $email,
							'confirmed'  => $confirmed,
							'created_at' => current_time( 'mysql' ),
						)
					);

					$new_id = (int) $wpdb->insert_id;
					if ( $new_id ) {
						task_table_sync_subscriber_categories( $new_id, $category_ids );
					}

					echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Subscriber added successfully.', 'task-table' ) . '</p></div>';
					echo '<script>window.location.href = window.location.href;</script>';
				}
			} else {
				// Fix: Corrected the PHP syntax in the error message
				echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Please enter a valid email address.', 'task-table' ) . '</p></div>';
			}
		}
	}
}

/**
 * Admin categories management page
 */
function task_table_admin_categories_page() {
	global $wpdb;

	$categories_table            = $wpdb->prefix . 'task_table_categories';
	$events_table                = $wpdb->prefix . 'task_table_events';
	$subscriber_categories_table = $wpdb->prefix . 'task_table_subscriber_categories';

	$message             = '';
	$error               = '';
	$default_category_id = task_table_get_default_category_id();
	task_table_print_admin_styles();

	// Handle add category
	if ( isset( $_POST['task_table_add_category_nonce'] ) && wp_verify_nonce( wp_unslash( $_POST['task_table_add_category_nonce'] ), 'task_table_add_category' ) ) {
		$name        = isset( $_POST['category_name'] ) ? sanitize_text_field( wp_unslash( $_POST['category_name'] ) ) : '';
		$slug_input  = isset( $_POST['category_slug'] ) ? sanitize_text_field( wp_unslash( $_POST['category_slug'] ) ) : '';
		$description = isset( $_POST['category_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['category_description'] ) ) : '';

		if ( $name === '' ) {
			$error = __( 'Category name is required.', 'task-table' );
		} else {
			$slug      = task_table_get_unique_category_slug( $slug_input !== '' ? $slug_input : $name );
			$next_sort = (int) $wpdb->get_var( "SELECT MAX(sort_order) FROM $categories_table" );
			$next_sort = $next_sort ? $next_sort + 10 : 10;

			$inserted = $wpdb->insert(
				$categories_table,
				array(
					'name'        => $name,
					'slug'        => $slug,
					'description' => $description,
					'sort_order'  => $next_sort,
					'created_at'  => current_time( 'mysql' ),
					'updated_at'  => current_time( 'mysql' ),
				)
			);

			if ( $inserted !== false ) {
				$new_id = (int) $wpdb->insert_id;
				if ( ! $default_category_id ) {
					update_option( TASK_TABLE_DEFAULT_CATEGORY_OPTION, $new_id );
					$default_category_id = $new_id;
				}
				$message = __( 'Category created successfully.', 'task-table' );
			} else {
				$error = __( 'Failed to create category.', 'task-table' );
			}
		}
	}

	// Handle update category
	if ( isset( $_POST['task_table_update_category_nonce'] ) && wp_verify_nonce( wp_unslash( $_POST['task_table_update_category_nonce'] ), 'task_table_update_category' ) ) {
		$category_id = isset( $_POST['category_id'] ) ? (int) $_POST['category_id'] : 0;
		$category    = $category_id ? task_table_get_category( $category_id ) : null;

		if ( $category ) {
			$name        = isset( $_POST['category_name'] ) ? sanitize_text_field( wp_unslash( $_POST['category_name'] ) ) : $category['name'];
			$slug_input  = isset( $_POST['category_slug'] ) ? sanitize_text_field( wp_unslash( $_POST['category_slug'] ) ) : $category['slug'];
			$description = isset( $_POST['category_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['category_description'] ) ) : $category['description'];
			$sort_order  = isset( $_POST['category_sort_order'] ) ? (int) $_POST['category_sort_order'] : (int) $category['sort_order'];

			if ( $name === '' ) {
				$error = __( 'Category name is required.', 'task-table' );
			} else {
				$slug = task_table_get_unique_category_slug( $slug_input !== '' ? $slug_input : $name, $category_id );

				$wpdb->update(
					$categories_table,
					array(
						'name'        => $name,
						'slug'        => $slug,
						'description' => $description,
						'sort_order'  => $sort_order,
						'updated_at'  => current_time( 'mysql' ),
					),
					array( 'id' => $category_id )
				);

				$message = __( 'Category updated.', 'task-table' );
			}
		} else {
			$error = __( 'Category not found.', 'task-table' );
		}
	}

	// Handle delete category
	if ( isset( $_POST['task_table_delete_category_nonce'] ) && wp_verify_nonce( wp_unslash( $_POST['task_table_delete_category_nonce'] ), 'task_table_delete_category' ) ) {
		$category_id          = isset( $_POST['category_id'] ) ? (int) $_POST['category_id'] : 0;
		$fallback_reassign_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM $categories_table WHERE id <> %d ORDER BY sort_order ASC, name ASC LIMIT 1",
				$category_id
			)
		);

		if ( ! $fallback_reassign_id ) {
			$error = __( 'At least one category must remain.', 'task-table' );
		} else {
			$reassign_id = isset( $_POST['reassign_category_id'] ) ? (int) $_POST['reassign_category_id'] : $fallback_reassign_id;
			if ( $reassign_id === $category_id || ! task_table_get_category( $reassign_id ) ) {
				$reassign_id = $fallback_reassign_id;
			}

			// Reassign events
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE $events_table SET category_id = %d WHERE category_id = %d",
					$reassign_id,
					$category_id
				)
			);

			// Reassign subscriber preferences
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $subscriber_categories_table ) ) === $subscriber_categories_table ) {
				$wpdb->query(
					$wpdb->prepare(
						"INSERT IGNORE INTO $subscriber_categories_table (subscriber_id, category_id)
                     SELECT subscriber_id, %d FROM $subscriber_categories_table WHERE category_id = %d",
						$reassign_id,
						$category_id
					)
				);
				$wpdb->query(
					$wpdb->prepare(
						"DELETE FROM $subscriber_categories_table WHERE category_id = %d",
						$category_id
					)
				);
			}

			$wpdb->delete( $categories_table, array( 'id' => $category_id ) );

			if ( $category_id === $default_category_id || ! task_table_get_category( $default_category_id ) ) {
				update_option( TASK_TABLE_DEFAULT_CATEGORY_OPTION, $reassign_id );
				$default_category_id = $reassign_id;
			}

			$message = __( 'Category deleted.', 'task-table' );
		}
	}

	$categories            = task_table_get_categories();
	$event_counts_raw      = $wpdb->get_results( "SELECT category_id, COUNT(*) AS total FROM $events_table GROUP BY category_id", OBJECT_K );
	$subscriber_counts_raw = array();
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $subscriber_categories_table ) ) === $subscriber_categories_table ) {
		$subscriber_counts_raw = $wpdb->get_results( "SELECT category_id, COUNT(*) AS total FROM $subscriber_categories_table GROUP BY category_id", OBJECT_K );
	}

	$editing_category = null;
	if ( isset( $_GET['edit'] ) ) {
		$edit_id          = (int) $_GET['edit'];
		$editing_category = task_table_get_category( $edit_id );
	}

	?>
	<div class="wrap task-table-admin">
		<div class="tt-page-header">
			<div class="tt-page-title">
				<h1><?php esc_html_e( 'Task Categories', 'task-table' ); ?></h1>
				<p><?php esc_html_e( 'Group your events into meaningful segments. Categories power signup filters, subscriber preferences, and email targeting.', 'task-table' ); ?></p>
			</div>
		</div>

		<?php if ( $message ) : ?>
			<div class="tt-inline-notice success"><p><?php echo esc_html( $message ); ?></p></div>
		<?php endif; ?>

		<?php if ( $error ) : ?>
			<div class="tt-inline-notice error"><p><?php echo esc_html( $error ); ?></p></div>
		<?php endif; ?>

		<?php
		$total_events = 0;
		foreach ( $event_counts_raw as $row ) {
			$total_events += (int) $row->total;
		}
		$total_subscribers = 0;
		foreach ( $subscriber_counts_raw as $row ) {
			$total_subscribers += (int) $row->total;
		}
		?>

		<div class="tt-card-grid">
			<div class="tt-card">
				<h3><?php esc_html_e( 'Categories', 'task-table' ); ?></h3>
				<div class="tt-card-value"><?php echo esc_html( count( $categories ) ); ?></div>
				<div class="tt-card-meta"><?php esc_html_e( 'Ready to organize your workload', 'task-table' ); ?></div>
			</div>
			<div class="tt-card">
				<h3><?php esc_html_e( 'Events Tagged', 'task-table' ); ?></h3>
				<div class="tt-card-value"><?php echo esc_html( $total_events ); ?></div>
				<div class="tt-card-meta"><?php esc_html_e( 'Events currently assigned to categories', 'task-table' ); ?></div>
			</div>
			<div class="tt-card">
				<h3><?php esc_html_e( 'Subscriber Preferences', 'task-table' ); ?></h3>
				<div class="tt-card-value"><?php echo esc_html( $total_subscribers ); ?></div>
				<div class="tt-card-meta"><?php esc_html_e( 'Category selections saved by subscribers', 'task-table' ); ?></div>
			</div>
		</div>

		<div class="tt-columns">
			<div class="tt-form-card">
				<h3><?php esc_html_e( 'Add New Category', 'task-table' ); ?></h3>
				<form method="post">
					<?php wp_nonce_field( 'task_table_add_category', 'task_table_add_category_nonce' ); ?>
					<p>
						<label for="category_name" class="screen-reader-text"><?php esc_html_e( 'Name', 'task-table' ); ?></label>
						<input type="text" name="category_name" id="category_name" class="regular-text" placeholder="<?php esc_attr_e( 'Category name', 'task-table' ); ?>" required>
					</p>
					<p>
						<label for="category_slug" class="screen-reader-text"><?php esc_html_e( 'Slug', 'task-table' ); ?></label>
						<input type="text" name="category_slug" id="category_slug" class="regular-text" placeholder="<?php esc_attr_e( 'Slug (optional)', 'task-table' ); ?>">
					</p>
					<p>
						<label for="category_description" class="screen-reader-text"><?php esc_html_e( 'Description', 'task-table' ); ?></label>
						<textarea name="category_description" id="category_description" class="large-text" rows="3" placeholder="<?php esc_attr_e( 'Short description (optional)', 'task-table' ); ?>"></textarea>
					</p>
					<p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e( 'Add Category', 'task-table' ); ?></button></p>
				</form>
			</div>

			<?php if ( $editing_category ) : ?>
				<div class="tt-form-card">
					<h3><?php esc_html_e( 'Edit Category', 'task-table' ); ?></h3>
					<form method="post">
						<?php wp_nonce_field( 'task_table_update_category', 'task_table_update_category_nonce' ); ?>
						<input type="hidden" name="category_id" value="<?php echo esc_attr( $editing_category['id'] ); ?>">
						<p>
							<label for="edit_category_name" class="screen-reader-text"><?php esc_html_e( 'Name', 'task-table' ); ?></label>
							<input type="text" name="category_name" id="edit_category_name" class="regular-text" value="<?php echo esc_attr( $editing_category['name'] ); ?>" required>
						</p>
						<p>
							<label for="edit_category_slug" class="screen-reader-text"><?php esc_html_e( 'Slug', 'task-table' ); ?></label>
							<input type="text" name="category_slug" id="edit_category_slug" class="regular-text" value="<?php echo esc_attr( $editing_category['slug'] ); ?>">
						</p>
						<p>
							<label for="edit_category_description" class="screen-reader-text"><?php esc_html_e( 'Description', 'task-table' ); ?></label>
							<textarea name="category_description" id="edit_category_description" class="large-text" rows="3"><?php echo esc_textarea( $editing_category['description'] ); ?></textarea>
						</p>
						<p>
							<label for="edit_category_sort_order" class="screen-reader-text"><?php esc_html_e( 'Sort Order', 'task-table' ); ?></label>
							<input type="number" name="category_sort_order" id="edit_category_sort_order" value="<?php echo esc_attr( $editing_category['sort_order'] ); ?>" step="1">
						</p>
						<p class="submit">
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Changes', 'task-table' ); ?></button>
							<a href="<?php echo esc_url( remove_query_arg( 'edit' ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'task-table' ); ?></a>
						</p>
					</form>
				</div>
			<?php endif; ?>
		</div>

		<div class="tt-section">
			<h2><?php esc_html_e( 'Existing Categories', 'task-table' ); ?></h2>
			<?php if ( empty( $categories ) ) : ?>
				<div class="tt-empty-state">
					<h3><?php esc_html_e( 'No categories yet', 'task-table' ); ?></h3>
					<p><?php esc_html_e( 'Create at least one category to start grouping events and subscribers.', 'task-table' ); ?></p>
				</div>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped tt-table">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Name', 'task-table' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Slug', 'task-table' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Sort Order', 'task-table' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Events', 'task-table' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Subscribers', 'task-table' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Actions', 'task-table' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php $has_alternative_categories = count( $categories ) > 1; ?>
						<?php foreach ( $categories as $category ) : ?>
							<?php
							$event_total      = isset( $event_counts_raw[ $category['id'] ] ) ? (int) $event_counts_raw[ $category['id'] ]->total : 0;
							$subscriber_total = isset( $subscriber_counts_raw[ $category['id'] ] ) ? (int) $subscriber_counts_raw[ $category['id'] ]->total : 0;
							?>
							<tr>
								<td>
									<strong><?php echo esc_html( $category['name'] ); ?></strong>
								</td>
								<td><?php echo esc_html( $category['slug'] ); ?></td>
								<td><?php echo esc_html( $category['sort_order'] ); ?></td>
								<td><?php echo esc_html( $event_total ); ?></td>
								<td><?php echo esc_html( $subscriber_total ); ?></td>
								<td class="tt-actions">
									<a href="<?php echo esc_url( add_query_arg( array( 'edit' => $category['id'] ) ) ); ?>" class="button button-secondary">
										<span class="dashicons dashicons-edit"></span>
										<?php esc_html_e( 'Edit', 'task-table' ); ?>
									</a>
									<?php if ( $has_alternative_categories ) : ?>
										<?php
										$reassign_category_id = 0;
										foreach ( $categories as $candidate_category ) {
											if ( (int) $candidate_category['id'] !== (int) $category['id'] ) {
												$reassign_category_id = (int) $candidate_category['id'];
												break;
											}
										}
										?>
										<form method="post" action="" style="display:inline-block;">
											<?php wp_nonce_field( 'task_table_delete_category', 'task_table_delete_category_nonce' ); ?>
											<input type="hidden" name="category_id" value="<?php echo esc_attr( $category['id'] ); ?>">
											<input type="hidden" name="reassign_category_id" value="<?php echo esc_attr( $reassign_category_id ); ?>">
											<button type="submit" class="button is-destructive" onclick="return confirm('<?php echo esc_js( __( 'Delete this category? Associated items will be reassigned automatically.', 'task-table' ) ); ?>');">
												<span class="dashicons dashicons-trash"></span>
												<?php esc_html_e( 'Delete', 'task-table' ); ?>
											</button>
										</form>
									<?php else : ?>
										<button type="button" class="button is-destructive" disabled>
											<span class="dashicons dashicons-trash"></span>
											<?php esc_html_e( 'Delete', 'task-table' ); ?>
										</button>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
	</div>
	<?php
}

/**
 * Admin email settings page
 */
function task_table_admin_email_settings_page() {
	task_table_print_admin_styles();

	// Handle form submission
	if ( isset( $_POST['task_table_email_settings_nonce'] ) && wp_verify_nonce( wp_unslash( $_POST['task_table_email_settings_nonce'] ), 'task_table_email_settings' ) ) {
		$templates = task_table_get_email_templates();

		foreach ( array_keys( $templates ) as $template_key ) {
			if ( isset( $_POST[ "task_table_email_{$template_key}_subject" ] ) ) {
				update_option( "task_table_email_{$template_key}_subject", sanitize_text_field( wp_unslash( $_POST[ "task_table_email_{$template_key}_subject" ] ) ) );
			}

			if ( isset( $_POST[ "task_table_email_{$template_key}_message" ] ) ) {
				update_option( "task_table_email_{$template_key}_message", sanitize_textarea_field( wp_unslash( $_POST[ "task_table_email_{$template_key}_message" ] ) ) );
			}
		}

		$submitted_recipients = null;

		if ( isset( $_POST['signup_notification_recipients_b64'] ) ) {
			$encoded_payload = trim( (string) wp_unslash( $_POST['signup_notification_recipients_b64'] ) );
			if ( $encoded_payload !== '' ) {
				$decoded_payload = base64_decode( $encoded_payload, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- the settings form base64-encodes its JSON payload.
				if ( is_string( $decoded_payload ) ) {
					$raw_recipients = json_decode( $decoded_payload, true );
					if ( is_array( $raw_recipients ) ) {
						$submitted_recipients = $raw_recipients;
					}
				}
			}
		}

		if ( $submitted_recipients === null && isset( $_POST['signup_notification_recipients'] ) ) {
			$raw_json_payload = wp_unslash( $_POST['signup_notification_recipients'] );
			if ( is_string( $raw_json_payload ) ) {
				$raw_json_payload = html_entity_decode( $raw_json_payload, ENT_QUOTES, 'UTF-8' );
				$raw_recipients   = json_decode( $raw_json_payload, true );
				if ( is_array( $raw_recipients ) ) {
					$submitted_recipients = $raw_recipients;
				}
			}
		}

		if ( $submitted_recipients !== null ) {
			task_table_save_signup_notification_recipients( $submitted_recipients );
		} elseif ( isset( $_POST['moderator_emails'] ) ) {
			// Legacy fallback for older forms posting only a comma-separated email list.
			$moderator_emails  = sanitize_text_field( wp_unslash( $_POST['moderator_emails'] ) );
			$emails_array      = array_filter( explode( ',', $moderator_emails ) );
			$legacy_recipients = array();
			foreach ( $emails_array as $email ) {
				$email = sanitize_email( trim( $email ) );
				if ( is_email( $email ) ) {
					$legacy_recipients[] = array(
						'email'        => $email,
						'category_ids' => array(),
					);
				}
			}
			task_table_save_signup_notification_recipients( $legacy_recipients );
		}

		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'E-Mail-Einstellungen gespeichert.', 'task-table' ) . '</p></div>';
	}

	$templates       = task_table_get_email_templates();
	$template_labels = array(
		'subscription_confirmation' => __( 'Abo-Bestätigung', 'task-table' ),
		'subscription_manage'       => __( 'Abo verwalten (bestehendes Abo)', 'task-table' ),
		'signup_approved'           => __( 'Anmeldung angenommen', 'task-table' ),
		'signup_denied'             => __( 'Anmeldung abgelehnt', 'task-table' ),
		'new_signup'                => __( 'Neue Anmeldung', 'task-table' ),
		'new_event'                 => __( 'Neue Veranstaltung', 'task-table' ),
	);

	$categories             = task_table_get_categories();
	$signup_recipients      = task_table_get_signup_notification_recipients();
	$signup_recipients_json = wp_json_encode( $signup_recipients );
	if ( ! is_string( $signup_recipients_json ) ) {
		$signup_recipients_json = '[]';
	}

	?>
	<div class="wrap task-table-admin">
		<div class="tt-page-header">
			<div class="tt-page-title">
				<h1><?php esc_html_e( 'E-Mail-Einstellungen', 'task-table' ); ?></h1>
				<p><?php esc_html_e( 'Passe die Benachrichtigungen und automatischen Antworten an, damit jede Nachricht im richtigen Ton an die richtige Person geht.', 'task-table' ); ?></p>
			</div>
		</div>

		<form method="post" action="" class="tt-form-card">
			<?php wp_nonce_field( 'task_table_email_settings', 'task_table_email_settings_nonce' ); ?>

			<div class="tt-toolbar" style="justify-content: flex-start;">
				<h2 style="margin:0; font-size: 16px; font-weight:600; color: var(--tt-text-muted); text-transform: uppercase; letter-spacing: 0.04em;">
					<?php esc_html_e( 'Benachrichtigungsempfänger', 'task-table' ); ?>
				</h2>
			</div>

			<div class="email-recipients-container" style="margin-bottom:24px;">
				<div id="signup-recipient-list" style="display:grid; gap:12px; margin-bottom:16px;"></div>

				<div class="tt-form-card" style="margin:0; padding:16px; border:1px solid var(--tt-border); border-radius:10px;">
					<h3 style="margin:0 0 10px;"><?php esc_html_e( 'Empfänger hinzufügen', 'task-table' ); ?></h3>

					<div class="email-input-container" style="margin-bottom:10px;">
						<input type="email" id="new_email" placeholder="<?php esc_attr_e( 'Neue E-Mail-Adresse hinzufügen', 'task-table' ); ?>" class="regular-text">
						<button type="button" id="add_signup_recipient" class="button button-secondary">
							<span class="dashicons dashicons-plus-alt"></span>
							<?php esc_html_e( 'Hinzufügen', 'task-table' ); ?>
						</button>
					</div>

					<label style="display:inline-flex; align-items:center; gap:8px; margin-bottom:10px;">
						<input type="checkbox" id="recipient_all_categories" checked>
						<?php esc_html_e( 'Alle Kategorien', 'task-table' ); ?>
					</label>

					<div id="recipient-category-picker" style="display:flex; flex-wrap:wrap; gap:10px; margin-bottom:4px;">
						<?php if ( ! empty( $categories ) ) : ?>
							<?php foreach ( $categories as $category ) : ?>
								<label style="display:inline-flex; align-items:center; gap:6px;">
									<input type="checkbox" class="recipient-category" value="<?php echo esc_attr( (int) $category['id'] ); ?>">
									<?php echo esc_html( $category['name'] ); ?>
								</label>
							<?php endforeach; ?>
						<?php else : ?>
							<span class="description"><?php esc_html_e( 'Keine Kategorien verfügbar.', 'task-table' ); ?></span>
						<?php endif; ?>
					</div>
				</div>

				<input type="hidden" name="signup_notification_recipients" id="signup_notification_recipients" value="<?php echo esc_attr( $signup_recipients_json ); ?>">
				<input type="hidden" name="signup_notification_recipients_b64" id="signup_notification_recipients_b64" value="">
				<input type="hidden" name="moderator_emails" id="moderator_emails" value="">

				<p class="description" style="margin-top:12px; color: var(--tt-text-muted);">
					<?php esc_html_e( 'Lege für jede Adresse fest, ob sie alle neuen Anmeldungen oder nur Anmeldungen bestimmter Kategorien erhalten soll.', 'task-table' ); ?>
				</p>
			</div>

			<h2 style="margin: 0 0 16px; font-size: 18px;">
				<?php esc_html_e( 'E-Mail-Vorlagen', 'task-table' ); ?>
			</h2>

			<div class="email-templates-tabs tt-pill-toggle" style="margin-bottom:18px;">
				<a href="#general-settings" class="nav-tab is-active" data-tab="general-settings">
					<?php esc_html_e( 'Platzhalter', 'task-table' ); ?>
				</a>
				<?php foreach ( array_keys( $templates ) as $tab_key ) : ?>
					<?php $tab_label = isset( $template_labels[ $tab_key ] ) ? $template_labels[ $tab_key ] : ucwords( str_replace( '_', ' ', $tab_key ) ); ?>
					<a href="#template-<?php echo esc_attr( $tab_key ); ?>" class="nav-tab" data-tab="template-<?php echo esc_attr( $tab_key ); ?>">
						<?php echo esc_html( $tab_label ); ?>
					</a>
				<?php endforeach; ?>
			</div>

			<div id="general-settings" class="email-template-tab" style="display: block;">
				<p style="margin-top:0;">
					<?php esc_html_e( 'Nutze diese Platzhalter in jeder Vorlage, um Nachrichten automatisch zu personalisieren. Beim Versand werden sie durch die passenden Werte ersetzt.', 'task-table' ); ?>
				</p>
				<ul class="task-table-placeholders">
					<li><code>{site_name}</code> - <?php esc_html_e( 'Seitenname', 'task-table' ); ?></li>
					<li><code>{user_name}</code> - <?php esc_html_e( 'Name der Person (falls vorhanden)', 'task-table' ); ?></li>
					<li><code>{user_email}</code> - <?php esc_html_e( 'E-Mail-Adresse der Person (falls vorhanden)', 'task-table' ); ?></li>
					<li><code>{event_title}</code> - <?php esc_html_e( 'Titel der Veranstaltung (falls vorhanden)', 'task-table' ); ?></li>
					<li><code>{event_details}</code> - <?php esc_html_e( 'Details zur Veranstaltung (falls vorhanden)', 'task-table' ); ?></li>
					<li><code>{event_id}</code> - <?php esc_html_e( 'Veranstaltungs-ID (falls vorhanden)', 'task-table' ); ?></li>
					<li><code>{event_category_line}</code> - <?php esc_html_e( 'Kategorie als Textzeile (nur neue Anmeldung)', 'task-table' ); ?></li>
					<li><code>{confirmation_link}</code> - <?php esc_html_e( 'Bestätigungslink (nur Abo-E-Mails)', 'task-table' ); ?></li>
					<li><code>{manage_link}</code> - <?php esc_html_e( 'Link zum Verwalten/Abmelden (neue Veranstaltung, Abo verwalten; wird sonst automatisch angehängt)', 'task-table' ); ?></li>
				</ul>
			</div>

			<?php foreach ( $templates as $template_key => $template ) : ?>
				<div id="template-<?php echo esc_attr( $template_key ); ?>" class="email-template-tab" style="display: none;">
					<?php $template_title = isset( $template_labels[ $template_key ] ) ? $template_labels[ $template_key ] : ucwords( str_replace( '_', ' ', $template_key ) ); ?>
					<h3><?php echo esc_html( $template_title ); ?> <?php esc_html_e( 'Vorlage', 'task-table' ); ?></h3>
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="task_table_email_<?php echo esc_attr( $template_key ); ?>_subject"><?php esc_html_e( 'Betreff', 'task-table' ); ?></label>
							</th>
							<td>
								<input type="text" name="task_table_email_<?php echo esc_attr( $template_key ); ?>_subject" id="task_table_email_<?php echo esc_attr( $template_key ); ?>_subject" class="large-text" value="<?php echo esc_attr( get_option( "task_table_email_{$template_key}_subject", $template['subject'] ) ); ?>">
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="task_table_email_<?php echo esc_attr( $template_key ); ?>_message"><?php esc_html_e( 'Nachricht', 'task-table' ); ?></label>
							</th>
							<td>
								<textarea name="task_table_email_<?php echo esc_attr( $template_key ); ?>_message" id="task_table_email_<?php echo esc_attr( $template_key ); ?>_message" class="large-text" rows="10"><?php echo esc_textarea( get_option( "task_table_email_{$template_key}_message", $template['message'] ) ); ?></textarea>
							</td>
						</tr>
					</table>
				</div>
			<?php endforeach; ?>

			<p class="submit">
				<input type="submit" name="submit" id="submit" class="button button-primary" value="<?php esc_attr_e( 'Änderungen speichern', 'task-table' ); ?>">
			</p>
		</form>
	</div>

	<script>
		jQuery(document).ready(function($) {
			$('.email-templates-tabs a.nav-tab').on('click', function(e) {
				e.preventDefault();
				const tabId = $(this).data('tab');

				$('.email-templates-tabs a.nav-tab').removeClass('is-active');
				$(this).addClass('is-active');

				$('.email-template-tab').hide();
				$('#' + tabId).show();
			});

			const categoryMap = 
			<?php
			echo wp_json_encode(
				array_map(
					static function ( $category ) {
						return array(
							'id'   => (int) $category['id'],
							'name' => $category['name'],
						);
					},
					$categories
				)
			);
			?>
			;
			const recipientsInput = $('#signup_notification_recipients');
			const recipientsB64Input = $('#signup_notification_recipients_b64');
			const moderatorEmailsInput = $('#moderator_emails');
			const recipientList = $('#signup-recipient-list');
			const categoryCheckboxes = $('.recipient-category');
			const allCategoriesToggle = $('#recipient_all_categories');
			let recipients = [];

			try {
				recipients = JSON.parse(recipientsInput.val() || '[]');
			} catch (e) {
				recipients = [];
			}

			function normalizeRecipients(rawRecipients) {
				const byEmail = {};

				if (!Array.isArray(rawRecipients)) {
					return [];
				}

				rawRecipients.forEach(function(recipient) {
					if (!recipient || typeof recipient !== 'object') {
						return;
					}

					const email = String(recipient.email || '').trim();
					if (!email.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
						return;
					}

					const categoryIds = [];
					if (Array.isArray(recipient.category_ids)) {
						recipient.category_ids.forEach(function(rawId) {
							const id = parseInt(rawId, 10);
							if (Number.isNaN(id) || id <= 0) {
								return;
							}

							const exists = categoryMap.some(function(category) {
								return parseInt(category.id, 10) === id;
							});

							if (!exists || categoryIds.indexOf(id) !== -1) {
								return;
							}

							categoryIds.push(id);
						});
					}

					byEmail[email.toLowerCase()] = {
						email: email,
						category_ids: categoryIds
					};
				});

				return Object.keys(byEmail).map(function(key) {
					return byEmail[key];
				});
			}

			function encodeBase64Unicode(value) {
				try {
					return window.btoa(unescape(encodeURIComponent(String(value))));
				} catch (error) {
					return '';
				}
			}

			function getSelectedCategoryIds() {
				const selected = [];
				categoryCheckboxes.filter(':checked').each(function() {
					const id = parseInt($(this).val(), 10);
					if (!Number.isNaN(id) && id > 0 && selected.indexOf(id) === -1) {
						selected.push(id);
					}
				});
				return selected;
			}

			function updateRecipientsInput() {
				recipients = normalizeRecipients(recipients);

				const jsonPayload = JSON.stringify(recipients);
				const emailsPayload = recipients.map(function(recipient) {
					return recipient.email;
				}).join(',');

				recipientsInput.val(jsonPayload);
				recipientsB64Input.val(encodeBase64Unicode(jsonPayload));
				moderatorEmailsInput.val(emailsPayload);
			}

			function getCategoryName(categoryId) {
				const match = categoryMap.find(function(category) {
					return parseInt(category.id, 10) === parseInt(categoryId, 10);
				});

				return match ? String(match.name) : '';
			}

			function renderRecipients() {
				recipientList.empty();

				if (!recipients.length) {
					recipientList.append(
						$('<p class="description"></p>').text('<?php echo esc_js( __( 'Noch keine Empfänger angelegt.', 'task-table' ) ); ?>')
					);
					return;
				}

				recipients.forEach(function(recipient) {
					const row = $('<div></div>').css({
						display: 'flex',
						justifyContent: 'space-between',
						gap: '12px',
						alignItems: 'flex-start',
						border: '1px solid var(--tt-border)',
						borderRadius: '10px',
						padding: '12px'
					});

					const info = $('<div></div>');
					info.append($('<strong></strong>').text(recipient.email));

					const categoryWrap = $('<div></div>').css({
						marginTop: '8px',
						display: 'flex',
						flexWrap: 'wrap',
						gap: '6px'
					});

					if (!recipient.category_ids.length) {
						categoryWrap.append(
							$('<span class="tt-pill is-active"></span>').text('<?php echo esc_js( __( 'Alle Kategorien', 'task-table' ) ); ?>')
						);
					} else {
						recipient.category_ids.forEach(function(categoryId) {
							const categoryName = getCategoryName(categoryId);
							if (!categoryName) {
								return;
							}
							categoryWrap.append(
								$('<span class="tt-pill is-unconfirmed"></span>').text(categoryName)
							);
						});
					}

					info.append(categoryWrap);

					const removeButton = $('<button type="button" class="button button-secondary remove-signup-recipient"></button>')
						.attr('data-email', recipient.email)
						.text('<?php echo esc_js( __( 'Entfernen', 'task-table' ) ); ?>');

					row.append(info).append(removeButton);
					recipientList.append(row);
				});
			}

			function syncCategoryInputState() {
				const disableCategories = allCategoriesToggle.is(':checked');
				categoryCheckboxes.prop('disabled', disableCategories);
				if (disableCategories) {
					categoryCheckboxes.prop('checked', false);
				}
			}

			$('#add_signup_recipient').on('click', function() {
				const email = $('#new_email').val().trim();
				if (!email) {
					return;
				}

				if (!email.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
					window.alert('<?php echo esc_js( __( 'Bitte gib eine gültige E-Mail-Adresse ein.', 'task-table' ) ); ?>');
					return;
				}

				recipients = normalizeRecipients(recipients);
				const isDuplicate = recipients.some(function(recipient) {
					return recipient.email.toLowerCase() === email.toLowerCase();
				});

				if (isDuplicate) {
					window.alert('<?php echo esc_js( __( 'Diese E-Mail-Adresse wurde bereits hinzugefügt.', 'task-table' ) ); ?>');
					return;
				}

				const allCategories = allCategoriesToggle.is(':checked');
				const selectedCategories = allCategories ? [] : getSelectedCategoryIds();

				if (!allCategories && !selectedCategories.length) {
					window.alert('<?php echo esc_js( __( 'Bitte wähle mindestens eine Kategorie oder aktiviere „Alle Kategorien“.', 'task-table' ) ); ?>');
					return;
				}

				recipients.push({
					email: email,
					category_ids: selectedCategories
				});

				recipients = normalizeRecipients(recipients);
				renderRecipients();
				updateRecipientsInput();

				$('#new_email').val('').focus();
				allCategoriesToggle.prop('checked', true);
				syncCategoryInputState();
			});

			$('#new_email').on('keypress', function(e) {
				if (e.which === 13) {
					e.preventDefault();
					$('#add_signup_recipient').click();
				}
			});

			allCategoriesToggle.on('change', syncCategoryInputState);

			$(document).on('click', '.remove-signup-recipient', function() {
				const email = String($(this).data('email') || '').toLowerCase();
				recipients = recipients.filter(function(recipient) {
					return recipient.email.toLowerCase() !== email;
				});
				renderRecipients();
				updateRecipientsInput();
			});

			$('form.tt-form-card').on('submit', function() {
				updateRecipientsInput();
			});

			recipients = normalizeRecipients(recipients);
			syncCategoryInputState();
			renderRecipients();
			updateRecipientsInput();
		});
	</script>
	<?php
}

/**
 * Parse a string containing multiple email addresses.
 * Accepts comma-separated or line-break-separated lists.
 *
 * @param string $email_string String containing multiple email addresses
 * @return array Array of valid email addresses
 */
function task_table_parse_email_list( $email_string ) {
	// Replace commas with line breaks to standardize the separator
	$email_string = str_replace( ',', "\n", $email_string );

	// Split the string by line breaks
	$emails = explode( "\n", $email_string );

	// Clean and validate each email
	$valid_emails = array();
	foreach ( $emails as $email ) {
		$email = trim( $email );
		if ( ! empty( $email ) && is_email( $email ) ) {
			$valid_emails[] = sanitize_email( $email );
		}
	}

	return $valid_emails;
}

/**
 * Get all notification emails for new signups
 * This function ensures emails are sent individually to protect recipient privacy
 */
function task_table_get_signup_notification_emails() {
	$emails = array();

	foreach ( task_table_get_signup_notification_recipients() as $recipient ) {
		$email = isset( $recipient['email'] ) ? sanitize_email( $recipient['email'] ) : '';
		if ( is_email( $email ) ) {
			$emails[ strtolower( $email ) ] = $email;
		}
	}

	return array_values( $emails );
}
