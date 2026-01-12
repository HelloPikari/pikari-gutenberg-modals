/**
 * WordPress Interactivity API store for Pikari Modal functionality.
 */

import { store, getContext, withSyncEvent } from '@wordpress/interactivity';
import { escapeHTML, escapeAttribute } from '@wordpress/escape-html';
import {
	setupFocusTrap,
	removeFocusTrap,
	setBackgroundInert,
	focusFirstElement,
} from './modal-a11y';
import { loadBlockStyles } from './block-style-loader';

const { state, actions } = store( 'pikari-modal', {
	state: {
		isOpen: false,
		content: '',
		loading: false,
		hasError: false,
		errorMessage: '',
		activeTriggerId: null,
		activeModalId: null, // Tracks which modal is open for aria-expanded binding
		hasAnimated: false,
	},

	actions: {
		/**
		 * Open the modal and fetch content.
		 *
		 * Uses a generator function instead of async/await because the
		 * Interactivity API needs to track async behavior to restore proper scope.
		 * See: https://developer.wordpress.org/block-editor/reference-guides/interactivity-api/api-reference/
		 */
		*openModal() {
			const context = getContext();
			const { postId, modalId } = context;

			if ( ! postId || ! modalId ) {
				// eslint-disable-next-line no-console
				console.error( 'Missing postId or modalId in context' );
				return;
			}

			// Store the trigger element for focus management
			// eslint-disable-next-line @wordpress/no-global-active-element
			state.activeTriggerId = document.activeElement?.id || null;

			// Set loading state
			state.loading = true;
			state.isOpen = true;
			state.activeModalId = modalId; // Track which modal is open for aria-expanded
			state.hasError = false;
			state.errorMessage = '';
			state.hasAnimated = false; // Start with entering animation

			// Show modal and trigger enter animation
			const modal = document.getElementById( 'pikari-modal' );
			if ( modal ) {
				modal.style.display = 'flex';
				modal.classList.add( 'is-open' );
				modal.classList.remove( 'is-closing' );

				// Set up accessibility features
				setupFocusTrap( modal );
				setBackgroundInert( true );
			}

			// Prevent body scroll when modal is open
			document.body.style.overflow = 'hidden';

			try {
				// Yield the fetch promise - Interactivity API will handle awaiting
				const response = yield fetch(
					`/wp-json/pikari-gutenberg-modals/v1/modal-content/${ postId }?modal_id=${ modalId }`
				);

				if ( ! response.ok ) {
					throw new Error( `HTTP error! status: ${ response.status }` );
				}

				// Yield the JSON parsing
				const data = yield response.json();

				if ( data.content ) {
					// Load block styles before showing content to prevent FOUC
					if ( data.blockStyles?.urls?.length ) {
						yield loadBlockStyles( data.blockStyles.urls );
					}

					// Build modal HTML with title and content using template literal
					// - escapeHTML for title (user-facing text)
					// - escapeAttribute for type/id (HTML attribute values used in CSS classes)
					// - content/styles are pre-sanitized HTML from WordPress
					const htmlContent = `
						${ data.styles ? `<style>${ data.styles }</style>` : '' }
						<article class="modal-entry type-${ escapeAttribute( String( data.type ) ) } post-${ escapeAttribute( String( data.id ) ) }">
							<header class="modal-entry-header">
								<h2 id="modal-title">${ escapeHTML( data.title ) }</h2>
							</header>
							<div class="modal-entry-content">
								${ data.content }
							</div>
						</article>
					`;

					// Directly set innerHTML since data-wp-html directive doesn't exist
					// in the WordPress Interactivity API
					const modalBody = document.getElementById( 'modal-content' );
					if ( modalBody ) {
						modalBody.innerHTML = htmlContent;
					}
					state.content = htmlContent;
				} else {
					throw new Error( 'Failed to load modal content' );
				}
			} catch ( error ) {
				// eslint-disable-next-line no-console
				console.error( 'Error loading modal content:', error );
				state.hasError = true;
				state.errorMessage = error.message || 'An error occurred while loading the modal content.';
				state.content = '';
			} finally {
				state.loading = false;

				// Focus first focusable element after content loads
				// eslint-disable-next-line no-undef
				requestAnimationFrame( () => {
					const modalElement = document.getElementById( 'pikari-modal' );
					if ( modalElement ) {
						focusFirstElement( modalElement );
					}
				} );
			}
		},

		closeModal() {
			state.isOpen = false;
			state.activeModalId = null; // Clear active modal for aria-expanded

			// Remove accessibility features
			removeFocusTrap();
			setBackgroundInert( false );

			// Trigger exit animation
			const modal = document.getElementById( 'pikari-modal' );
			if ( modal ) {
				modal.classList.remove( 'is-open' );
				modal.classList.add( 'is-closing' );
			}

			// Delay hiding and content clearing to allow exit animation
			setTimeout( () => {
				if ( modal ) {
					modal.style.display = 'none';
					modal.classList.remove( 'is-closing' );
				}
				state.content = '';
				// Clear innerHTML directly since data-wp-html doesn't exist
				const modalBody = document.getElementById( 'modal-content' );
				if ( modalBody ) {
					modalBody.innerHTML = '';
				}
				state.loading = false;
				state.hasError = false;
				state.errorMessage = '';
				state.hasAnimated = false;
			}, 200 ); // Match the leave animation duration

			// Restore body scroll
			document.body.style.overflow = '';

			// Return focus to trigger element
			if ( state.activeTriggerId ) {
				const triggerElement = document.getElementById( state.activeTriggerId );
				if ( triggerElement ) {
					triggerElement.focus();
				}
				state.activeTriggerId = null;
			}
		},

		closeModalOnBackdrop: withSyncEvent( ( event ) => {
			// Only close if clicking on the backdrop itself, not its children
			if ( event.target === event.currentTarget ) {
				actions.closeModal();
			}
		} ),

		handleKeydown: withSyncEvent( ( event ) => {
			if ( event.key === 'Escape' && state.isOpen ) {
				event.preventDefault();
				actions.closeModal();
			}
		} ),

		handleTriggerClick: withSyncEvent( ( event ) => {
			// Prevent default navigation - show modal instead
			// Without JS, the link navigates to the actual content (progressive enhancement)
			event.preventDefault();
			actions.openModal();
		} ),

		/**
		 * Handle clicks on modal trigger containers (group blocks).
		 *
		 * Uses click delegation: triggers modal when clicking on "empty" areas
		 * or the primary link, but lets other interactive elements work normally.
		 */
		handleGroupTriggerClick: withSyncEvent( ( event ) => {
			const clickedElement = event.target;

			// Check if clicked element is an interactive element that should NOT trigger modal
			const interactiveSelector =
				'a:not(.is-primary-link), button, input, select, textarea, [role="button"]';
			const clickedInteractive = clickedElement.closest( interactiveSelector );

			// If clicked on a non-primary interactive element, let it handle the event
			if (
				clickedInteractive &&
				! clickedInteractive.classList.contains( 'is-primary-link' )
			) {
				return; // Don't prevent default, let the element work normally
			}

			// Otherwise, trigger the modal
			event.preventDefault();
			actions.openModal();
		} ),

		stopPropagation: withSyncEvent( ( event ) => {
			event.stopPropagation();
		} ),
	},
} );

// Development debug helper - exposes store to browser console
// Access via: pikariModal.state, pikariModal.actions
// eslint-disable-next-line no-undef
if ( typeof window !== 'undefined' ) {
	window.pikariModal = { state, actions };
}
