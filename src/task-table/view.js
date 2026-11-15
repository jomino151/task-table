/**
 * Front-end script for the task-table block: the signup dialog.
 *
 * render.php prints the dialog once per page. Its data- attributes carry the REST URL,
 * the nonce and the translated messages, so this file needs no inline globals.
 */

const initSignupDialog = () => {
	const dialog = document.getElementById( 'signup-modal' );
	if ( ! dialog || dialog.dataset.initialized === 'true' ) {
		return;
	}
	dialog.dataset.initialized = 'true';

	const form = dialog.querySelector( '#signup-form' );
	const submitBtn = dialog.querySelector( '#submit-signup' );
	const feedbackEl = dialog.querySelector( '#signup-feedback' );
	const freeTextArea = dialog.querySelector( '#free-text' );
	const eventIdInput = dialog.querySelector( '#event-id' );
	const replacementFlag = dialog.querySelector( '#replacement-signup-flag' );
	const disclaimer = dialog.querySelector( '#replacement-disclaimer' );
	const { restUrl, restNonce } = dialog.dataset;
	let i18n = {};
	try {
		i18n = JSON.parse( dialog.dataset.i18n || '{}' );
	} catch {
		i18n = {};
	}

	let trigger = null;
	let feedbackTimer = null;

	const autoResize = () => {
		freeTextArea.style.height = 'auto';
		freeTextArea.style.height = freeTextArea.scrollHeight + 'px';
	};
	if ( freeTextArea ) {
		freeTextArea.addEventListener( 'input', autoResize );
	}

	const showFeedback = ( message, type ) => {
		feedbackEl.textContent = message;
		feedbackEl.className = 'signup-feedback ' + type + ' show';
		clearTimeout( feedbackTimer );
		feedbackTimer = setTimeout(
			() => feedbackEl.classList.remove( 'show' ),
			5000
		);
	};

	const setLoading = ( loading ) => {
		submitBtn.classList.toggle( 'loading', loading );
		submitBtn.disabled = loading;
		submitBtn.setAttribute( 'aria-busy', loading ? 'true' : 'false' );
	};

	const openDialog = ( button ) => {
		trigger = button;
		form.reset();
		feedbackEl.className = 'signup-feedback';
		feedbackEl.textContent = '';
		eventIdInput.value = button.dataset.eventId;

		const hasApproved = button.dataset.hasApproved === '1';
		disclaimer.hidden = ! hasApproved;
		replacementFlag.value = hasApproved ? '1' : '';

		dialog.showModal();
		form.elements.user_name.focus();
		document.body.style.overflow = 'hidden';
		if ( freeTextArea ) {
			autoResize();
		}
	};

	// Native <dialog> traps focus and closes on Escape; on close, hand focus back.
	dialog.addEventListener( 'close', () => {
		document.body.style.overflow = '';
		if ( trigger && document.contains( trigger ) ) {
			trigger.focus();
		}
	} );

	// A click on the backdrop lands on the <dialog> element itself.
	dialog.addEventListener( 'click', ( e ) => {
		if ( e.target === dialog ) {
			dialog.close();
		}
	} );

	dialog
		.querySelectorAll( '[data-close-dialog]' )
		.forEach( ( btn ) =>
			btn.addEventListener( 'click', () => dialog.close() )
		);

	document.querySelectorAll( '.event-signup' ).forEach( ( button ) =>
		button.addEventListener( 'click', ( e ) => {
			e.preventDefault();
			openDialog( button );
		} )
	);

	form.addEventListener( 'submit', ( e ) => {
		e.preventDefault();

		const userName = form.elements.user_name.value;
		const userEmail = form.elements.user_email.value;
		let freeText = freeTextArea ? freeTextArea.value : '';
		if ( replacementFlag.value === '1' && i18n.backup ) {
			if ( freeText.indexOf( i18n.backup ) !== 0 ) {
				freeText = i18n.backup + ' ' + freeText;
			}
		}

		if ( ! userName.trim() || ! userEmail.trim() ) {
			showFeedback( i18n.fillAll, 'error' );
			return;
		}

		setLoading( true );

		fetch( restUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': restNonce,
			},
			body: JSON.stringify( {
				event_id: eventIdInput.value,
				user_name: userName,
				user_email: userEmail,
				free_text: freeText,
			} ),
		} )
			.then( ( response ) => response.json() )
			.then( ( data ) => {
				setLoading( false );
				if ( data.success ) {
					showFeedback( i18n.success, 'success' );
					setTimeout( () => {
						dialog.close();
						window.location.reload();
					}, 2000 );
				} else {
					showFeedback( data.message || i18n.error, 'error' );
				}
			} )
			.catch( () => {
				setLoading( false );
				showFeedback( i18n.error, 'error' );
			} );
	} );
};

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', initSignupDialog );
} else {
	initSignupDialog();
}
