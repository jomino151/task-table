<?php
/**
 * Events screen (the plugin's top-level page): archive/restore, filters, stats and the export dialog.
 *
 * @package TaskTable
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Admin dashboard page
 */
function task_table_admin_dashboard_page() {
	$notice = task_table_admin_events_handle_action();
	if ( $notice ) {
		task_table_admin_print_notice( $notice );
	}

	task_table_admin_events_render( task_table_admin_events_get_view_data() );
}

/**
 * Handle archive/unarchive (nonce protected via URL).
 *
 * @return array|null Notice for task_table_admin_print_notice(), or null if nothing was done.
 */
function task_table_admin_events_handle_action() {
	global $wpdb;
	$events_table = $wpdb->prefix . 'task_table_events';

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

			return array(
				'type'    => 'success',
				'message' => $archived_val ? __( 'Event archived.', 'task-table' ) : __( 'Event restored.', 'task-table' ),
			);
		}
	}

	return null;
}

/**
 * Read the filters from the request and load the events and stats for the screen.
 *
 * @return array View data for task_table_admin_events_render().
 */
function task_table_admin_events_get_view_data() {
	global $wpdb;
	$events_table  = $wpdb->prefix . 'task_table_events';
	$signups_table = $wpdb->prefix . 'task_table_signups';

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only view state from the URL; the actions check their own nonces.
	$show_archived     = isset( $_GET['archived'] ) && $_GET['archived'] === '1';
	$categories        = task_table_get_categories();
	$selected_category = isset( $_GET['tt_category'] ) ? (int) $_GET['tt_category'] : 0;
	$search_term       = isset( $_GET['tt_search'] ) ? sanitize_text_field( wp_unslash( $_GET['tt_search'] ) ) : '';
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

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

	return compact(
		'show_archived',
		'categories',
		'selected_category',
		'search_term',
		'events',
		'pending_signups',
		'active_total',
		'archived_total',
		'latest_event_meta',
		'base_admin_url',
		'filters_applied',
		'export_action_url',
		'export_selected_categories',
		'export_default_archived',
		'export_fallback_url'
	);
}

/**
 * Render the Events screen.
 *
 * @param array $view View data from task_table_admin_events_get_view_data().
 */
function task_table_admin_events_render( $view ) {
	$show_archived              = $view['show_archived'];
	$categories                 = $view['categories'];
	$selected_category          = $view['selected_category'];
	$search_term                = $view['search_term'];
	$events                     = $view['events'];
	$pending_signups            = $view['pending_signups'];
	$active_total               = $view['active_total'];
	$archived_total             = $view['archived_total'];
	$latest_event_meta          = $view['latest_event_meta'];
	$base_admin_url             = $view['base_admin_url'];
	$filters_applied            = $view['filters_applied'];
	$export_action_url          = $view['export_action_url'];
	$export_selected_categories = $view['export_selected_categories'];
	$export_default_archived    = $view['export_default_archived'];
	$export_fallback_url        = $view['export_fallback_url'];
	$render_event_cell          = 'task_table_admin_render_event_cell';

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
																				" data-tt-confirm="<?php echo esc_attr( __( 'Archive this event?', 'task-table' ) ); ?>">
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
																				" data-tt-confirm="<?php echo esc_attr( __( 'Restore this event?', 'task-table' ) ); ?>">
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
	<?php
}
