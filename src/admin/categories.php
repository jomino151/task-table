<?php
/**
 * Categories screen: add, edit and delete event categories.
 *
 * @package TaskTable
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Admin categories management page
 */
function task_table_admin_categories_page() {
	$result = task_table_admin_categories_handle_action();

	task_table_admin_categories_render( task_table_admin_categories_get_view_data( $result ) );
}

/**
 * Handle the add, update and delete category forms (POST, each with its own nonce).
 *
 * @return array {
 *     @type string $message Success message, or ''.
 *     @type string $error   Error message, or ''.
 * }
 */
function task_table_admin_categories_handle_action() {
	global $wpdb;

	$categories_table            = $wpdb->prefix . 'task_table_categories';
	$events_table                = $wpdb->prefix . 'task_table_events';
	$subscriber_categories_table = $wpdb->prefix . 'task_table_subscriber_categories';

	$message             = '';
	$error               = '';
	$default_category_id = task_table_get_default_category_id();

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

	return compact( 'message', 'error' );
}

/**
 * Load the categories with their event and subscriber counts, and the category being edited.
 *
 * @param array $result Result of task_table_admin_categories_handle_action().
 * @return array View data for task_table_admin_categories_render().
 */
function task_table_admin_categories_get_view_data( $result ) {
	global $wpdb;

	$events_table                = $wpdb->prefix . 'task_table_events';
	$subscriber_categories_table = $wpdb->prefix . 'task_table_subscriber_categories';

	$categories            = task_table_get_categories();
	$event_counts_raw      = $wpdb->get_results( "SELECT category_id, COUNT(*) AS total FROM $events_table GROUP BY category_id", OBJECT_K );
	$subscriber_counts_raw = array();
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $subscriber_categories_table ) ) === $subscriber_categories_table ) {
		$subscriber_counts_raw = $wpdb->get_results( "SELECT category_id, COUNT(*) AS total FROM $subscriber_categories_table GROUP BY category_id", OBJECT_K );
	}

	$editing_category = null;
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only view state from the URL; the actions check their own nonces.
	if ( isset( $_GET['edit'] ) ) {
		$edit_id          = (int) $_GET['edit'];
		$editing_category = task_table_get_category( $edit_id );
	}
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	$total_events = 0;
	foreach ( $event_counts_raw as $row ) {
		$total_events += (int) $row->total;
	}
	$total_subscribers = 0;
	foreach ( $subscriber_counts_raw as $row ) {
		$total_subscribers += (int) $row->total;
	}

	return array(
		'message'               => $result['message'],
		'error'                 => $result['error'],
		'categories'            => $categories,
		'event_counts_raw'      => $event_counts_raw,
		'subscriber_counts_raw' => $subscriber_counts_raw,
		'editing_category'      => $editing_category,
		'total_events'          => $total_events,
		'total_subscribers'     => $total_subscribers,
	);
}

/**
 * Render the Categories screen.
 *
 * @param array $view View data from task_table_admin_categories_get_view_data().
 */
function task_table_admin_categories_render( $view ) {
	$message               = $view['message'];
	$error                 = $view['error'];
	$categories            = $view['categories'];
	$event_counts_raw      = $view['event_counts_raw'];
	$subscriber_counts_raw = $view['subscriber_counts_raw'];
	$editing_category      = $view['editing_category'];
	$total_events          = $view['total_events'];
	$total_subscribers     = $view['total_subscribers'];

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

		<div class="tt-card-grid">
			<div class="tt-card">
				<h3><?php esc_html_e( 'Categories', 'task-table' ); ?></h3>
				<div class="tt-card-value"><?php echo esc_html( (string) count( $categories ) ); ?></div>
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
								<td><?php echo esc_html( (string) $event_total ); ?></td>
								<td><?php echo esc_html( (string) $subscriber_total ); ?></td>
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
										<form method="post" action="" class="tt-inline-form">
											<?php wp_nonce_field( 'task_table_delete_category', 'task_table_delete_category_nonce' ); ?>
											<input type="hidden" name="category_id" value="<?php echo esc_attr( $category['id'] ); ?>">
											<input type="hidden" name="reassign_category_id" value="<?php echo esc_attr( (string) $reassign_category_id ); ?>">
											<button type="submit" class="button is-destructive" data-tt-confirm="<?php echo esc_attr( __( 'Delete this category? Associated items will be reassigned automatically.', 'task-table' ) ); ?>">
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
