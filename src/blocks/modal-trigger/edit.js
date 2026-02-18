/**
 * WordPress dependencies
 */
import {
	useBlockProps,
	useInnerBlocksProps,
	InspectorControls,
	LinkControl,
	useBlockEditingMode,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	Notice,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import { useMemo, useEffect } from '@wordpress/element';
import findLinksInBlocks from '../../editor/find-links-in-blocks';
import useModalContentBlocks from '../../editor/use-modal-content-blocks';
import useIsModalTemplatePart from '../../editor/use-is-modal-template-part';
import useModalTemplateParts from '../../editor/use-modal-template-parts';

// Modal sizes from PHP filter (pikari_gutenberg_modals_modal_sizes)
const MODAL_SIZE_OPTIONS = window.pikariGutenbergModals?.modalSizes || [
	{ label: __( 'Default', 'pikari-gutenberg-modals' ), value: '' },
	{ label: __( 'Small', 'pikari-gutenberg-modals' ), value: 'small' },
	{ label: __( 'Large', 'pikari-gutenberg-modals' ), value: 'large' },
	{ label: __( 'Fullscreen', 'pikari-gutenberg-modals' ), value: 'fullscreen' },
];

export default function Edit( { attributes, setAttributes, clientId } ) {
	const {
		contentSource,
		primaryLinkId,
		directUrl,
		inlineAnchor,
		modalSize,
		templatePart,
	} = attributes;

	const blockProps = useBlockProps();
	const innerBlocksProps = useInnerBlocksProps( blockProps );

	const isInline = contentSource === 'inline';
	const isUrl = contentSource === 'url';
	const modalContentBlocks = useModalContentBlocks();
	const templateParts = useModalTemplateParts();

	const blockEditingMode = useBlockEditingMode();
	const isContentOnly = blockEditingMode === 'contentOnly';
	const isInsideModalTemplatePart = useIsModalTemplatePart( clientId );

	// Get inner blocks for link detection
	const innerBlocks = useSelect(
		( select ) => {
			return select( blockEditorStore ).getBlocks( clientId );
		},
		[ clientId ]
	);

	// Detect links in inner blocks (only needed for link mode)
	const detectedLinks = useMemo( () => {
		if ( isInline || isUrl ) {
			return [];
		}
		return findLinksInBlocks( innerBlocks );
	}, [ innerBlocks, isInline, isUrl ] );

	// Auto-select first link when no link is selected (link mode only)
	useEffect( () => {
		if (
			contentSource === 'link' &&
			! primaryLinkId &&
			detectedLinks.length > 0
		) {
			setAttributes( {
				primaryLinkId: JSON.stringify(
					detectedLinks[ 0 ].identifier
				),
			} );
		}
	}, [ contentSource, primaryLinkId, detectedLinks, setAttributes ] );

	// Clear selection if the selected link no longer exists (link mode only)
	useEffect( () => {
		if ( contentSource === 'link' && primaryLinkId && detectedLinks.length > 0 ) {
			try {
				const selectedIdentifier = JSON.parse( primaryLinkId );

				let stillExists;
				if ( selectedIdentifier.linkType === 'post-link' ) {
					stillExists = detectedLinks.some(
						( link ) =>
							link.identifier.blockName === selectedIdentifier.blockName &&
							link.identifier.linkType === 'post-link'
					);
				} else {
					stillExists = detectedLinks.some(
						( link ) => link.identifier.linkUrl === selectedIdentifier.linkUrl
					);
				}

				if ( ! stillExists ) {
					setAttributes( { primaryLinkId: '' } );
				}
			} catch ( e ) {
				setAttributes( { primaryLinkId: '' } );
			}
		}
	}, [ contentSource, primaryLinkId, detectedLinks, setAttributes ] );

	// Don't show controls if in contentOnly mode or inside the modal template part
	if ( isContentOnly || isInsideModalTemplatePart ) {
		return <div { ...innerBlocksProps } />;
	}

	// Determine if the trigger has a valid content source configured
	let hasValidSource = detectedLinks.length > 0;
	if ( isInline ) {
		hasValidSource = !! inlineAnchor;
	} else if ( isUrl ) {
		hasValidSource = !! directUrl;
	}

	return (
		<>
			<div { ...innerBlocksProps } />
			<InspectorControls>
				<PanelBody
					title={ __(
						'Modal Settings',
						'pikari-gutenberg-modals'
					) }
					initialOpen
				>
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
									'Custom URL',
									'pikari-gutenberg-modals'
								),
								value: 'url',
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
								contentSource: value,
							} )
						}
					/>

					{ contentSource === 'link' && detectedLinks.length === 0 && (
						<Notice status="warning" isDismissible={ false }>
							{ __(
								'No links found. Add a block with a link (button, heading, image, etc.) to use as the modal trigger.',
								'pikari-gutenberg-modals'
							) }
						</Notice>
					) }

					{ contentSource === 'link' && detectedLinks.length > 0 && (
						<SelectControl
							__nextHasNoMarginBottom
							label={ __(
								'Primary Link',
								'pikari-gutenberg-modals'
							) }
							help={ __(
								'This link determines the modal content. Its clickable area will expand to cover the entire block.',
								'pikari-gutenberg-modals'
							) }
							value={ primaryLinkId }
							options={ detectedLinks.map( ( link ) => ( {
								label: link.label,
								value: JSON.stringify( link.identifier ),
							} ) ) }
							onChange={ ( value ) =>
								setAttributes( {
									primaryLinkId: value,
								} )
							}
						/>
					) }

					{ isUrl && (
						<LinkControl
							value={ directUrl ? { url: directUrl } : undefined }
							onChange={ ( link ) =>
								setAttributes( { directUrl: link?.url || '' } )
							}
							settings={ [] }
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
							value={ inlineAnchor }
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
									inlineAnchor: value,
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
							value={ modalSize }
							options={ MODAL_SIZE_OPTIONS }
							onChange={ ( value ) =>
								setAttributes( { modalSize: value } )
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
							value={ templatePart }
							options={ templateParts.options }
							onChange={ ( value ) =>
								setAttributes( {
									templatePart: value,
								} )
							}
						/>
					) }
					{ ! templateParts.isValidSelection( templatePart ) && (
						<Notice
							status="warning"
							onRemove={ () =>
								setAttributes( {
									templatePart: '',
								} )
							}
						>
							{ sprintf(
								/* translators: %s: template part slug */
								__( 'The modal template "%s" no longer exists. The default template will be used.', 'pikari-gutenberg-modals' ),
								templatePart
							) }
						</Notice>
					) }
				</PanelBody>
			</InspectorControls>
		</>
	);
}
