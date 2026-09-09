/**
 * Decides whether a click inside a modal trigger belongs to something else.
 *
 * A trigger wraps arbitrary content, and that content may contain genuine
 * interactive elements — a "Read more" link in a card, a form control. Those
 * must keep working, so the trigger defers to them rather than opening.
 *
 * Deliberately free of `@wordpress/*` imports so Jest can resolve it: the
 * editor and interactivity packages are webpack externals and are not
 * installed.
 */

/**
 * Elements a trigger click should defer to.
 *
 * `a[href]` rather than `a`: a core Button with no link set renders
 * `<a class="wp-block-button__link">` with no href. That is not a link — it is
 * not focusable and there is nothing to navigate to — so deferring to it left
 * the button inert, opening nothing and going nowhere.
 */
export const DEFERRED_SELECTOR =
	'a[href]:not(.is-primary-link), button, input, select, textarea, [role="button"]';

/**
 * Whether the trigger should let the browser handle this click instead.
 *
 * @param {HTMLElement|null} clickedElement The click target.
 * @param {HTMLElement|null} currentTarget  The trigger wrapper.
 * @return {boolean} True when another element owns the click.
 */
export function shouldDeferToElement( clickedElement, currentTarget ) {
	if ( ! clickedElement || typeof clickedElement.closest !== 'function' ) {
		return false;
	}

	const match = clickedElement.closest( DEFERRED_SELECTOR );

	return Boolean(
		match &&
			match !== currentTarget &&
			! match.classList.contains( 'is-primary-link' )
	);
}
