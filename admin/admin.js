/**
 * Admin JavaScript for the Task Table plugin, loaded on the plugin's own admin screens.
 */
/* global jQuery, taskTableAdminEmail */

/**
 * Confirm dialogs for destructive links and buttons: data-tt-confirm="Question?".
 */
document.addEventListener( 'click', function ( event ) {
	const trigger = event.target.closest( '[data-tt-confirm]' );
	// eslint-disable-next-line no-alert -- a native confirm guards destructive row actions
	if ( trigger && ! window.confirm( trigger.dataset.ttConfirm ) ) {
		event.preventDefault();
	}
} );

/**
 * Events screen: the "Download Excel" export dialog.
 */
( function () {
	const modal = document.getElementById( 'tt-export-modal' );
	const openButton = document.getElementById( 'tt-export-open' );
	if ( ! modal || ! openButton ) {
		return;
	}

	const body = document.body;
	const overlay = modal.querySelector( '.tt-export-modal__overlay' );
	const closeElements = modal.querySelectorAll( '[data-tt-export-close]' );
	const form = modal.querySelector( 'form' );
	const focusableSelector =
		'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
	let lastFocusedElement = null;

	function getFocusableElements() {
		return Array.from( modal.querySelectorAll( focusableSelector ) ).filter(
			function ( el ) {
				return (
					el.offsetParent !== null && ! el.hasAttribute( 'disabled' )
				);
			}
		);
	}

	function handleKeydown( event ) {
		if ( ! modal.classList.contains( 'is-open' ) ) {
			return;
		}

		if ( event.key === 'Escape' ) {
			event.preventDefault();
			closeModal();
			return;
		}

		if ( event.key === 'Tab' ) {
			const focusableElements = getFocusableElements();
			if ( ! focusableElements.length ) {
				event.preventDefault();
				return;
			}
			const first = focusableElements[ 0 ];
			const last = focusableElements[ focusableElements.length - 1 ];
			if ( event.shiftKey ) {
				if (
					modal.ownerDocument.activeElement === first ||
					! modal.contains( modal.ownerDocument.activeElement )
				) {
					event.preventDefault();
					last.focus();
				}
			} else if ( modal.ownerDocument.activeElement === last ) {
				event.preventDefault();
				first.focus();
			}
		}
	}

	function openModal() {
		lastFocusedElement = modal.ownerDocument.activeElement;
		modal.classList.add( 'is-open' );
		modal.setAttribute( 'aria-hidden', 'false' );
		body.classList.add( 'tt-export-modal-open' );
		const focusableElements = getFocusableElements();
		if ( focusableElements.length ) {
			focusableElements[ 0 ].focus();
		}
		document.addEventListener( 'keydown', handleKeydown );
	}

	function closeModal() {
		modal.classList.remove( 'is-open' );
		modal.setAttribute( 'aria-hidden', 'true' );
		body.classList.remove( 'tt-export-modal-open' );
		document.removeEventListener( 'keydown', handleKeydown );
		if (
			lastFocusedElement &&
			typeof lastFocusedElement.focus === 'function'
		) {
			lastFocusedElement.focus();
		} else {
			openButton.focus();
		}
	}

	openButton.addEventListener( 'click', function () {
		openModal();
	} );

	if ( overlay ) {
		overlay.addEventListener( 'click', closeModal );
	}

	closeElements.forEach( function ( element ) {
		element.addEventListener( 'click', function () {
			closeModal();
		} );
	} );

	if ( form ) {
		form.addEventListener( 'submit', function () {
			setTimeout( closeModal, 150 );
		} );
	}
} )();

/**
 * Email Settings screen: template tabs.
 */
