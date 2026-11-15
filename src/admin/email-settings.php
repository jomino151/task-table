<?php
/**
 * Email Settings screen: signup notification recipients and the email templates.
 *
 * The recipient editor is built by admin/admin.js from the hidden JSON field and the data passed
 * in task_table_admin_email_settings_script_data().
 *
 * @package TaskTable
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Admin email settings page
 */
function task_table_admin_email_settings_page() {
	$notice = task_table_admin_email_settings_handle_save();
	if ( $notice ) {
		task_table_admin_print_notice( $notice );
	}

	task_table_admin_email_settings_render( task_table_admin_email_settings_get_view_data() );
}

/**
 * Save the templates and recipients when the settings form is posted.
 *
 * @return array|null Notice for task_table_admin_print_notice(), or null if nothing was saved.
 */
function task_table_admin_email_settings_handle_save() {
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

		return array(
			'type'    => 'success',
			'message' => __( 'Email settings saved.', 'task-table' ),
		);
	}

	return null;
}

/**
 * Labels for the email template tabs.
 *
 * @return array Template key => label.
 */
function task_table_admin_email_template_labels() {
	return array(
		'subscription_confirmation' => __( 'Subscription confirmation', 'task-table' ),
		'subscription_manage'       => __( 'Manage subscription (existing subscription)', 'task-table' ),
		'signup_approved'           => __( 'Signup approved', 'task-table' ),
		'signup_denied'             => __( 'Signup denied', 'task-table' ),
		'new_signup'                => __( 'New signup', 'task-table' ),
		'new_event'                 => __( 'New event', 'task-table' ),
	);
}

/**
 * Load the templates, categories and current recipients.
 *
 * @return array View data for task_table_admin_email_settings_render().
 */
function task_table_admin_email_settings_get_view_data() {
	$templates       = task_table_get_email_templates();
	$template_labels = task_table_admin_email_template_labels();

	$categories             = task_table_get_categories();
	$signup_recipients      = task_table_get_signup_notification_recipients();
	$signup_recipients_json = wp_json_encode( $signup_recipients );
	if ( ! is_string( $signup_recipients_json ) ) {
		$signup_recipients_json = '[]';
	}

	return compact( 'templates', 'template_labels', 'categories', 'signup_recipients_json' );
}

/**
 * Data for the recipient editor in admin/admin.js (passed with wp_localize_script).
 *
 * @return array Categories (id, name) and the editor's UI strings.
 */
function task_table_admin_email_settings_script_data() {
	return array(
		'categories' => array_map(
			static function ( $category ) {
				return array(
					'id'   => (int) $category['id'],
					'name' => $category['name'],
				);
			},
			task_table_get_categories()
		),
		'i18n'       => array(
			'noRecipients'   => __( 'No recipients yet.', 'task-table' ),
			'allCategories'  => __( 'All categories', 'task-table' ),
			'remove'         => __( 'Remove', 'task-table' ),
			'invalidEmail'   => __( 'Please enter a valid email address.', 'task-table' ),
			'duplicateEmail' => __( 'This email address has already been added.', 'task-table' ),
			'chooseCategory' => __( 'Please choose at least one category or enable "All categories".', 'task-table' ),
		),
	);
}

/**
 * Render the Email Settings screen.
 *
 * @param array $view View data from task_table_admin_email_settings_get_view_data().
 */
