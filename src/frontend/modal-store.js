/**
 * WordPress Interactivity API store for Pikari Modal functionality.
 */

import {
	store,
	getConfig,
	getContext,
	withScope,
	withSyncEvent,
} from '@wordpress/interactivity';
import { escapeHTML, escapeAttribute } from '@wordpress/escape-html';
import {
	setupFocusTrap,
	removeFocusTrap,
	setBackgroundInert,
	focusFirstElement,
} from './modal-a11y';
import { loadBlockStyles } from './block-style-loader';

// Prefetch delay in milliseconds - filters out accidental mouse movements
const PREFETCH_DELAY_MS = 200;

// Module-scope DOM reference for the active modal container.
// DOM refs should not live in reactive state.
let activeContainer = null;
let closeTimeoutId = null;

/**
 * Resolve the modal container element for a given template part slug.
 *
 * @param {string} slug - Template part slug (default: 'modal')
 * @return {HTMLElement|null} The container element
 */
function getContainerElement( slug ) {
	const id = slug === 'modal' ? 'pikari-modal' : `pikari-modal--${ slug }`;
	return document.getElementById( id );
}

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
		prefetchedPosts: {}, // Tracks prefetch status: { [postId]: 'pending' | 'complete' }

		/**
		 * Derived state: whether this specific trigger's modal is currently open.
		 * Used for aria-expanded binding so only the active trigger shows expanded.
		 */
		get isExpanded() {
			const context = getContext();
			const contextModalId =
				context.contentSource === 'inline'
					? `inline-${ context.inlineAnchor }`
					: context.modalId;
			return state.activeModalId === contextModalId;
		},
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
			const {
				postId,
				modalId,
				size,
				contentSource,
				inlineAnchor,
				templatePart,
			} = context;

			// Validate required context based on content source
			const isInline = contentSource === 'inline';

			if ( isInline && ! inlineAnchor ) {
				// eslint-disable-next-line no-console
				console.error( 'Missing inlineAnchor in context for inline content' );
				return;
			}

			if ( ! isInline && ( ! postId || ! modalId ) ) {
				// eslint-disable-next-line no-console
				console.error( 'Missing postId or modalId in context' );
				return;
			}

			const activeModalId = isInline ? `inline-${ inlineAnchor }` : modalId;
			const slug = templatePart || 'modal';

			// Cancel any pending close animation from a previous modal
			if ( closeTimeoutId ) {
				clearTimeout( closeTimeoutId );
				closeTimeoutId = null;

				// Clean up accessibility state from the previous modal
				removeFocusTrap();
				if ( activeContainer ) {
					setBackgroundInert( false, activeContainer );
				}

				// Immediately finish closing the previous container
				if ( activeContainer ) {
					activeContainer.style.display = 'none';
					activeContainer.classList.remove( 'is-closing' );
					activeContainer.removeAttribute( 'data-size' );
					const prevBody = activeContainer.querySelector( '.modal-body' );
					if ( prevBody ) {
						prevBody.removeAttribute( 'id' );
						prevBody.innerHTML = '';
					}
				}
			}

			// Resolve the container for this trigger's template part.
			// Falls back to the default container if a custom template part
			// was deleted — ensures the modal always works.
			let modal = getContainerElement( slug );
			if ( ! modal && slug !== 'modal' ) {
				// eslint-disable-next-line no-console
				console.warn( `Modal container not found for template part "${ slug }", falling back to default.` );
				modal = getContainerElement( 'modal' );
			}
			if ( ! modal ) {
				// eslint-disable-next-line no-console
				console.error( 'Modal container not found.' );
				return;
			}
			activeContainer = modal;

			// Store the trigger element for focus management
			// eslint-disable-next-line @wordpress/no-global-active-element
			state.activeTriggerId = document.activeElement?.id || null;

			// Set initial state
			state.isOpen = true;
			state.activeModalId = activeModalId;
			state.hasError = false;
			state.errorMessage = '';
			state.hasAnimated = false;

			// Show modal and trigger enter animation
			modal.style.display = 'flex';
			modal.classList.add( 'is-open' );
			modal.classList.remove( 'is-closing' );

			// Apply size from trigger context
			if ( size ) {
				modal.setAttribute( 'data-size', size );
			} else {
				modal.removeAttribute( 'data-size' );
			}

			// Set up accessibility features
			setupFocusTrap( modal );
			setBackgroundInert( true, modal );

			// Prevent body scroll when modal is open
			document.body.style.overflow = 'hidden';

			// Set dynamic ARIA IDs scoped to this container's slug
			const modalBody = modal.querySelector( '.modal-body' );
			if ( modalBody ) {
				modalBody.id = `modal-content--${ slug }`;
			}

			if ( isInline ) {
				// Inline content: clone from hidden element on the page (no REST API call)
				const sourceElement = document.querySelector(
					`[data-modal-inline-content="${ inlineAnchor }"]`
				);

				if ( sourceElement ) {
					if ( modalBody ) {
						const titleText = sourceElement.getAttribute( 'data-modal-inline-title' ) || '';
						const htmlContent = `
							<article class="modal-entry">
								<h2 id="modal-title--${ escapeAttribute( slug ) }" class="sr-only">${ escapeHTML( titleText ) }</h2>
								<div class="modal-entry-content is-layout-constrained">
									${ sourceElement.innerHTML }
								</div>
							</article>
						`;
						modalBody.innerHTML = htmlContent;
					}
					state.content = sourceElement.innerHTML;
				} else {
					state.hasError = true;
					state.errorMessage = 'Inline modal content not found on this page.';
					state.content = '';
				}

				state.loading = false;

				// Focus first focusable element
				// eslint-disable-next-line no-undef
				requestAnimationFrame( () => {
					if ( activeContainer ) {
						focusFirstElement( activeContainer );
					}
				} );

				return;
			}

			// External URL: render iframe instead of REST API fetch
			const isExternalUrl = contentSource === 'url';
			if ( isExternalUrl ) {
				const iframeSrc = postId;

				// Extract hostname for accessible iframe title
				let iframeTitle = '';
				try {
					iframeTitle = new URL( iframeSrc ).hostname;
				} catch {
					iframeTitle = iframeSrc;
				}

				const iframeHtml = `
					<article class="modal-entry modal-entry--iframe">
						<h2 id="modal-title--${ escapeAttribute( slug ) }" class="sr-only">${ escapeHTML( iframeTitle ) }</h2>
						<iframe
							src="${ escapeAttribute( iframeSrc ) }"
							title="${ escapeAttribute( iframeTitle ) }"
							sandbox="allow-scripts allow-same-origin allow-forms allow-popups"
						></iframe>
					</article>
				`;

				if ( modalBody ) {
					modalBody.innerHTML = iframeHtml;

					// Clear loading state when iframe finishes loading
					const iframe = modalBody.querySelector( 'iframe' );
					if ( iframe ) {
						iframe.addEventListener(
							'load',
							withScope( () => {
								state.loading = false;
							} ),
							{ once: true }
						);
					}
				}
				state.content = iframeHtml;
				state.loading = true;

				// Focus close button after iframe is inserted
				// eslint-disable-next-line no-undef
				requestAnimationFrame( () => {
					if ( activeContainer ) {
						focusFirstElement( activeContainer );
					}
				} );

				return;
			}

			// Remote content: fetch via REST API
			state.loading = true;

			try {
				// Build the REST URL dynamically so it works with any permalink structure.
				// getConfig() reads the restUrl set by wp_interactivity_config() in PHP.
				// String concatenation is used instead of the URL constructor because
				// plain permalinks put the route in ?rest_route= query param, and the
				// URL constructor resolves relative paths against the pathname only.
				const { restUrl } = getConfig();
				const separator = restUrl.includes( '?' ) ? '&' : '?';
				const fetchUrl = `${ restUrl }modal-content/${ postId }${ separator }modal_id=${ modalId }`;

				// Yield the fetch promise - Interactivity API will handle awaiting
				const response = yield fetch( fetchUrl );

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
							<h2 id="modal-title--${ escapeAttribute( slug ) }" class="sr-only">${ escapeHTML( data.title ) }</h2>
							<div class="modal-entry-content is-layout-constrained">
								${ data.content }
							</div>
						</article>
					`;

					// Directly set innerHTML since data-wp-html directive doesn't exist
					// in the WordPress Interactivity API
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
					if ( activeContainer ) {
						focusFirstElement( activeContainer );
					}
				} );
			}
		},

		closeModal() {
			state.isOpen = false;
			state.activeModalId = null; // Clear active modal for aria-expanded

			// Remove accessibility features
			removeFocusTrap();
			if ( activeContainer ) {
				setBackgroundInert( false, activeContainer );
			}

			// Trigger exit animation
			const modal = activeContainer;
			if ( modal ) {
				modal.classList.remove( 'is-open' );
				modal.classList.add( 'is-closing' );
			}

			// Delay hiding and content clearing to allow exit animation
			closeTimeoutId = setTimeout( () => {
				closeTimeoutId = null;
				if ( modal ) {
					modal.style.display = 'none';
					modal.classList.remove( 'is-closing' );
					modal.removeAttribute( 'data-size' );
				}
				state.content = '';
				// Clear innerHTML directly since data-wp-html doesn't exist
				if ( modal ) {
					const modalBody = modal.querySelector( '.modal-body' );
					if ( modalBody ) {
						modalBody.removeAttribute( 'id' );
						modalBody.innerHTML = '';
					}
				}
				state.loading = false;
				state.hasError = false;
				state.errorMessage = '';
				state.hasAnimated = false;
				activeContainer = null;
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
		 * Handle clicks on modal trigger containers.
		 *
		 * Uses click delegation: triggers modal when clicking on "empty" areas
		 * or the primary link, but lets other interactive elements work normally.
		 * Supports both role="group" (detected link) and role="button" (URL/inline) wrappers.
		 */
		handleGroupTriggerClick: withSyncEvent( ( event ) => {
			const clickedElement = event.target;

			// Check if clicked element is an interactive element that should NOT trigger modal
			const interactiveSelector =
				'a:not(.is-primary-link), button, input, select, textarea, [role="button"]';
			const clickedInteractive = clickedElement.closest( interactiveSelector );

			// If clicked on a non-primary interactive element (not the trigger wrapper itself),
			// let it handle the event normally
			if (
				clickedInteractive &&
				clickedInteractive !== event.currentTarget &&
				! clickedInteractive.classList.contains( 'is-primary-link' )
			) {
				return; // Don't prevent default, let the element work normally
			}

			// Otherwise, trigger the modal
			event.preventDefault();
			actions.openModal();
		} ),

		/**
		 * Handle keyboard activation on role="button" trigger wrappers.
		 *
		 * Elements with role="button" must respond to Enter and Space per
		 * the ARIA button pattern (WCAG 2.1.1).
		 */
		handleTriggerKeydown: withSyncEvent( ( event ) => {
			if ( event.key === 'Enter' || event.key === ' ' ) {
				event.preventDefault();
				actions.openModal();
			}
		} ),

		stopPropagation: withSyncEvent( ( event ) => {
			event.stopPropagation();
		} ),

		/**
		 * Prefetch modal content for the current context.
		 *
		 * Fetches the modal content API endpoint with low priority to warm the
		 * browser's HTTP cache. The response is not processed - we rely on the
		 * browser cache to serve it when openModal() is called.
		 */
		*prefetchModal() {
			const context = getContext();
			const { postId, modalId, contentSource } = context;

			// Skip prefetch for inline content (already on the page)
			if ( contentSource === 'inline' ) {
				return;
			}

			// Skip prefetch for external URLs (loaded via iframe, not REST API)
			if ( contentSource === 'url' ) {
				return;
			}

			// Skip if no postId or already prefetched/prefetching
			if ( ! postId || state.prefetchedPosts[ postId ] ) {
				return;
			}

			// Mark as pending (prevents duplicate requests during fetch)
			state.prefetchedPosts[ postId ] = 'pending';

			try {
				// URL must match openModal() exactly for browser HTTP cache to work
				const { restUrl } = getConfig();
				const separator = restUrl.includes( '?' ) ? '&' : '?';
				const fetchUrl = `${ restUrl }modal-content/${ postId }${ separator }modal_id=${ modalId }`;

				const response = yield fetch( fetchUrl, {
					priority: 'low',
					credentials: 'same-origin',
				} );

				if ( response.ok ) {
					// Success - mark as complete
					// Browser's HTTP cache stores response based on Cache-Control/ETag headers
					state.prefetchedPosts[ postId ] = 'complete';
				} else {
					// Failed - remove from tracking so it can be retried
					delete state.prefetchedPosts[ postId ];
				}
			} catch ( error ) {
				// Network error - remove from tracking so it can be retried
				delete state.prefetchedPosts[ postId ];
			}
		},

		/**
		 * Handle mouse enter on modal triggers.
		 *
		 * Starts a debounced prefetch - if the mouse leaves before the delay,
		 * the prefetch is cancelled.
		 */
		handlePrefetchHover: withSyncEvent( ( event ) => {
			const context = getContext();
			const { postId } = context;

			// Skip prefetch for external URLs (loaded via iframe, not REST API)
			if ( context.contentSource === 'url' ) {
				return;
			}

			// Skip if no postId or already prefetched
			if ( ! postId || state.prefetchedPosts[ postId ] ) {
				return;
			}

			const element = event.currentTarget;

			// Clear any existing timeout for this element
			if ( element._pikariPrefetchTimeout ) {
				clearTimeout( element._pikariPrefetchTimeout );
			}

			// Set debounced prefetch - use withScope to preserve Interactivity API context
			element._pikariPrefetchTimeout = setTimeout(
				withScope( () => {
					actions.prefetchModal();
					delete element._pikariPrefetchTimeout;
				} ),
				PREFETCH_DELAY_MS
			);
		} ),

		/**
		 * Handle mouse leave on modal triggers.
		 *
		 * Cancels any pending prefetch if the mouse leaves before the delay completes.
		 */
		handlePrefetchLeave: withSyncEvent( ( event ) => {
			const element = event.currentTarget;

			// Cancel pending prefetch
			if ( element._pikariPrefetchTimeout ) {
				clearTimeout( element._pikariPrefetchTimeout );
				delete element._pikariPrefetchTimeout;
			}
		} ),
	},
} );

// Development debug helper - exposes store to browser console
// Access via: pikariModal.state, pikariModal.actions
// eslint-disable-next-line no-undef
if ( typeof window !== 'undefined' ) {
	window.pikariModal = { state, actions };
}
