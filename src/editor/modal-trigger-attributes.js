/**
 * Adds the modal attributes to every supported trigger block.
 *
 * Replaces the attribute halves of button-modal-extension.js and
 * group-modal-trigger-extension.js, which declared two different "on"
 * attributes (pikariOpenInModal, pikariModalTrigger) for one concept.
 */

import { addFilter } from '@wordpress/hooks';
import { isTriggerBlock, MODAL_ATTRIBUTES } from './trigger-blocks';

addFilter(
	'blocks.registerBlockType',
	'pikari-gutenberg-modals/modal-attributes',
	( settings, name ) => {
		if ( ! isTriggerBlock( name ) ) {
			return settings;
		}

		return {
			...settings,
			attributes: {
				...settings.attributes,
				...MODAL_ATTRIBUTES,
			},
		};
	}
);
