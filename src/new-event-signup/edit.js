import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

const Edit = () => {
	return (
		<div { ...useBlockProps( { className: 'new-event-signup-editor' } ) }>
			<h3>{ __( 'Subscribe to new events', 'task-table' ) }</h3>
			<p>
				{ __(
					'This form appears on the front end so visitors can subscribe to notifications.',
					'task-table'
				) }
			</p>
		</div>
	);
};

export default Edit;
