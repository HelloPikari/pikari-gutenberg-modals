/**
 * Accessibility utilities for Pikari Modal.
 *
 * Handles focus trapping, background inert state, and other a11y concerns.
 */

// Focus trap handler reference
let focusTrapHandler = null;

/**
 * Selector for focusable elements.
 */
const FOCUSABLE_SELECTOR =
	'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])';

/**
 * Get all focusable elements within a container.
 *
 * @param {HTMLElement} container - The container element
 * @return {HTMLElement[]} Array of focusable elements
 */
export function getFocusableElements( container ) {
	return Array.from( container.querySelectorAll( FOCUSABLE_SELECTOR ) ).filter(
		( el ) => ! el.disabled && el.offsetParent !== null
	);
}

/**
 * Set up focus trap within the modal.
 * Prevents focus from leaving the modal when tabbing.
 *
 * @param {HTMLElement} modal - The modal element
 */
export function setupFocusTrap( modal ) {
	// Remove existing trap if any
	removeFocusTrap();

	focusTrapHandler = ( event ) => {
		if ( ! modal.contains( event.target ) ) {
			event.preventDefault();
			const focusable = getFocusableElements( modal );
			if ( focusable.length ) {
				focusable[ 0 ].focus();
			}
		}
	};
	document.addEventListener( 'focusin', focusTrapHandler, true );
}

/**
 * Remove focus trap.
 */
export function removeFocusTrap() {
	if ( focusTrapHandler ) {
		document.removeEventListener( 'focusin', focusTrapHandler, true );
		focusTrapHandler = null;
	}
}

/**
 * Set inert attribute on background content.
 * This hides background content from screen readers and prevents interaction.
 *
 * @param {boolean} inert - Whether to set or remove inert
 */
export function setBackgroundInert( inert ) {
	const selectors = [ 'main', 'header', 'footer', 'aside', 'nav' ];
	selectors.forEach( ( selector ) => {
		const elements = document.querySelectorAll( selector );
		elements.forEach( ( el ) => {
			// Don't make the modal's parent inert
			if ( ! el.contains( document.getElementById( 'pikari-modal' ) ) ) {
				if ( inert ) {
					el.setAttribute( 'inert', '' );
				} else {
					el.removeAttribute( 'inert' );
				}
			}
		} );
	} );
}

/**
 * Focus the first focusable element in the modal.
 *
 * @param {HTMLElement} modal - The modal element
 */
export function focusFirstElement( modal ) {
	const focusable = getFocusableElements( modal );
	if ( focusable.length ) {
		focusable[ 0 ].focus();
	}
}
