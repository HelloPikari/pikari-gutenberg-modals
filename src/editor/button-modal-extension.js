/**
 * Button Modal Extension
 *
 * Extends the core/button block with modal trigger functionality
 * using the Higher-Order Component (HOC) pattern.
 */

import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, ToggleControl, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Add modal attributes to core/button block.
 *
 * @param {Object} settings Block settings object.
 * @param {string} name     Block name.
 * @return {Object} Modified block settings.
 */
function addModalAttributes( settings, name ) {
	if ( name !== 'core/button' ) {
		return settings;
	}

	return {
		...settings,
		attributes: {
			...settings.attributes,
			pikariOpenInModal: {
				type: 'boolean',
				default: false,
			},
		},
	};
}

addFilter(
	'blocks.registerBlockType',
	'pikari-gutenberg-modals/button-modal-attributes',
	addModalAttributes
);

/**
 * Add Modal Settings panel to core/button block inspector controls.
 *
 * Uses createHigherOrderComponent to wrap the block's edit component
 * and inject additional InspectorControls alongside the original UI.
 */
const withModalInspectorControls = createHigherOrderComponent(
	( BlockEdit ) => {
		return ( props ) => {
			// Only modify core/button blocks
			if ( props.name !== 'core/button' ) {
				return <BlockEdit { ...props } />;
			}

			const { attributes, setAttributes } = props;
			const { pikariOpenInModal, url } = attributes;

			return (
				<>
					<BlockEdit { ...props } />
					<InspectorControls>
						<PanelBody
							title={ __(
								'Modal Settings',
								'pikari-gutenberg-modals'
							) }
							initialOpen={ pikariOpenInModal }
						>
							{ ! url && (
								<Notice status="info" isDismissible={ false }>
									{ __(
										'Add a link to the button first to enable modal functionality.',
										'pikari-gutenberg-modals'
									) }
								</Notice>
							) }
							<ToggleControl
								__nextHasNoMarginBottom
								label={ __(
									'Open link in modal',
									'pikari-gutenberg-modals'
								) }
								help={
									pikariOpenInModal
										? __(
											'The linked content will open in a modal dialog.',
											'pikari-gutenberg-modals'
										)
										: __(
											'The link will open normally.',
											'pikari-gutenberg-modals'
										)
								}
								checked={ pikariOpenInModal }
								onChange={ ( value ) =>
									setAttributes( { pikariOpenInModal: value } )
								}
								disabled={ ! url }
							/>
						</PanelBody>
					</InspectorControls>
				</>
			);
		};
	},
	'withModalInspectorControls'
);

addFilter(
	'editor.BlockEdit',
	'pikari-gutenberg-modals/button-modal-controls',
	withModalInspectorControls
);
