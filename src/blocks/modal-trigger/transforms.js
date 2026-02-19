/**
 * WordPress dependencies
 */
import { createBlock } from '@wordpress/blocks';

const transforms = {
	from: [
		{
			type: 'block',
			blocks: [ 'core/group' ],
			transform: ( attributes, innerBlocks ) => {
				return createBlock(
					'pikari-gutenberg-modals/modal-trigger',
					{
						contentSource:
							attributes.pikariModalContentSource || 'link',
						primaryLinkId:
							attributes.pikariModalTriggerBlockId || '',
						modalSize: attributes.pikariModalSize || '',
						inlineAnchor:
							attributes.pikariModalInlineAnchor || '',
						templatePart:
							attributes.pikariModalTemplatePart || '',
					},
					innerBlocks
				);
			},
		},
	],
	to: [
		{
			type: 'block',
			blocks: [ 'core/group' ],
			transform: ( attributes, innerBlocks ) => {
				return createBlock( 'core/group', {}, innerBlocks );
			},
		},
	],
};

export default transforms;