function task_table_admin_email_settings_render( $view ) {
	$templates              = $view['templates'];
	$template_labels        = $view['template_labels'];
	$categories             = $view['categories'];
	$signup_recipients_json = $view['signup_recipients_json'];

	?>
	<div class="wrap task-table-admin">
		<div class="tt-page-header">
			<div class="tt-page-title">
				<h1><?php esc_html_e( 'Email Settings', 'task-table' ); ?></h1>
				<p><?php esc_html_e( 'Adjust notifications and automatic replies so every message reaches the right person in the right tone.', 'task-table' ); ?></p>
			</div>
		</div>

		<form method="post" action="" class="tt-form-card">
			<?php wp_nonce_field( 'task_table_email_settings', 'task_table_email_settings_nonce' ); ?>

			<div class="tt-toolbar tt-toolbar--start">
				<h2 class="tt-section-label">
					<?php esc_html_e( 'Notification recipients', 'task-table' ); ?>
				</h2>
			</div>

			<div class="email-recipients-container">
				<div id="signup-recipient-list" class="tt-recipient-list"></div>

				<div class="tt-form-card tt-recipient-add">
					<h3><?php esc_html_e( 'Add recipient', 'task-table' ); ?></h3>

					<div class="email-input-container">
						<input type="email" id="new_email" placeholder="<?php esc_attr_e( 'Add a new email address', 'task-table' ); ?>" class="regular-text">
						<button type="button" id="add_signup_recipient" class="button button-secondary">
							<span class="dashicons dashicons-plus-alt"></span>
							<?php esc_html_e( 'Add', 'task-table' ); ?>
						</button>
					</div>

					<label class="tt-recipient-all">
						<input type="checkbox" id="recipient_all_categories" checked>
						<?php esc_html_e( 'All categories', 'task-table' ); ?>
					</label>

					<div id="recipient-category-picker" class="tt-recipient-categories">
						<?php if ( ! empty( $categories ) ) : ?>
							<?php foreach ( $categories as $category ) : ?>
								<label>
									<input type="checkbox" class="recipient-category" value="<?php echo esc_attr( (int) $category['id'] ); ?>">
									<?php echo esc_html( $category['name'] ); ?>
								</label>
							<?php endforeach; ?>
						<?php else : ?>
							<span class="description"><?php esc_html_e( 'No categories available.', 'task-table' ); ?></span>
						<?php endif; ?>
					</div>
				</div>

				<input type="hidden" name="signup_notification_recipients" id="signup_notification_recipients" value="<?php echo esc_attr( $signup_recipients_json ); ?>">
				<input type="hidden" name="signup_notification_recipients_b64" id="signup_notification_recipients_b64" value="">
				<input type="hidden" name="moderator_emails" id="moderator_emails" value="">

				<p class="description tt-recipient-help">
					<?php esc_html_e( 'Choose for each address whether it receives all new signups or only signups in certain categories.', 'task-table' ); ?>
				</p>
			</div>

			<h2 class="tt-templates-heading">
				<?php esc_html_e( 'Email templates', 'task-table' ); ?>
			</h2>

			<div class="email-templates-tabs tt-pill-toggle">
				<a href="#general-settings" class="nav-tab is-active" data-tab="general-settings">
					<?php esc_html_e( 'Placeholders', 'task-table' ); ?>
				</a>
				<?php foreach ( array_keys( $templates ) as $tab_key ) : ?>
					<?php $tab_label = isset( $template_labels[ $tab_key ] ) ? $template_labels[ $tab_key ] : ucwords( str_replace( '_', ' ', $tab_key ) ); ?>
					<a href="#template-<?php echo esc_attr( $tab_key ); ?>" class="nav-tab" data-tab="template-<?php echo esc_attr( $tab_key ); ?>">
						<?php echo esc_html( $tab_label ); ?>
					</a>
				<?php endforeach; ?>
			</div>

			<div id="general-settings" class="email-template-tab" style="display: block;">
				<p class="tt-placeholders-intro">
					<?php esc_html_e( 'Use these placeholders in any template to personalise messages. They are replaced with the matching values when the email is sent.', 'task-table' ); ?>
				</p>
				<ul class="task-table-placeholders">
					<li><code>{site_name}</code> - <?php esc_html_e( 'Site name', 'task-table' ); ?></li>
					<li><code>{user_name}</code> - <?php esc_html_e( 'Person\'s name (if available)', 'task-table' ); ?></li>
					<li><code>{user_email}</code> - <?php esc_html_e( 'Person\'s email address (if available)', 'task-table' ); ?></li>
					<li><code>{event_title}</code> - <?php esc_html_e( 'Event title (if available)', 'task-table' ); ?></li>
					<li><code>{event_details}</code> - <?php esc_html_e( 'Event details (if available)', 'task-table' ); ?></li>
					<li><code>{event_id}</code> - <?php esc_html_e( 'Event ID (if available)', 'task-table' ); ?></li>
					<li><code>{event_category_line}</code> - <?php esc_html_e( 'Category as a line of text (new signup only)', 'task-table' ); ?></li>
					<li><code>{confirmation_link}</code> - <?php esc_html_e( 'Confirmation link (subscription emails only)', 'task-table' ); ?></li>
					<li><code>{manage_link}</code> - <?php esc_html_e( 'Manage/unsubscribe link (new event, manage subscription; appended automatically otherwise)', 'task-table' ); ?></li>
				</ul>
			</div>

			<?php foreach ( $templates as $template_key => $template ) : ?>
				<div id="template-<?php echo esc_attr( $template_key ); ?>" class="email-template-tab" style="display: none;">
					<?php $template_title = isset( $template_labels[ $template_key ] ) ? $template_labels[ $template_key ] : ucwords( str_replace( '_', ' ', $template_key ) ); ?>
					<h3><?php echo esc_html( $template_title ); ?> <?php esc_html_e( 'Template', 'task-table' ); ?></h3>
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="task_table_email_<?php echo esc_attr( $template_key ); ?>_subject"><?php esc_html_e( 'Subject', 'task-table' ); ?></label>
							</th>
							<td>
								<input type="text" name="task_table_email_<?php echo esc_attr( $template_key ); ?>_subject" id="task_table_email_<?php echo esc_attr( $template_key ); ?>_subject" class="large-text" value="<?php echo esc_attr( get_option( "task_table_email_{$template_key}_subject", $template['subject'] ) ); ?>">
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="task_table_email_<?php echo esc_attr( $template_key ); ?>_message"><?php esc_html_e( 'Message', 'task-table' ); ?></label>
							</th>
							<td>
								<textarea name="task_table_email_<?php echo esc_attr( $template_key ); ?>_message" id="task_table_email_<?php echo esc_attr( $template_key ); ?>_message" class="large-text" rows="10"><?php echo esc_textarea( get_option( "task_table_email_{$template_key}_message", $template['message'] ) ); ?></textarea>
							</td>
						</tr>
					</table>
				</div>
			<?php endforeach; ?>

			<p class="submit">
				<input type="submit" name="submit" id="submit" class="button button-primary" value="<?php esc_attr_e( 'Save changes', 'task-table' ); ?>">
			</p>
		</form>
	</div>
	<?php
}
