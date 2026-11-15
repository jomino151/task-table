<?php
/**
 * Render callback for the new event signup (notification) block.
 *
 * @package TaskTable
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rest_url   = esc_url_raw( rest_url( 'task-table/v1/subscribers' ) );
$rest_nonce = wp_create_nonce( 'wp_rest' );

$display_categories = task_table_get_categories();

ob_start();
?>
<div
	class="wp-block-task-table-new-event-signup"
	data-rest-url="<?php echo esc_attr( $rest_url ); ?>"
	data-rest-nonce="<?php echo esc_attr( $rest_nonce ); ?>"
>
	<h3><?php esc_html_e( 'Subscribe to new events', 'task-table' ); ?></h3>
	<p><?php esc_html_e( 'Get an email when new events are added.', 'task-table' ); ?></p>
	<form class="event-notification-form">
		<input type="email" class="subscriber-email" placeholder="<?php esc_attr_e( 'Your email address', 'task-table' ); ?>" aria-label="<?php esc_attr_e( 'Your email address', 'task-table' ); ?>" required>
		<?php if ( ! empty( $display_categories ) ) : ?>
			<fieldset class="subscription-categories">
				<legend><?php esc_html_e( 'Which categories do you want to hear about?', 'task-table' ); ?></legend>
				<?php
				foreach ( $display_categories as $category ) :
					$category_id   = (int) $category['id'];
					$category_name = $category['name'];
					$input_id      = 'subscription-category-' . $category_id;
					?>
					<label for="<?php echo esc_attr( $input_id ); ?>" class="subscription-category">
						<input type="checkbox" class="subscriber-category" id="<?php echo esc_attr( $input_id ); ?>" value="<?php echo esc_attr( $category_id ); ?>">
						<span><?php echo esc_html( $category_name ); ?></span>
					</label>
				<?php endforeach; ?>
				<p class="subscription-hint"><?php esc_html_e( 'You can choose several categories. At least one is required.', 'task-table' ); ?></p>
			</fieldset>
		<?php else : ?>
			<p class="subscription-categories-empty"><?php esc_html_e( 'There are no categories to subscribe to right now. Please try again later or contact the team.', 'task-table' ); ?></p>
		<?php endif; ?>
		<button type="submit" class="subscription-button"><?php esc_html_e( 'Subscribe', 'task-table' ); ?></button>
	</form>
	<div class="subscription-message" role="status" aria-live="polite"></div>
</div>
<?php
return ob_get_clean();
