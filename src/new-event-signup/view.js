import { __ } from '@wordpress/i18n';

/**
 * Front-end interactions for the new event signup block.
 */

const initSignupForm = ( container ) => {
	if ( ! container || container.dataset.initialized === 'true' ) {
		return;
	}

	const form = container.querySelector( '.event-notification-form' );
	const emailInput = container.querySelector( '.subscriber-email' );
	const messageEl = container.querySelector( '.subscription-message' );
	const restUrl = container.dataset.restUrl;
	const restNonce = container.dataset.restNonce;

	if ( ! form || ! emailInput || ! messageEl || ! restUrl ) {
		container.dataset.initialized = 'true';
		return;
	}

	const submitButton = container.querySelector( '.subscription-button' );
	const categoryInputs = Array.from(
		container.querySelectorAll( '.subscriber-category' )
	);

	const resetMessage = () => {
		messageEl.classList.remove( 'is-visible', 'is-success', 'is-error' );
		messageEl.textContent = '';
	};

	const showMessage = ( text, type ) => {
		messageEl.textContent = text;
		messageEl.classList.add( 'is-visible' );
		messageEl.classList.toggle( 'is-success', type === 'success' );
		messageEl.classList.toggle( 'is-error', type === 'error' );
	};

	const getSelectedCategories = () =>
		categoryInputs
			.filter( ( input ) => input.checked )
			.map( ( input ) => parseInt( input.value, 10 ) )
			.filter( ( value ) => ! Number.isNaN( value ) );

	form.addEventListener( 'submit', async ( event ) => {
		event.preventDefault();

		if ( ! emailInput.value.trim() ) {
			showMessage(
				__( 'Please enter a valid email address.', 'task-table' ),
				'error'
			);
			return;
		}

		const selectedCategories = getSelectedCategories();
		if ( categoryInputs.length > 0 && selectedCategories.length === 0 ) {
			showMessage(
				__( 'Please choose at least one category.', 'task-table' ),
				'error'
			);
			return;
		}

		submitButton?.setAttribute( 'disabled', 'disabled' );
		submitButton?.classList.add( 'is-busy' );
		resetMessage();

		try {
			const response = await fetch( restUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': restNonce || '',
				},
				body: JSON.stringify( {
					email: emailInput.value.trim(),
					categories: selectedCategories,
				} ),
			} );

			const data = await response.json();

			if ( response.ok && data?.success ) {
				showMessage(
					__(
						'Please check your inbox and confirm your subscription with the link in our email.',
						'task-table'
					),
					'success'
				);
				emailInput.value = '';
			} else {
				showMessage(
					data?.message ||
						__(
							'Something went wrong. Please try again later.',
							'task-table'
						),
					'error'
				);
			}
		} catch ( error ) {
			// eslint-disable-next-line no-console -- keep the cause visible when the request fails
			console.error( 'Task Table subscription error:', error );
			showMessage(
				__(
					'Something went wrong. Please try again later.',
					'task-table'
				),
				'error'
			);
		} finally {
			submitButton?.removeAttribute( 'disabled' );
			submitButton?.classList.remove( 'is-busy' );

			setTimeout( () => {
				resetMessage();
			}, 5000 );
		}
	} );

	container.dataset.initialized = 'true';
};

const bootstrapSignupBlocks = () => {
	const containers = document.querySelectorAll(
		'.wp-block-task-table-new-event-signup'
	);
	containers.forEach( initSignupForm );
};

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', bootstrapSignupBlocks );
} else {
	bootstrapSignupBlocks();
}

// Support for Ajax-rendered content (e.g., when block is inserted dynamically)
document.addEventListener( 'task-table:new-event-signup:init', ( event ) => {
	const { target } = event;
	if ( target instanceof window.HTMLElement ) {
		initSignupForm( target );
	}
} );
