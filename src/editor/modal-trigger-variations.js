/**
 * Inserter entries for the two trigger shapes.
 *
 * A variation is the core block with attributes pre-filled — it adds no
 * behaviour of its own and is not serialized into saved content, so server
 * rendering keys on pikariModalAction, never on the variation name.
 */

import { registerBlockVariation } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';

registerBlockVariation( 'core/group', {
	name: 'pikari-modal-clickable-card',
	title: __( 'Clickable Card', 'pikari-gutenberg-modals' ),
	description: __(
		'A group that opens its content in a modal when clicked.',
		'pikari-gutenberg-modals'
	),
	attributes: {
		pikariModalAction: 'open',
		pikariModalContentSource: 'link',
	},
	scope: [ 'inserter', 'transform' ],
	isActive: ( blockAttributes ) =>
		blockAttributes?.pikariModalAction === 'open',
} );

registerBlockVariation( 'core/button', {
	name: 'pikari-modal-button',
	title: __( 'Modal Button', 'pikari-gutenberg-modals' ),
	description: __(
		'A button that opens content in a modal.',
		'pikari-gutenberg-modals'
	),
	attributes: {
		pikariModalAction: 'open',
		pikariModalContentSource: 'url',
	},
	scope: [ 'inserter', 'transform' ],
	isActive: ( blockAttributes ) =>
		blockAttributes?.pikariModalAction === 'open',
} );