document.addEventListener( 'DOMContentLoaded', function () {
	const tabLinks = document.querySelectorAll(
		'.email-templates-tabs .nav-tab'
	);

	tabLinks.forEach( ( link ) => {
		link.addEventListener( 'click', function ( e ) {
			e.preventDefault();

			const targetTab = document.querySelector(
				this.getAttribute( 'href' )
			);

			document
				.querySelectorAll( '.email-template-tab' )
				.forEach( ( tab ) => {
					tab.style.display = 'none';
				} );
			document.querySelectorAll( '.nav-tab' ).forEach( ( tab ) => {
				tab.classList.remove( 'nav-tab-active' );
			} );
			tabLinks.forEach( ( tab ) => {
				tab.classList.remove( 'is-active' );
			} );

			if ( targetTab ) {
				targetTab.style.display = 'block';
			}
			this.classList.add( 'nav-tab-active', 'is-active' );
		} );
	} );
} );

/**
 * Email Settings screen: the signup notification recipient editor. The hidden inputs carry the
 * recipients as JSON, as base64-encoded JSON and as a legacy comma-separated list.
 */
jQuery( function ( $ ) {
	const recipientList = $( '#signup-recipient-list' );
	if (
		! recipientList.length ||
		typeof taskTableAdminEmail === 'undefined'
	) {
		return;
	}

	const categoryMap = taskTableAdminEmail.categories;
	const i18n = taskTableAdminEmail.i18n;
	const recipientsInput = $( '#signup_notification_recipients' );
	const recipientsB64Input = $( '#signup_notification_recipients_b64' );
	const moderatorEmailsInput = $( '#moderator_emails' );
	const categoryCheckboxes = $( '.recipient-category' );
	const allCategoriesToggle = $( '#recipient_all_categories' );
	let recipients = [];

	try {
		recipients = JSON.parse( recipientsInput.val() || '[]' );
	} catch {
		recipients = [];
	}

	function normalizeRecipients( rawRecipients ) {
		const byEmail = {};

		if ( ! Array.isArray( rawRecipients ) ) {
			return [];
		}

		rawRecipients.forEach( function ( recipient ) {
			if ( ! recipient || typeof recipient !== 'object' ) {
				return;
			}

			const email = String( recipient.email || '' ).trim();
			if ( ! email.match( /^[^\s@]+@[^\s@]+\.[^\s@]+$/ ) ) {
				return;
			}

			const categoryIds = [];
			if ( Array.isArray( recipient.category_ids ) ) {
				recipient.category_ids.forEach( function ( rawId ) {
					const id = parseInt( rawId, 10 );
					if ( Number.isNaN( id ) || id <= 0 ) {
						return;
					}

					const exists = categoryMap.some( function ( category ) {
						return parseInt( category.id, 10 ) === id;
					} );

					if ( ! exists || categoryIds.indexOf( id ) !== -1 ) {
						return;
					}

					categoryIds.push( id );
				} );
			}

			byEmail[ email.toLowerCase() ] = {
				email,
				category_ids: categoryIds,
			};
		} );

		return Object.keys( byEmail ).map( function ( key ) {
			return byEmail[ key ];
		} );
	}

	function encodeBase64Unicode( value ) {
		try {
			// Encode UTF-8 bytes so btoa() accepts non-Latin-1 characters.
			const bytes = new TextEncoder().encode( String( value ) );
			let binary = '';
			bytes.forEach( function ( byte ) {
				binary += String.fromCharCode( byte );
			} );
			return window.btoa( binary );
		} catch {
			return '';
		}
	}

	function getSelectedCategoryIds() {
		const selected = [];
		categoryCheckboxes.filter( ':checked' ).each( function () {
			const id = parseInt( $( this ).val(), 10 );
			if (
				! Number.isNaN( id ) &&
				id > 0 &&
				selected.indexOf( id ) === -1
			) {
				selected.push( id );
			}
		} );
		return selected;
	}

	function updateRecipientsInput() {
		recipients = normalizeRecipients( recipients );

		const jsonPayload = JSON.stringify( recipients );
		const emailsPayload = recipients
			.map( function ( recipient ) {
				return recipient.email;
			} )
			.join( ',' );

		recipientsInput.val( jsonPayload );
		recipientsB64Input.val( encodeBase64Unicode( jsonPayload ) );
		moderatorEmailsInput.val( emailsPayload );
	}

	function getCategoryName( categoryId ) {
		const match = categoryMap.find( function ( category ) {
			return parseInt( category.id, 10 ) === parseInt( categoryId, 10 );
		} );

		return match ? String( match.name ) : '';
	}

	function renderRecipients() {
		recipientList.empty();

		if ( ! recipients.length ) {
			recipientList.append(
				$( '<p class="description"></p>' ).text( i18n.noRecipients )
			);
			return;
		}

		recipients.forEach( function ( recipient ) {
			const row = $( '<div class="tt-recipient-row"></div>' );

			const info = $( '<div></div>' );
			info.append( $( '<strong></strong>' ).text( recipient.email ) );

			const categoryWrap = $(
				'<div class="tt-recipient-row__categories"></div>'
			);

			if ( ! recipient.category_ids.length ) {
				categoryWrap.append(
					$( '<span class="tt-pill is-active"></span>' ).text(
						i18n.allCategories
					)
				);
			} else {
				recipient.category_ids.forEach( function ( categoryId ) {
					const categoryName = getCategoryName( categoryId );
					if ( ! categoryName ) {
						return;
					}
					categoryWrap.append(
						$(
							'<span class="tt-pill is-unconfirmed"></span>'
						).text( categoryName )
					);
				} );
			}

			info.append( categoryWrap );

			const removeButton = $(
				'<button type="button" class="button button-secondary remove-signup-recipient"></button>'
			)
				.attr( 'data-email', recipient.email )
				.text( i18n.remove );

			row.append( info ).append( removeButton );
			recipientList.append( row );
		} );
	}

	function syncCategoryInputState() {
		const disableCategories = allCategoriesToggle.is( ':checked' );
		categoryCheckboxes.prop( 'disabled', disableCategories );
		if ( disableCategories ) {
			categoryCheckboxes.prop( 'checked', false );
		}
	}

	$( '#add_signup_recipient' ).on( 'click', function () {
		const email = $( '#new_email' ).val().trim();
		if ( ! email ) {
			return;
		}

		if ( ! email.match( /^[^\s@]+@[^\s@]+\.[^\s@]+$/ ) ) {
			// eslint-disable-next-line no-alert -- inline validation message for the recipient editor
			window.alert( i18n.invalidEmail );
			return;
		}

		recipients = normalizeRecipients( recipients );
		const isDuplicate = recipients.some( function ( recipient ) {
			return recipient.email.toLowerCase() === email.toLowerCase();
		} );

		if ( isDuplicate ) {
			// eslint-disable-next-line no-alert -- inline validation message for the recipient editor
			window.alert( i18n.duplicateEmail );
			return;
		}

		const allCategories = allCategoriesToggle.is( ':checked' );
		const selectedCategories = allCategories
			? []
			: getSelectedCategoryIds();

		if ( ! allCategories && ! selectedCategories.length ) {
			// eslint-disable-next-line no-alert -- inline validation message for the recipient editor
			window.alert( i18n.chooseCategory );
			return;
		}

		recipients.push( {
			email,
			category_ids: selectedCategories,
		} );

		recipients = normalizeRecipients( recipients );
		renderRecipients();
		updateRecipientsInput();

		$( '#new_email' ).val( '' ).trigger( 'focus' );
		allCategoriesToggle.prop( 'checked', true );
		syncCategoryInputState();
	} );

	$( '#new_email' ).on( 'keypress', function ( e ) {
		if ( e.which === 13 ) {
			e.preventDefault();
			$( '#add_signup_recipient' ).trigger( 'click' );
		}
	} );

	allCategoriesToggle.on( 'change', syncCategoryInputState );

	$( document ).on( 'click', '.remove-signup-recipient', function () {
		const email = String( $( this ).data( 'email' ) || '' ).toLowerCase();
		recipients = recipients.filter( function ( recipient ) {
			return recipient.email.toLowerCase() !== email;
		} );
		renderRecipients();
		updateRecipientsInput();
	} );

	$( 'form.tt-form-card' ).on( 'submit', function () {
		updateRecipientsInput();
	} );

	recipients = normalizeRecipients( recipients );
	syncCategoryInputState();
	renderRecipients();
	updateRecipientsInput();
} );
