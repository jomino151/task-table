<?php
/**
 * Signups screen: approve, deny or delete volunteer signups.
 *
 * @package TaskTable
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Admin signups page
 */
function task_table_admin_signups_page() {
	$notice = task_table_admin_signups_handle_action();
	if ( $notice ) {
		task_table_admin_print_notice( $notice );
	}

	task_table_admin_signups_render( task_table_admin_signups_get_view_data() );
}

/**
 * Handle signup status changes (approve, deny, delete; nonce protected via URL).
 *
 * @return array|null Notice for task_table_admin_print_notice(), or null if nothing was done.
 */
function task_table_admin_signups_handle_action() {
	global $wpdb;
	$signups_table = $wpdb->prefix . 'task_table_signups';
	$events_table  = $wpdb->prefix . 'task_table_events';

	if ( isset( $_GET['action'] ) && isset( $_GET['signup_id'] ) && isset( $_GET['_wpnonce'] ) ) {
		$action    = sanitize_text_field( wp_unslash( $_GET['action'] ) );
		$signup_id = intval( $_GET['signup_id'] );
		$nonce     = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );

		if ( wp_verify_nonce( $nonce, 'task_table_signup_' . $action . '_' . $signup_id ) ) {
			$signup = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE id = %d',
					$signups_table,
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

					return array(
						'type'    => 'success',
						'message' => __( 'Signup approved.', 'task-table' ),
					);
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

					return array(
						'type'    => 'info',
						'message' => __( 'Signup denied.', 'task-table' ),
					);
				} elseif ( $action === 'delete' ) {
					$wpdb->delete( $signups_table, array( 'id' => $signup_id ) );
					return array(
						'type'    => 'info',
						'message' => __( 'Signup deleted.', 'task-table' ),
					);
				}
			}
		}
	}

	return null;
}

/**
 * Read the filters from the request and load the signups and status counts.
 *
 * @return array View data for task_table_admin_signups_render().
 */
function task_table_admin_signups_get_view_data() {
	global $wpdb;
	$signups_table = $wpdb->prefix . 'task_table_signups';
	$events_table  = $wpdb->prefix . 'task_table_events';

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only view state from the URL; the actions check their own nonces.
	$status_filter = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
	$search_term   = isset( $_GET['tt_search'] ) ? sanitize_text_field( wp_unslash( $_GET['tt_search'] ) ) : '';
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

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

	$status_counts_raw = $wpdb->get_results( $wpdb->prepare( 'SELECT status, COUNT(*) AS total FROM %i GROUP BY status', $signups_table ), OBJECT_K );
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
	$reset_url = admin_url( 'admin.php?page=task-table-signups' );

	return compact(
		'status_filter',
		'search_term',
		'signups',
		'status_totals',
		'total_signups',
		'filters_applied',
		'status_labels',
		'base_args',
		'reset_url'
	);
}

/**
 * Render the Signups screen.
 *
 * @param array $view View data from task_table_admin_signups_get_view_data().
 */
function task_table_admin_signups_render( $view ) {
	$status_filter     = $view['status_filter'];
	$search_term       = $view['search_term'];
	$signups           = $view['signups'];
	$status_totals     = $view['status_totals'];
	$total_signups     = $view['total_signups'];
	$filters_applied   = $view['filters_applied'];
	$status_labels     = $view['status_labels'];
	$base_args         = $view['base_args'];
	$reset_url         = $view['reset_url'];
	$render_event_cell = 'task_table_admin_render_event_cell';

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
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=task-table-signups&action=delete&signup_id=' . $signup->id ), 'task_table_signup_delete_' . $signup->id ) ); ?>" class="button is-destructive" data-tt-confirm="<?php echo esc_attr( __( 'Are you sure you want to delete this signup?', 'task-table' ) ); ?>">
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
