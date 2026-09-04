/**
 * Geometry resolution for modal placement and size.
 *
 * Placement precedence: the trigger's override, else the Modal Dialog
 * block's own value, else centered.
 *
 * Size is contextual. Centered modals measure a max-width; panels measure
 * a width. A slug only survives if it belongs to the resolved placement —
 * without that, `fullscreen` on a right panel would apply
 * `max-width: 100%` and silently turn the panel into a full-width sheet.
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

	const allowed = placement ? PANEL_SIZES : CENTERED_SIZES;

	return {
		placement,
		size: allowed.includes( size ) ? size : '',
	};
}
