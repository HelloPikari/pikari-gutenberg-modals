/**
 * Which blocks can open a modal, and the attributes that make them do it.
 *
 * Deliberately free of `@wordpress/*` imports: the editor packages are webpack
 * externals and are not installed, so Jest cannot resolve them. Logic that
 * needs a unit test lives here; the filter wiring that needs the editor
 * packages lives in modal-trigger-attributes.js.
 */

/**
 * Blocks that can carry a modal action.
 *
 * core/image is excluded: core attaches its own lightbox click handler to it
 * via render_block_core/image, and the toggle ("Enlarge on click") is offered
 * by default. Two click handlers on one element is the conflict this avoids.
 * A site that wants it anyway can add it through the
 * `pikari_gutenberg_modals_trigger_blocks` filter.
 */
export const TRIGGER_BLOCKS = [ 'core/group', 'core/button' ];

/**
 * Recognised values for pikariModalAction.
 */
export const MODAL_ACTIONS = [ 'open', 'close' ];

/**
 * Attribute schema added to every trigger block.
 */
export const MODAL_ATTRIBUTES = {
	pikariModalAction: { type: 'string', default: '' },
	pikariModalContentSource: { type: 'string', default: '' },
	pikariModalDirectUrl: { type: 'string', default: '' },
	pikariModalPrimaryLinkId: { type: 'string', default: '' },
	pikariModalInlineAnchor: { type: 'string', default: '' },
	pikariModalSize: { type: 'string', default: '' },
	pikariModalPlacement: { type: 'string', default: '' },
	pikariModalTemplatePart: { type: 'string', default: '' },
	pikariModalAccessibleLabel: { type: 'string', default: '' },
	pikariModalAspectRatio: { type: 'string', default: '' },
};

/**
 * Whether a block name can carry a modal action.
 *
 * Prefers the list localized by PHP (window.pikariGutenbergModals.triggerBlocks),
 * which reflects the `pikari_gutenberg_modals_trigger_blocks` filter — so a
 * block added through that filter gets both the editor panel and the
 * server-side decoration. Falls back to the hardcoded default when the
 * global is absent or empty (e.g. this module used outside the block
 * editor, such as in Jest).
 *
 * @param {string} name Block name.
 * @return {boolean} True when supported.
 */
export function isTriggerBlock( name ) {
	const localized = window.pikariGutenbergModals?.triggerBlocks;

	if ( Array.isArray( localized ) && localized.length > 0 ) {
		return localized.includes( name );
	}

	return TRIGGER_BLOCKS.includes( name );
}

/**
 * Whether a block's attributes declare a modal action.
 *
 * @param {Object} attributes Block attributes.
 * @return {boolean} True when the action is open or close.
 */
export function hasModalAction( attributes ) {
	return MODAL_ACTIONS.includes( attributes?.pikariModalAction );
}
