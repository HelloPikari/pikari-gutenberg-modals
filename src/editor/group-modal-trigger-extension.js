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
import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import { useMemo, useEffect } from '@wordpress/element';
import useModalContentBlocks from './use-modal-content-blocks';

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
		},
	};
}

addFilter(
	'blocks.registerBlockType',
	'pikari-gutenberg-modals/group-modal-trigger-attributes',
	addModalTriggerAttributes
);

/**
 * Recursively find all links in inner blocks.
 *
 * @param {Array}  blocks     Array of blocks to search.
 * @param {string} parentPath Path to the parent block (e.g., "0.2").
 * @return {Array} Array of detected link objects.
 */
function findLinksInBlocks( blocks, parentPath = '' ) {
	const links = [];

	blocks.forEach( ( block, index ) => {
		const currentPath = parentPath ? `${ parentPath }.${ index }` : `${ index }`;

		// Check for links based on block type
		switch ( block.name ) {
			case 'core/button':
				if ( block.attributes.url ) {
					links.push( {
						identifier: {
							blockPath: currentPath,
							blockName: block.name,
							linkUrl: block.attributes.url,
						},
						label: `${ __( 'Button', 'pikari-gutenberg-modals' ) }: ${ block.attributes.text || block.attributes.url }`,
					} );
				}
				break;

			case 'core/image':
				if ( block.attributes.href ) {
					links.push( {
						identifier: {
							blockPath: currentPath,
							blockName: block.name,
							linkUrl: block.attributes.href,
						},
						label: `${ __( 'Image', 'pikari-gutenberg-modals' ) }: ${ block.attributes.alt || block.attributes.href }`,
					} );
				}
				break;

			case 'core/navigation-link':
				if ( block.attributes.url ) {
					links.push( {
						identifier: {
							blockPath: currentPath,
							blockName: block.name,
							linkUrl: block.attributes.url,
						},
						label: `${ __( 'Nav Link', 'pikari-gutenberg-modals' ) }: ${ block.attributes.label || block.attributes.url }`,
					} );
				}
				break;

			case 'core/heading':
			case 'core/paragraph': {
				// Parse content for <a> tags
				const content = block.attributes.content || '';
				const anchorMatches = content.match( /<a[^>]+href="([^"]+)"[^>]*>([^<]*)<\/a>/g );

				if ( anchorMatches ) {
					anchorMatches.forEach( ( match, linkIndex ) => {
						const hrefMatch = match.match( /href="([^"]+)"/ );
						const textMatch = match.match( />([^<]*)<\/a>/ );

						if ( hrefMatch && hrefMatch[ 1 ] ) {
							const blockTypeLabel = block.name === 'core/heading'
								? __( 'Heading', 'pikari-gutenberg-modals' )
								: __( 'Paragraph', 'pikari-gutenberg-modals' );

							links.push( {
								identifier: {
									blockPath: `${ currentPath }.${ linkIndex }`,
									blockName: block.name,
									linkUrl: hrefMatch[ 1 ],
								},
								label: `${ blockTypeLabel }: ${ textMatch ? textMatch[ 1 ] : hrefMatch[ 1 ] }`,
							} );
						}
					} );
				}
				break;
			}

			// Query Loop post-link blocks (link to current post dynamically)
			case 'core/post-title':
			case 'core/post-featured-image':
			case 'core/post-date':
				if ( block.attributes.isLink ) {
					const postLinkLabels = {
						'core/post-title': __( 'Post Title', 'pikari-gutenberg-modals' ),
						'core/post-featured-image': __( 'Featured Image', 'pikari-gutenberg-modals' ),
						'core/post-date': __( 'Post Date', 'pikari-gutenberg-modals' ),
					};
					links.push( {
						identifier: {
							blockPath: currentPath,
							blockName: block.name,
							linkType: 'post-link',
							linkUrl: null,
						},
						label: `${ postLinkLabels[ block.name ] }: ${ __( 'Current Post', 'pikari-gutenberg-modals' ) }`,
					} );
				}
				break;

			case 'core/read-more':
				// Read More block always links to current post
				links.push( {
					identifier: {
						blockPath: currentPath,
						blockName: block.name,
						linkType: 'post-link',
						linkUrl: null,
					},
					label: `${ __( 'Read More', 'pikari-gutenberg-modals' ) }: ${ block.attributes.content || __( 'Current Post', 'pikari-gutenberg-modals' ) }`,
				} );
				break;

			case 'core/post-excerpt':
				// Only detect as link if moreText has a non-empty value
				if ( block.attributes.moreText && block.attributes.moreText.trim() !== '' ) {
					links.push( {
						identifier: {
							blockPath: currentPath,
							blockName: block.name,
							linkType: 'post-link',
							linkUrl: null,
						},
						label: `${ __( 'Excerpt', 'pikari-gutenberg-modals' ) }: ${ block.attributes.moreText }`,
					} );
				}
				break;
		}

		// Recursively search inner blocks
		if ( block.innerBlocks && block.innerBlocks.length > 0 ) {
			links.push( ...findLinksInBlocks( block.innerBlocks, currentPath ) );
		}
	} );

	return links;
}

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
			} = attributes;

			const contentSource = pikariModalContentSource || 'link';
			const isInline = contentSource === 'inline';
			const modalContentBlocks = useModalContentBlocks();

			// Check if we're in contentOnly editing mode (e.g., locked patterns)
			const blockEditingMode = useBlockEditingMode();
			const isContentOnly = blockEditingMode === 'contentOnly';

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

			// Don't show controls if in contentOnly mode
			if ( isContentOnly ) {
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
