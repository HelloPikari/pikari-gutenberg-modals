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
import { useSelect, useDispatch } from '@wordpress/data';
import { useMemo, useEffect } from '@wordpress/element';
import findLinksInBlocks from '../../editor/find-links-in-blocks';
import useModalContentBlocks from '../../editor/use-modal-content-blocks';
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
		triggerAction,
		contentSource,
		primaryLinkId,
		directUrl,
		inlineAnchor,
		modalSize,
		templatePart,
	} = attributes;

	const isCloseMode = triggerAction === 'close';

	const blockProps = useBlockProps();
	const innerBlocksProps = useInnerBlocksProps( blockProps );

	const isInline = contentSource === 'inline';
	const isUrl = contentSource === 'url';
	const modalContentBlocks = useModalContentBlocks();
	const templateParts = useModalTemplateParts();

	const blockEditingMode = useBlockEditingMode();
	const isContentOnly = blockEditingMode === 'contentOnly';

	const { updateBlockAttributes } = useDispatch( blockEditorStore );

	// Get inner blocks for link detection
	const innerBlocks = useSelect(
		( select ) => {
			return select( blockEditorStore ).getBlocks( clientId );
		},
		[ clientId ]
	);

	// Detect links in inner blocks (open mode: link detection, close mode: element detection)
	const detectedLinks = useMemo( () => {
		if ( isCloseMode ) {
			return findLinksInBlocks( innerBlocks, '', { includeButtonsWithoutUrl: true } );
		}
		if ( isInline || isUrl ) {
			return [];
		}
		return findLinksInBlocks( innerBlocks );
	}, [ innerBlocks, isInline, isUrl, isCloseMode ] );

	// Auto-select first link when no link is selected (link mode only, not close mode)
	useEffect( () => {
		if (
			! isCloseMode &&
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
	}, [ isCloseMode, contentSource, primaryLinkId, detectedLinks, setAttributes ] );

	// Clear selection if the selected link/element no longer exists
	useEffect( () => {
		if ( ! primaryLinkId || detectedLinks.length === 0 ) {
			return;
		}

		if ( isCloseMode ) {
			// Close mode: match by anchor (persistent) or clientId (fresh selection)
			try {
				const identifier = JSON.parse( primaryLinkId );
				let stillExists = false;

				if ( identifier.closeTriggerId ) {
					// Anchor-based match (persists across page loads)
					stillExists = detectedLinks.some(
						( el ) => el.identifier.anchor === identifier.closeTriggerId
					);
				} else if ( identifier.clientId ) {
					// ClientId-based match (fresh selection before save)
					stillExists = detectedLinks.some(
						( el ) => el.identifier.clientId === identifier.clientId
					);
				} else {
					return;
				}

				if ( ! stillExists ) {
					setAttributes( { primaryLinkId: '' } );
				}
			} catch {
				setAttributes( { primaryLinkId: '' } );
			}
			return;
		}

		// Open mode: match by URL or block name
		if ( contentSource !== 'link' ) {
			return;
		}
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
	}, [ isCloseMode, contentSource, primaryLinkId, detectedLinks, setAttributes ] );

	// Don't show controls if in contentOnly mode
	if ( isContentOnly ) {
		return <div { ...innerBlocksProps } />;
	}

	// Helper to get selected clientId for close mode SelectControl
	const getSelectedCloseClientId = () => {
		if ( ! primaryLinkId ) {
			return '';
		}
		try {
			const identifier = JSON.parse( primaryLinkId );
			if ( identifier.closeTriggerId ) {
				// Find by anchor (persistent across page loads)
				const match = detectedLinks.find(
					( el ) => el.identifier.anchor === identifier.closeTriggerId
				);
				return match ? match.identifier.clientId : '';
			}
			return identifier.clientId || '';
		} catch {
			return '';
		}
	};

	// Handle close-mode element selection change
	const handleCloseElementChange = ( selectedClientId ) => {
		// Clear previous anchor from old child block
		if ( primaryLinkId ) {
			try {
				const prev = JSON.parse( primaryLinkId );
				if ( prev.clientId ) {
					updateBlockAttributes( prev.clientId, { anchor: '' } );
				}
			} catch {
				// Ignore invalid JSON
			}
		}

		if ( ! selectedClientId ) {
			setAttributes( { primaryLinkId: '' } );
			return;
		}

		// Generate unique anchor ID for the child element
		const closeTriggerId = `modal-close-${ selectedClientId.substring( 0, 8 ) }`;

		// Set anchor attribute on the child block
		updateBlockAttributes( selectedClientId, { anchor: closeTriggerId } );

		// Store the close trigger identifier
		setAttributes( {
			primaryLinkId: JSON.stringify( { closeTriggerId, clientId: selectedClientId } ),
		} );
	};

	// Handle trigger action mode switch
	const handleTriggerActionChange = ( value ) => {
		// Clear mode-specific attributes when switching
		if ( value === 'close' ) {
			setAttributes( {
				triggerAction: value,
				primaryLinkId: '',
				directUrl: '',
				inlineAnchor: '',
				modalSize: '',
				templatePart: '',
			} );
		} else {
			// Switching to open: clear close-mode anchor from child block
			if ( primaryLinkId ) {
				try {
					const prev = JSON.parse( primaryLinkId );
					if ( prev.clientId ) {
						updateBlockAttributes( prev.clientId, { anchor: '' } );
					}
				} catch {
					// Ignore
				}
			}
			setAttributes( {
				triggerAction: value,
				primaryLinkId: '',
			} );
		}
	};

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
						'Trigger Settings',
						'pikari-gutenberg-modals'
					) }
					initialOpen
				>
					<SelectControl
						__nextHasNoMarginBottom
						label={ __(
							'Action',
							'pikari-gutenberg-modals'
						) }
						value={ triggerAction }
						options={ [
							{
								label: __(
									'Open Modal',
									'pikari-gutenberg-modals'
								),
								value: 'open',
							},
							{
								label: __(
									'Close Modal',
									'pikari-gutenberg-modals'
								),
								value: 'close',
							},
						] }
						onChange={ handleTriggerActionChange }
					/>

					{ isCloseMode && detectedLinks.length > 0 && (
						<SelectControl
							__nextHasNoMarginBottom
							label={ __(
								'Close Trigger Element',
								'pikari-gutenberg-modals'
							) }
							help={ __(
								'Select which element triggers closing the modal. If none selected, the entire block is the close trigger.',
								'pikari-gutenberg-modals'
							) }
							value={ getSelectedCloseClientId() }
							options={ [
								{
									label: __(
										'Entire block',
										'pikari-gutenberg-modals'
									),
									value: '',
								},
								...detectedLinks.map( ( link ) => ( {
									label: link.label,
									value: link.identifier.clientId,
								} ) ),
							] }
							onChange={ handleCloseElementChange }
						/>
					) }

					{ isCloseMode && detectedLinks.length === 0 && (
						<Notice status="info" isDismissible={ false }>
							{ __(
								'The entire block will close the modal on click. Add a button inside to target a specific element.',
								'pikari-gutenberg-modals'
							) }
						</Notice>
					) }

					{ ! isCloseMode && (
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
						</>
					) }
				</PanelBody>
			</InspectorControls>
		</>
	);
}
