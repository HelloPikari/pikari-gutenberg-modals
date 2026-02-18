/**
 * WordPress dependencies
 */
import { registerBlockType, createBlock } from '@wordpress/blocks';
import { InnerBlocks, useBlockProps } from '@wordpress/block-editor';

/**
 * Internal dependencies
 */
import edit from './edit';
import metadata from './block.json';
import icon from './icon';

registerBlockType( metadata.name, {
	icon,
	edit,
	save() {
		const blockProps = useBlockProps.save();
		return (
			<div { ...blockProps }>
				<InnerBlocks.Content />
			</div>
		);
	},
	transforms: {
		from: [
			{
				type: 'block',
				blocks: [ 'core/group' ],
				isMatch: ( attributes ) =>
					attributes.pikariModalTrigger === true,
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
	},
} );
