/**
 * Geometry resolution for modal placement and size.
 *
 * Placement precedence: the trigger's override, else the Modal Dialog
 * block's own value, else centered.
 *
 * Size is contextual. Centered modals measure a max-width; panels measure
 * a width. Rather than allowlisting known slugs, the resolver rejects only
 * a slug that belongs to the *other* mode — otherwise `fullscreen` on a
 * right panel would apply `max-width: 100%` and silently turn the panel
 * into a full-width sheet. Everything else, including a site's own custom
 * slug registered via `pikari_gutenberg_modals_modal_sizes` or
 * `pikari_gutenberg_modals_panel_widths`, passes through unchanged.
 */

export const PANEL_PLACEMENTS = [ 'left', 'right' ];
export const PANEL_SIZES = [ 'narrow', 'wide' ];
export const CENTERED_SIZES = [ 'small', 'large', 'fullscreen' ];

/**
 * Resolve the effective placement and size for an opening modal.
 *
 * @param {Object} options                  Resolution inputs.
 * @param {string} options.triggerPlacement Placement from the trigger's context.
 * @param {string} options.dialogPlacement  Placement declared by the Modal Dialog block.
 * @param {string} options.size             Size slug from the trigger's context.
 * @return {{placement: string, size: string}} Effective geometry. Empty strings mean default.
 */
export function resolveGeometry( {
	triggerPlacement = '',
	dialogPlacement = '',
	size = '',
} = {} ) {
	let placement = '';

	if ( PANEL_PLACEMENTS.includes( triggerPlacement ) ) {
		placement = triggerPlacement;
	} else if ( PANEL_PLACEMENTS.includes( dialogPlacement ) ) {
		placement = dialogPlacement;
	}

	const foreign = placement ? CENTERED_SIZES : PANEL_SIZES;

	return {
		placement,
		size: foreign.includes( size ) ? '' : size,
	};
}
