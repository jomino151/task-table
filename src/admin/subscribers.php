<?php
/**
 * Subscribers screen: list, search, add, delete, resend confirmation, edit categories.
 *
 * @package TaskTable
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Admin subscribers page
 */
function task_table_admin_subscribers_page() {
	$result = task_table_admin_subscribers_handle_action();
	foreach ( $result['notices'] as $notice ) {
		task_table_admin_print_notice( $notice );
	}

	task_table_admin_subscribers_render( task_table_admin_subscribers_get_view_data( $result ) );
}

/**
 * Handle the subscriber actions: update categories (POST), delete and resend (GET, nonce in URL)
 * and add a subscriber (POST).
 *
 * @return array {
 *     @type array  $notices                Notices for task_table_admin_print_notice().
 *     @type string $category_update_notice Inline notice after saving a subscriber's categories.
 *     @type bool   $subscriber_added       Whether a new subscriber was added.
 * }
 */
function task_table_admin_subscribers_handle_action() {
	global $wpdb;
	$subscribers_table           = $wpdb->prefix . 'task_table_subscribers';
	$subscriber_categories_table = $wpdb->prefix . 'task_table_subscriber_categories';
	$category_update_notice      = '';
	$notices                     = array();
	$subscriber_added            = false;

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
			$notices[] = array(
				'type'    => 'success',
				'message' => __( 'Subscriber deleted.', 'task-table' ),
			);
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
				$notices[] = array(
					'type'    => 'success',
					'message' => __( 'Confirmation email resent.', 'task-table' ),
				);
			}
		}
	}
	// Handle the "Add New Subscriber" form
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
					$notices[] = array(
						'type'    => 'error',
						'message' => __( 'This email is already subscribed.', 'task-table' ),
					);
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

					$notices[] = array(
						'type'    => 'success',
						'message' => __( 'Subscriber added successfully.', 'task-table' ),
					);

					$subscriber_added = true;
				}
			} else {
				$notices[] = array(
					'type'    => 'error',
					'message' => __( 'Please enter a valid email address.', 'task-table' ),
				);
			}
		}
	}

	return compact( 'notices', 'category_update_notice', 'subscriber_added' );
}

/**
 * Read the search from the request and load the subscribers, stats and the subscriber being edited.
 *
 * @param array $result Result of task_table_admin_subscribers_handle_action().
 * @return array View data for task_table_admin_subscribers_render().
 */
function task_table_admin_subscribers_get_view_data( $result ) {
	global $wpdb;
	$subscribers_table      = $wpdb->prefix . 'task_table_subscribers';
	$categories             = task_table_get_categories();
	$default_category_id    = task_table_get_default_category_id();
	$category_update_notice = $result['category_update_notice'];

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view state from the URL; the actions check their own nonces.
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
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only view state from the URL.
	if ( isset( $_GET['action'] ) && $_GET['action'] === 'edit-categories' && isset( $_GET['subscriber_id'] ) ) {
		$editing_id         = (int) $_GET['subscriber_id'];
		$editing_subscriber = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $subscribers_table WHERE id = %d", $editing_id ) );
		if ( $editing_subscriber ) {
			$editing_categories = task_table_get_subscriber_categories( $editing_subscriber->id );
		}
	}
	$edit_requested = isset( $_GET['action'] ) && $_GET['action'] === 'edit-categories';
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	// After a successful add the form starts over with the default category, as on a fresh load.
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- only re-checks the boxes the user just submitted; nothing is stored.
	$preselected = ! $result['subscriber_added'] && isset( $_POST['subscriber_categories'] ) ? array_map( 'intval', (array) $_POST['subscriber_categories'] ) : array();
	$preselected = ! empty( $preselected ) ? $preselected : array( $default_category_id );

	return compact(
		'categories',
		'category_update_notice',
		'search_term',
		'subscribers',
		'total_subscribers',
		'confirmed_subscribers',
		'awaiting_confirmation',
		'last_subscriber_date',
		'filters_applied',
		'editing_subscriber',
		'editing_categories',
		'edit_requested',
		'preselected'
	);
}

/**
 * Render the Subscribers screen.
 *
 * @param array $view View data from task_table_admin_subscribers_get_view_data().
 */
function task_table_admin_subscribers_render( $view ) {
	$categories             = $view['categories'];
	$category_update_notice = $view['category_update_notice'];
	$search_term            = $view['search_term'];
	$subscribers            = $view['subscribers'];
	$total_subscribers      = $view['total_subscribers'];
	$confirmed_subscribers  = $view['confirmed_subscribers'];
	$awaiting_confirmation  = $view['awaiting_confirmation'];
	$last_subscriber_date   = $view['last_subscriber_date'];
	$filters_applied        = $view['filters_applied'];
	$editing_subscriber     = $view['editing_subscriber'];
	$editing_categories     = $view['editing_categories'];
	$edit_requested         = $view['edit_requested'];
	$preselected            = $view['preselected'];

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
			<?php if ( $edit_requested && ! $editing_subscriber ) : ?>
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
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=task-table-subscribers&action=delete&subscriber_id=' . $subscriber->id ), 'task_table_delete_subscriber_' . $subscriber->id ) ); ?>" class="button is-destructive" data-tt-confirm="<?php echo esc_attr( __( 'Are you sure you want to delete this subscriber?', 'task-table' ) ); ?>">
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
						<?php if ( ! empty( $categories ) ) : ?>
							<?php foreach ( $categories as $category ) : ?>
								<label class="tt-category-option">
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
}
