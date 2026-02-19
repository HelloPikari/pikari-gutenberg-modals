/**
 * Group Modal Trigger Extension
 *
 * Extends the core/group block with modal trigger functionality,
 * making the entire group clickable to open a modal.
 * Uses the CSS pseudo-element pattern for accessible clickable groups.
 */

import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { InspectorControls, useBlockEditingMode } from '@wordpress/block-editor';
import {
	PanelBody,
	ToggleControl,
	SelectControl,
	Notice,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import { useMemo, useEffect } from '@wordpress/element';
import useModalContentBlocks from './use-modal-content-blocks';
import useIsModalTemplatePart from './use-is-modal-template-part';
import useModalTemplateParts from './use-modal-template-parts';
import findLinksInBlocks from './find-links-in-blocks';

// Modal sizes from PHP filter (pikari_gutenberg_modals_modal_sizes)
const MODAL_SIZE_OPTIONS = window.pikariGutenbergModals?.modalSizes || [
	{ label: __( 'Default', 'pikari-gutenberg-modals' ), value: '' },
	{ label: __( 'Small', 'pikari-gutenberg-modals' ), value: 'small' },
	{ label: __( 'Large', 'pikari-gutenberg-modals' ), value: 'large' },
	{ label: __( 'Fullscreen', 'pikari-gutenberg-modals' ), value: 'fullscreen' },
];

/**
 * Add modal trigger attributes to core/group block.
 *
 * @param {Object} settings Block settings object.
 * @param {string} name     Block name.
 * @return {Object} Modified block settings.
 */
function addModalTriggerAttributes( settings, name ) {
	if ( name !== 'core/group' ) {
		return settings;
	}

	return {
		...settings,
		attributes: {
			...settings.attributes,
			pikariModalTrigger: {
				type: 'boolean',
				default: false,
			},
			pikariModalTriggerBlockId: {
				type: 'string',
				default: '',
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
			pikariModalTemplatePart: {
				type: 'string',
				default: '',
			},
		},
	};
}

addFilter(
	'blocks.registerBlockType',
	'pikari-gutenberg-modals/group-modal-trigger-attributes',
	addModalTriggerAttributes
);

/**
 * Add Modal Trigger Settings panel to core/group block inspector controls.
 *
 * Uses createHigherOrderComponent to wrap the block's edit component
 * and inject additional InspectorControls alongside the original UI.
 */
const withModalTriggerInspectorControls = createHigherOrderComponent(
	( BlockEdit ) => {
		return ( props ) => {
			// Only modify core/group blocks
			if ( props.name !== 'core/group' ) {
				return <BlockEdit { ...props } />;
			}

			const { attributes, setAttributes, clientId } = props;
			const {
				pikariModalTrigger,
				pikariModalTriggerBlockId,
				pikariModalSize,
				pikariModalContentSource,
				pikariModalInlineAnchor,
				pikariModalTemplatePart,
			} = attributes;

			const contentSource = pikariModalContentSource || 'link';
			const isInline = contentSource === 'inline';
			const modalContentBlocks = useModalContentBlocks();
			const templateParts = useModalTemplateParts();

			// Check if we're in contentOnly editing mode (e.g., locked patterns)
			const blockEditingMode = useBlockEditingMode();
			const isContentOnly = blockEditingMode === 'contentOnly';
			const isInsideModalTemplatePart = useIsModalTemplatePart( clientId );

			// Get inner blocks for this group
			const innerBlocks = useSelect(
				( select ) => {
					return select( 'core/block-editor' ).getBlocks( clientId );
				},
				[ clientId ]
			);

			// Detect links in inner blocks (only needed for link mode)
			const detectedLinks = useMemo( () => {
				if ( isInline ) {
					return [];
				}
				return findLinksInBlocks( innerBlocks );
			}, [ innerBlocks, isInline ] );

			// Auto-select first link when modal trigger is enabled and no link is selected (link mode only)
			useEffect( () => {
				if (
					pikariModalTrigger &&
					! isInline &&
					! pikariModalTriggerBlockId &&
					detectedLinks.length > 0
				) {
					setAttributes( {
						pikariModalTriggerBlockId: JSON.stringify(
							detectedLinks[ 0 ].identifier
						),
					} );
				}
			}, [ pikariModalTrigger, isInline, pikariModalTriggerBlockId, detectedLinks, setAttributes ] );

			// Clear selection if the selected link no longer exists (link mode only)
			useEffect( () => {
				if ( pikariModalTrigger && ! isInline && pikariModalTriggerBlockId && detectedLinks.length > 0 ) {
					try {
						const selectedIdentifier = JSON.parse( pikariModalTriggerBlockId );

						let stillExists;
						if ( selectedIdentifier.linkType === 'post-link' ) {
							// For post-link blocks, check by block name and link type
							stillExists = detectedLinks.some(
								( link ) =>
									link.identifier.blockName === selectedIdentifier.blockName &&
									link.identifier.linkType === 'post-link'
							);
						} else {
							// For URL-based links, check by URL
							stillExists = detectedLinks.some(
								( link ) => link.identifier.linkUrl === selectedIdentifier.linkUrl
							);
						}

						if ( ! stillExists ) {
							// Selected link was removed, clear the selection
							setAttributes( { pikariModalTriggerBlockId: '' } );
						}
					} catch ( e ) {
						// Invalid JSON, clear it
						setAttributes( { pikariModalTriggerBlockId: '' } );
					}
				}
			}, [ pikariModalTrigger, isInline, pikariModalTriggerBlockId, detectedLinks, setAttributes ] );

			// Don't show controls if in contentOnly mode or inside the modal template part
			if ( isContentOnly || isInsideModalTemplatePart ) {
				return <BlockEdit { ...props } />;
			}

			// Determine if the trigger has a valid content source configured
			const hasValidSource = isInline
				? !! pikariModalInlineAnchor
				: detectedLinks.length > 0;

			return (
				<>
					<BlockEdit { ...props } />
					<InspectorControls>
						<PanelBody
							title={ __(
								'Modal Settings',
								'pikari-gutenberg-modals'
							) }
							initialOpen={ pikariModalTrigger }
						>
							<ToggleControl
								__nextHasNoMarginBottom
								label={ __(
									'Trigger Modal',
									'pikari-gutenberg-modals'
								) }
								help={
									pikariModalTrigger
										? __(
											'Clicking anywhere on this group will open a modal.',
											'pikari-gutenberg-modals'
										)
										: __(
											'Make the entire group clickable to open a modal.',
											'pikari-gutenberg-modals'
										)
								}
								checked={ pikariModalTrigger }
								onChange={ ( value ) =>
									setAttributes( { pikariModalTrigger: value } )
								}
							/>

							{ pikariModalTrigger && (
								<>
									<Notice
										status="info"
										isDismissible={ false }
									>
										{ __(
											'Tip: The Modal Trigger block is now available in the inserter. It provides the same functionality with an easier setup. You can transform this group to a Modal Trigger block using the block toolbar.',
											'pikari-gutenberg-modals'
										) }
									</Notice>
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
													'Detected Link',
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

									{ ! isInline && detectedLinks.length === 0 && (
										<Notice status="warning" isDismissible={ false }>
											{ __(
												'No links found in this group. Add a block with a link (button, heading, image, etc.) to use as the modal trigger.',
												'pikari-gutenberg-modals'
											) }
										</Notice>
									) }

									{ ! isInline && detectedLinks.length > 0 && (
										<SelectControl
											__nextHasNoMarginBottom
											label={ __(
												'Primary Link',
												'pikari-gutenberg-modals'
											) }
											help={ __(
												'This link determines the modal content. Its clickable area will expand to cover the entire group.',
												'pikari-gutenberg-modals'
											) }
											value={ pikariModalTriggerBlockId }
											options={ detectedLinks.map( ( link ) => ( {
												label: link.label,
												value: JSON.stringify( link.identifier ),
											} ) ) }
											onChange={ ( value ) =>
												setAttributes( {
													pikariModalTriggerBlockId: value,
												} )
											}
										/>
									) }

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

									{ hasValidSource && (
										<SelectControl
											__nextHasNoMarginBottom
											label={ __(
												'Modal Size',
												'pikari-gutenberg-modals'
											) }
											value={ pikariModalSize }
											options={ MODAL_SIZE_OPTIONS }
											onChange={ ( value ) =>
												setAttributes( { pikariModalSize: value } )
											}
										/>
									) }
									{ templateParts.hasMultiple && (
										<SelectControl
											__nextHasNoMarginBottom
											label={ __(
												'Modal Template',
												'pikari-gutenberg-modals'
											) }
											value={
												pikariModalTemplatePart
											}
											options={
												templateParts.options
											}
											onChange={ ( value ) =>
												setAttributes( {
													pikariModalTemplatePart:
														value,
												} )
											}
										/>
									) }
									{ ! templateParts.isValidSelection( pikariModalTemplatePart ) && (
										<Notice
											status="warning"
											onRemove={ () =>
												setAttributes( {
													pikariModalTemplatePart: '',
												} )
											}
										>
											{ sprintf(
												/* translators: %s: template part slug */
												__( 'The modal template "%s" no longer exists. The default template will be used.', 'pikari-gutenberg-modals' ),
												pikariModalTemplatePart
											) }
										</Notice>
									) }
								</>
							) }
						</PanelBody>
					</InspectorControls>
				</>
			);
		};
	},
	'withModalTriggerInspectorControls'
);

addFilter(
	'editor.BlockEdit',
	'pikari-gutenberg-modals/group-modal-trigger-controls',
	withModalTriggerInspectorControls
);
