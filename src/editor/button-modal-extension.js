/**
 * Button Modal Extension
 *
 * Extends the core/button block with modal trigger functionality
 * using the Higher-Order Component (HOC) pattern.
 */

import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	ToggleControl,
	SelectControl,
	Notice,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import useModalContentBlocks from './use-modal-content-blocks';
import useIsModalTemplatePart from './use-is-modal-template-part';

// Modal sizes from PHP filter (pikari_gutenberg_modals_modal_sizes)
const MODAL_SIZE_OPTIONS = window.pikariGutenbergModals?.modalSizes || [
	{ label: __( 'Default', 'pikari-gutenberg-modals' ), value: '' },
	{ label: __( 'Small', 'pikari-gutenberg-modals' ), value: 'small' },
	{ label: __( 'Large', 'pikari-gutenberg-modals' ), value: 'large' },
	{ label: __( 'Fullscreen', 'pikari-gutenberg-modals' ), value: 'fullscreen' },
];

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
			pikariModalSize: {
				type: 'string',
				default: '',
			},
			pikariModalContentSource: {
				type: 'string',
				default: 'link',
			},
			pikariModalInlineAnchor: {
				type: 'string',
				default: '',
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

			const { attributes, setAttributes, clientId } = props;
			const {
				pikariOpenInModal,
				pikariModalSize,
				pikariModalContentSource,
				pikariModalInlineAnchor,
				url,
			} = attributes;

			const contentSource = pikariModalContentSource || 'link';
			const modalContentBlocks = useModalContentBlocks();
			const isInline = contentSource === 'inline';
			const isInsideModalTemplatePart = useIsModalTemplatePart( clientId );

			// Don't show controls if inside the modal template part
			if ( isInsideModalTemplatePart ) {
				return <BlockEdit { ...props } />;
			}

			// Toggle is enabled if there's a URL (link mode) or inline content is selected
			const canEnableModal = isInline
				? modalContentBlocks.length > 0
				: !! url;

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
							{ ! isInline && ! url && (
								<Notice status="info" isDismissible={ false }>
									{ __(
										'Add a link to the button first, or switch content source to "Page Content".',
										'pikari-gutenberg-modals'
									) }
								</Notice>
							) }
							<ToggleControl
								__nextHasNoMarginBottom
								label={ __(
									'Open in modal',
									'pikari-gutenberg-modals'
								) }
								help={
									pikariOpenInModal
										? __(
											'The content will open in a modal dialog.',
											'pikari-gutenberg-modals'
										)
										: __(
											'Enable to open content in a modal.',
											'pikari-gutenberg-modals'
										)
								}
								checked={ pikariOpenInModal }
								onChange={ ( value ) =>
									setAttributes( { pikariOpenInModal: value } )
								}
								disabled={ ! canEnableModal }
							/>
							{ pikariOpenInModal && (
								<>
									<SelectControl
										__nextHasNoMarginBottom
										label={ __(
											'Content Source',
											'pikari-gutenberg-modals'
										) }
										value={ contentSource }
										options={ [
											{
												label: __(
													'Button Link',
													'pikari-gutenberg-modals'
												),
												value: 'link',
											},
											{
												label: __(
													'Page Content',
													'pikari-gutenberg-modals'
												),
												value: 'inline',
											},
										] }
										onChange={ ( value ) =>
											setAttributes( {
												pikariModalContentSource: value,
											} )
										}
									/>
									{ isInline && modalContentBlocks.length === 0 && (
										<Notice
											status="warning"
											isDismissible={ false }
										>
											{ __(
												'No Modal Content blocks found on this page. Add a Modal Content block first.',
												'pikari-gutenberg-modals'
											) }
										</Notice>
									) }
									{ isInline && modalContentBlocks.length > 0 && (
										<SelectControl
											__nextHasNoMarginBottom
											label={ __(
												'Modal Content',
												'pikari-gutenberg-modals'
											) }
											value={ pikariModalInlineAnchor }
											options={ [
												{
													label: __(
														'Select\u2026',
														'pikari-gutenberg-modals'
													),
													value: '',
												},
												...modalContentBlocks.map(
													( block ) => ( {
														label: block.title,
														value: block.anchor,
													} )
												),
											] }
											onChange={ ( value ) =>
												setAttributes( {
													pikariModalInlineAnchor:
														value,
												} )
											}
										/>
									) }
									<SelectControl
										__nextHasNoMarginBottom
										label={ __(
											'Modal Size',
											'pikari-gutenberg-modals'
										) }
										value={ pikariModalSize }
										options={ MODAL_SIZE_OPTIONS }
										onChange={ ( value ) =>
											setAttributes( {
												pikariModalSize: value,
											} )
										}
									/>
								</>
							) }
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
