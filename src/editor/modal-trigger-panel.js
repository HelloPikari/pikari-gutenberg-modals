/**
 * The single "Modal" panel shown on every supported trigger block.
 *
 * Supersedes the two near-identical panels in button-modal-extension.js and
 * group-modal-trigger-extension.js.
 */

import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import {
	InspectorControls,
	useBlockEditingMode,
} from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';
import { useEffect, useMemo } from '@wordpress/element';
import {
	Notice,
	PanelBody,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { isTriggerBlock, hasModalAction } from './trigger-blocks';
import findLinksInBlocks from './find-links-in-blocks';
import useModalContentBlocks from './use-modal-content-blocks';
import useModalTemplateParts from './use-modal-template-parts';

// Modal sizes from PHP filter (pikari_gutenberg_modals_modal_sizes). Read
// once at module scope, same as the deleted group/button extensions this
// panel replaces — see their MODAL_SIZE_OPTIONS constants.
const MODAL_SIZE_OPTIONS = window.pikariGutenbergModals?.modalSizes || [
	{ label: __( 'Default', 'pikari-gutenberg-modals' ), value: '' },
	{ label: __( 'Small', 'pikari-gutenberg-modals' ), value: 'small' },
	{ label: __( 'Large', 'pikari-gutenberg-modals' ), value: 'large' },
	{ label: __( 'Fullscreen', 'pikari-gutenberg-modals' ), value: 'fullscreen' },
];

const withModalPanel = createHigherOrderComponent( ( BlockEdit ) => {
	return ( props ) => {
		const { name, attributes, setAttributes, isSelected, clientId } = props;

		if ( ! isTriggerBlock( name ) ) {
			return <BlockEdit { ...props } />;
		}

		const {
			pikariModalAction,
			pikariModalContentSource,
			pikariModalDirectUrl,
			pikariModalPrimaryLinkId,
			pikariModalInlineAnchor,
			pikariModalPlacement,
			pikariModalSize,
			pikariModalTemplatePart,
			pikariModalAccessibleLabel,
		} = attributes;

		const templateParts = useModalTemplateParts();
		const modalContentBlocks = useModalContentBlocks();

		// Don't expose block settings in contentOnly editing mode (e.g.
		// locked patterns) — that's a WordPress convention. Unlike the old
		// group-modal-trigger-extension.js, this does NOT also hide the panel
		// inside a modal template part: close-mode triggers live there by
		// design and need their Action control to stay reachable.
		const blockEditingMode = useBlockEditingMode();
		const isContentOnly = blockEditingMode === 'contentOnly';

		const isOpen = pikariModalAction === 'open';
		const contentSource = pikariModalContentSource || 'link';
		const isLinkSource = name === 'core/group' && contentSource === 'link';
		const isInlineSource = contentSource === 'inline';

		// A core/button rendered as a <button> (tagName: 'button') has no
		// href of its own, so "Detected link" — the button's own URL —
		// has nothing to detect. See includes/BlockSupport.php's
		// filter_button_block(): with no url attribute and no <a> in the
		// markup to fall back to, the trigger silently does nothing.
		const isButtonWithoutLink =
			name === 'core/button' && attributes.tagName === 'button';

		const contentSourceOptions = [
			! isButtonWithoutLink && {
				label: __( 'Detected link', 'pikari-gutenberg-modals' ),
				value: 'link',
			},
			{
				label: __( 'Custom URL', 'pikari-gutenberg-modals' ),
				value: 'url',
			},
			{
				label: __( 'Inline content', 'pikari-gutenberg-modals' ),
				value: 'inline',
			},
		].filter( Boolean );

		// Detected-link picker (core/group only): find links in the group's
		// inner blocks. A core/button's own URL is its detected link, so it
		// needs no picker — see includes/BlockSupport.php.
		const innerBlocks = useSelect(
			( select ) => select( 'core/block-editor' ).getBlocks( clientId ),
			[ clientId ]
		);

		const detectedLinks = useMemo( () => {
			if ( ! isLinkSource ) {
				return [];
			}
			return findLinksInBlocks( innerBlocks );
		}, [ innerBlocks, isLinkSource ] );

		// Auto-select the first detected link once the block is set to open
		// a modal and nothing has been chosen yet.
		useEffect( () => {
			if (
				isOpen &&
				isLinkSource &&
				! pikariModalPrimaryLinkId &&
				detectedLinks.length > 0
			) {
				setAttributes( {
					pikariModalPrimaryLinkId: JSON.stringify(
						detectedLinks[ 0 ].identifier
					),
				} );
			}
		}, [
			isOpen,
			isLinkSource,
			pikariModalPrimaryLinkId,
			detectedLinks,
			setAttributes,
		] );

		// Clear the selection if the previously selected link no longer exists.
		useEffect( () => {
			if (
				isOpen &&
				isLinkSource &&
				pikariModalPrimaryLinkId &&
				detectedLinks.length > 0
			) {
				try {
					const selectedIdentifier = JSON.parse(
						pikariModalPrimaryLinkId
					);

					let stillExists;
					if ( selectedIdentifier.linkType === 'post-link' ) {
						// For post-link blocks, check by block name and link type.
						stillExists = detectedLinks.some(
							( link ) =>
								link.identifier.blockName ===
									selectedIdentifier.blockName &&
								link.identifier.linkType === 'post-link'
						);
					} else {
						// For URL-based links, check by URL.
						stillExists = detectedLinks.some(
							( link ) =>
								link.identifier.linkUrl ===
								selectedIdentifier.linkUrl
						);
					}

					if ( ! stillExists ) {
						// Selected link was removed, clear the selection.
						setAttributes( { pikariModalPrimaryLinkId: '' } );
					}
				} catch ( e ) {
					// Invalid JSON, clear it.
					setAttributes( { pikariModalPrimaryLinkId: '' } );
				}
			}
		}, [
			isOpen,
			isLinkSource,
			pikariModalPrimaryLinkId,
			detectedLinks,
			setAttributes,
		] );

		// A core/button with no href (tagName: 'button') can't use
		// "Detected link" — the option is hidden from the Content select
		// above, but the stored attribute still defaults to 'link'. Move it
		// off that dead value automatically rather than leaving the trigger
		// silently inert.
		useEffect( () => {
			if ( isOpen && isButtonWithoutLink && contentSource === 'link' ) {
				setAttributes( { pikariModalContentSource: 'url' } );
			}
		}, [ isOpen, isButtonWithoutLink, contentSource, setAttributes ] );

		return (
			<>
				<BlockEdit { ...props } />
				{ isSelected && ! isContentOnly && (
					<InspectorControls>
						<PanelBody
							title={ __( 'Modal', 'pikari-gutenberg-modals' ) }
							initialOpen={ hasModalAction( attributes ) }
						>
							<SelectControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								label={ __( 'Action', 'pikari-gutenberg-modals' ) }
								value={ pikariModalAction }
								options={ [
									{ label: __( 'None', 'pikari-gutenberg-modals' ), value: '' },
									{
										label: __( 'Open a modal', 'pikari-gutenberg-modals' ),
										value: 'open',
									},
									{
										label: __( 'Close the modal', 'pikari-gutenberg-modals' ),
										value: 'close',
									},
								] }
								onChange={ ( value ) =>
									setAttributes( { pikariModalAction: value } )
								}
								help={ __(
									'Close is for use inside a modal template part.',
									'pikari-gutenberg-modals'
								) }
							/>

							{ isOpen && (
								<>
									<SelectControl
										__next40pxDefaultSize
										__nextHasNoMarginBottom
										label={ __( 'Content', 'pikari-gutenberg-modals' ) }
										value={ contentSource }
										options={ contentSourceOptions }
										onChange={ ( value ) =>
											setAttributes( { pikariModalContentSource: value } )
										}
									/>

									{ isLinkSource && detectedLinks.length === 0 && (
										<Notice status="warning" isDismissible={ false }>
											{ __(
												'No links found in this group. Add a block with a link (button, heading, image, etc.) to use as the modal trigger.',
												'pikari-gutenberg-modals'
											) }
										</Notice>
									) }

									{ isLinkSource && detectedLinks.length > 0 && (
										<SelectControl
											__next40pxDefaultSize
											__nextHasNoMarginBottom
											label={ __( 'Primary link', 'pikari-gutenberg-modals' ) }
											help={ __(
												'This link determines the modal content. Its clickable area will expand to cover the entire group.',
												'pikari-gutenberg-modals'
											) }
											value={ pikariModalPrimaryLinkId }
											options={ detectedLinks.map( ( link ) => ( {
												label: link.label,
												value: JSON.stringify( link.identifier ),
											} ) ) }
											onChange={ ( value ) =>
												setAttributes( { pikariModalPrimaryLinkId: value } )
											}
										/>
									) }

									{ contentSource === 'url' && (
										<TextControl
											__next40pxDefaultSize
											__nextHasNoMarginBottom
											label={ __( 'URL', 'pikari-gutenberg-modals' ) }
											value={ pikariModalDirectUrl }
											onChange={ ( value ) =>
												setAttributes( { pikariModalDirectUrl: value } )
											}
										/>
									) }

									{ isInlineSource && modalContentBlocks.length === 0 && (
										<Notice status="warning" isDismissible={ false }>
											{ __(
												'No Modal Content blocks found on this page. Add a Modal Content block first.',
												'pikari-gutenberg-modals'
											) }
										</Notice>
									) }

									{ isInlineSource && modalContentBlocks.length > 0 && (
										<SelectControl
											__next40pxDefaultSize
											__nextHasNoMarginBottom
											label={ __( 'Modal content', 'pikari-gutenberg-modals' ) }
											value={ pikariModalInlineAnchor }
											options={ [
												{
													label: __( 'Select…', 'pikari-gutenberg-modals' ),
													value: '',
												},
												...modalContentBlocks.map( ( block ) => ( {
													label: block.title,
													value: block.anchor,
												} ) ),
											] }
											onChange={ ( value ) =>
												setAttributes( { pikariModalInlineAnchor: value } )
											}
										/>
									) }

									<SelectControl
										__next40pxDefaultSize
										__nextHasNoMarginBottom
										label={ __( 'Placement', 'pikari-gutenberg-modals' ) }
										value={ pikariModalPlacement }
										options={ [
											{
												label: __(
													'From the template',
													'pikari-gutenberg-modals'
												),
												value: '',
											},
											{
												label: __( 'Left panel', 'pikari-gutenberg-modals' ),
												value: 'left',
											},
											{
												label: __( 'Right panel', 'pikari-gutenberg-modals' ),
												value: 'right',
											},
										] }
										onChange={ ( value ) =>
											setAttributes( { pikariModalPlacement: value } )
										}
									/>

									<SelectControl
										__next40pxDefaultSize
										__nextHasNoMarginBottom
										label={ __( 'Size', 'pikari-gutenberg-modals' ) }
										value={ pikariModalSize }
										options={ MODAL_SIZE_OPTIONS }
										onChange={ ( value ) =>
											setAttributes( { pikariModalSize: value } )
										}
									/>

									{ templateParts.hasMultiple && (
										<SelectControl
											__next40pxDefaultSize
											__nextHasNoMarginBottom
											label={ __( 'Modal template', 'pikari-gutenberg-modals' ) }
											value={ pikariModalTemplatePart }
											options={ templateParts.options }
											onChange={ ( value ) =>
												setAttributes( { pikariModalTemplatePart: value } )
											}
										/>
									) }

									{ ! isLinkSource && (
										<TextControl
											__next40pxDefaultSize
											__nextHasNoMarginBottom
											label={ __( 'Accessible label', 'pikari-gutenberg-modals' ) }
											value={ pikariModalAccessibleLabel }
											onChange={ ( value ) =>
												setAttributes( { pikariModalAccessibleLabel: value } )
											}
											help={ __(
												'Overrides the label announced to screen readers.',
												'pikari-gutenberg-modals'
											) }
										/>
									) }
								</>
							) }
						</PanelBody>
					</InspectorControls>
				) }
			</>
		);
	};
}, 'withModalPanel' );

addFilter(
	'editor.BlockEdit',
	'pikari-gutenberg-modals/modal-panel',
	withModalPanel
);
