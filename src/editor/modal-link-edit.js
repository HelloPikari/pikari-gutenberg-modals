/**
 * WordPress dependencies
 */
/* eslint-disable @wordpress/no-unsafe-wp-apis */
import {
	Popover,
	SelectControl,
	__experimentalHeading as Heading,
} from '@wordpress/components';
/* eslint-enable @wordpress/no-unsafe-wp-apis */
import { LinkControl, RichTextToolbarButton } from '@wordpress/block-editor';
import {
	useState,
	useEffect,
	useCallback,
	useLayoutEffect,
	useMemo,
} from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { external } from '@wordpress/icons';
import { applyFormat, removeFormat, useAnchor } from '@wordpress/rich-text';
import useModalContentBlocks from './use-modal-content-blocks';

const MODAL_FORMAT_NAME = 'modal-toolbar-button/modal-link';

// Format settings for useAnchor hook
const modalLinkFormatSettings = {
	name: MODAL_FORMAT_NAME,
	tagName: 'span',
	className: 'modal-link-trigger',
};

/**
 * Modal Link Edit Component
 *
 * Provides the UI for adding/editing modal links in the block editor.
 * Integrates with WordPress's RichText format API to apply modal link
 * formatting to selected text.
 *
 * @param {Object}   props            - Component properties
 * @param {boolean}  props.isActive   - Whether the format is currently active
 * @param {Object}   props.value      - RichText value object
 * @param {Function} props.onChange   - Callback to update the RichText value
 * @param {Object}   props.contentRef - Reference to the content element
 * @return {JSX.Element} The modal link edit UI
 */
// Modal sizes from PHP filter (pikari_gutenberg_modals_modal_sizes)
const MODAL_SIZE_OPTIONS = window.pikariGutenbergModals?.modalSizes || [
	{ label: __( 'Default', 'pikari-gutenberg-modals' ), value: '' },
	{ label: __( 'Small', 'pikari-gutenberg-modals' ), value: 'small' },
	{ label: __( 'Large', 'pikari-gutenberg-modals' ), value: 'large' },
	{ label: __( 'Fullscreen', 'pikari-gutenberg-modals' ), value: 'fullscreen' },
];

const ModalLinkEdit = ( { isActive, value, onChange, contentRef } ) => {
	const [ addingLink, setAddingLink ] = useState( false );
	// Track what opened the popover: 'toolbar' or 'click'
	const [ openedBy, setOpenedBy ] = useState( null );
	const [ size, setSize ] = useState( '' );
	const [ contentSource, setContentSource ] = useState( 'link' );
	const modalContentBlocks = useModalContentBlocks();

	// Use useAnchor to position popover at the text selection/formatted element
	const popoverAnchor = useAnchor( {
		editableContentElement: contentRef.current,
		settings: {
			...modalLinkFormatSettings,
			isActive,
		},
	} );

	// Get the currently selected block to check if we should show the button
	const selectedBlock = useSelect( ( select ) => {
		return select( 'core/block-editor' ).getSelectedBlock();
	}, [] );

	// Get supported blocks from localized data (provided by PHP)
	const supportedBlocks = window.pikariGutenbergModals?.supportedBlocks || [
		'core/paragraph',
		'core/heading',
		'core/list',
		'core/list-item',
		'core/quote',
		'core/verse',
		'core/preformatted',
		'core/navigation-link',
	];

	// Check if the current block supports modal links
	const isBlockSupported =
		selectedBlock && supportedBlocks.includes( selectedBlock.name );

	/**
	 * Stop adding/editing the link and close the popover.
	 * Handles focus return based on what opened the popover.
	 */
	const stopAddingLink = useCallback( () => {
		setAddingLink( false );
		setOpenedBy( null );
	}, [] );

	/**
	 * Reset popover state when the format becomes inactive.
	 * This handles the case where user clicks away from a modal trigger.
	 */
	useEffect( () => {
		if ( ! isActive && openedBy === 'click' ) {
			stopAddingLink();
		}
	}, [ isActive, openedBy, stopAddingLink ] );

	/**
	 * Compute existing modal link data synchronously during render.
	 * Uses useMemo instead of useEffect to ensure the value is available
	 * immediately when the popover first renders.
	 */
	const linkValue = useMemo( () => {
		if ( ! isActive || ! value.activeFormats ) {
			return null;
		}

		const activeFormat = value.activeFormats.find(
			( format ) => format.type === MODAL_FORMAT_NAME
		);

		if ( ! activeFormat?.attributes ) {
			return null;
		}

		try {
			// Try to parse the JSON data stored in the format
			return JSON.parse(
				activeFormat.attributes[ 'data-modal-link' ] || '{}'
			);
		} catch ( error ) {
			// Fallback for legacy format or corrupted data
			// eslint-disable-next-line no-console
			console.warn( 'Failed to parse modal link data:', error );
			return {
				url: activeFormat.attributes[ 'data-modal-content-id' ] || '',
				type:
					activeFormat.attributes[ 'data-modal-content-type' ] ||
					'post',
			};
		}
	}, [ isActive, value.activeFormats ] );

	// Sync size and content source state when editing an existing modal link
	useEffect( () => {
		if ( isActive && value.activeFormats ) {
			const activeFormat = value.activeFormats.find(
				( format ) => format.type === MODAL_FORMAT_NAME
			);
			setSize( activeFormat?.attributes?.[ 'data-modal-size' ] || '' );
			const existingType =
				activeFormat?.attributes?.[ 'data-modal-content-type' ] || '';
			setContentSource( existingType === 'inline' ? 'inline' : 'link' );
		}
	}, [ isActive, value.activeFormats ] );

	/**
	 * Click detection for existing modal triggers.
	 * When user clicks on a modal trigger in the editor, show the popover.
	 */
	useLayoutEffect( () => {
		const element = contentRef.current;
		if ( ! element ) {
			return;
		}

		function handleClick( event ) {
			// Find if the click target is within a modal trigger
			const modalTrigger = event.target.closest( '.modal-link-trigger' );

			if ( ! modalTrigger ) {
				return;
			}

			// Prevent default behavior and show the popover
			setAddingLink( true );
			setOpenedBy( 'click' );
		}

		element.addEventListener( 'click', handleClick );
		return () => {
			element.removeEventListener( 'click', handleClick );
		};
	}, [ contentRef, stopAddingLink ] );

	// Don't render anything if the block doesn't support modal links
	if ( ! isBlockSupported ) {
		return null;
	}

	/**
	 * Open the link popover from the toolbar button.
	 */
	const openFromToolbar = () => {
		setAddingLink( true );
		setOpenedBy( 'toolbar' );
	};

	/**
	 * Handle focus moving outside the popover.
	 * Close the popover when focus moves elsewhere.
	 */
	const onFocusOutside = () => {
		stopAddingLink();
	};

	/**
	 * Handle form submission when user selects/enters a link.
	 *
	 * @param {Object} newValue         - The link value from LinkControl
	 * @param {string} newValue.url     - The URL (external) or slug (internal)
	 * @param {number} [newValue.id]    - Post ID for internal links
	 * @param {string} [newValue.type]  - Post type for internal links
	 * @param {string} [newValue.title] - Title of the linked content
	 */
	const onSubmit = ( newValue ) => {
		// If no URL provided, remove the format entirely
		if ( ! newValue || ! newValue.url ) {
			onChange( removeFormat( value, MODAL_FORMAT_NAME ) );
			stopAddingLink();
			return;
		}

		// Determine content type: 'url' for external links, post type for internal
		let contentType = 'url';
		let contentId = newValue.url;

		if ( newValue.id ) {
			// Internal WordPress content (post, page, etc.)
			contentType = newValue.type || 'post';
			contentId = newValue.id;
		}

		// Prepare complete link data for storage
		// This preserves all information needed for display without additional queries
		const linkData = {
			...newValue,
			title: newValue.title || newValue.url,
		};

		// Create the format object with all necessary attributes
		const formatAttributes = {
			'data-modal-link': JSON.stringify( linkData ), // Full data for editor display
			'data-modal-content-type': contentType, // Quick access for backend
			'data-modal-content-id': String( contentId ), // ID or URL for backend
			href: '#', // Prevents default link behavior
		};

		// Only include size attribute when not default
		if ( size ) {
			formatAttributes[ 'data-modal-size' ] = size;
		}

		const format = {
			type: MODAL_FORMAT_NAME,
			attributes: formatAttributes,
		};

		onChange( applyFormat( value, format ) );
		stopAddingLink();
	};

	const onRemove = () => {
		onChange( removeFormat( value, MODAL_FORMAT_NAME ) );
		stopAddingLink();
	};

	// Determine if we should show the popover
	// Show when explicitly adding a link (from toolbar or click on existing trigger)
	const showPopover = addingLink;

	return (
		<>
			<RichTextToolbarButton
				icon={ external }
				title={ __( 'Modal Link', 'pikari-gutenberg-modals' ) }
				onClick={ openFromToolbar }
				isActive={ isActive }
				className="modal-toolbar-button"
				shortcutType="primary"
				shortcutCharacter="m"
			/>
			{ showPopover && (
				<Popover
					anchor={ popoverAnchor }
					animate={ false }
					onClose={ stopAddingLink }
					onFocusOutside={ onFocusOutside }
					placement="bottom"
					offset={ 8 }
					shift
					focusOnMount={ openedBy === 'toolbar' ? 'firstElement' : false }
					constrainTabbing
					className="modal-link-popover"
				>
					<Heading level={ 4 }>
						{ ! isActive
							? __( 'Add Modal Link', 'pikari-gutenberg-modals' )
							: __( 'Edit Modal Link', 'pikari-gutenberg-modals' ) }
					</Heading>
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
									'Post / URL',
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
						onChange={ setContentSource }
					/>
					{ contentSource === 'link' && (
						<LinkControl
							searchInputPlaceholder={ __(
								'Search or enter URL',
								'pikari-gutenberg-modals'
							) }
							value={ linkValue }
							onChange={ onSubmit }
							onRemove={ isActive ? onRemove : undefined }
							showInitialSuggestions={ ! isActive }
							showSuggestions
							settings={ [] }
						/>
					) }
					{ contentSource === 'inline' && (
						<SelectControl
							__nextHasNoMarginBottom
							label={ __(
								'Modal Content',
								'pikari-gutenberg-modals'
							) }
							value={
								isActive &&
								value.activeFormats?.find(
									( f ) => f.type === MODAL_FORMAT_NAME
								)?.attributes?.[ 'data-modal-content-type' ] ===
									'inline'
									? value.activeFormats.find(
										( f ) =>
											f.type === MODAL_FORMAT_NAME
									)?.attributes?.[
										'data-modal-content-id'
									] || ''
									: ''
							}
							options={ [
								{
									label: __(
										'Select\u2026',
										'pikari-gutenberg-modals'
									),
									value: '',
								},
								...modalContentBlocks.map( ( block ) => ( {
									label: block.title,
									value: block.anchor,
								} ) ),
							] }
							onChange={ ( anchor ) => {
								if ( ! anchor ) {
									return;
								}
								const block = modalContentBlocks.find(
									( b ) => b.anchor === anchor
								);
								const formatAttributes = {
									'data-modal-link': JSON.stringify( {
										title:
											block?.title ||
											__(
												'Modal Content',
												'pikari-gutenberg-modals'
											),
									} ),
									'data-modal-content-type': 'inline',
									'data-modal-content-id': anchor,
									href: '#' + anchor,
								};
								if ( size ) {
									formatAttributes[ 'data-modal-size' ] =
										size;
								}
								onChange(
									applyFormat( value, {
										type: MODAL_FORMAT_NAME,
										attributes: formatAttributes,
									} )
								);
							} }
						/>
					) }
					<SelectControl
						__nextHasNoMarginBottom
						label={ __( 'Modal Size', 'pikari-gutenberg-modals' ) }
						value={ size }
						options={ MODAL_SIZE_OPTIONS }
						onChange={ ( newSize ) => {
							setSize( newSize );

							// Re-apply format immediately so the change persists
							if ( isActive && value.activeFormats ) {
								const activeFormat = value.activeFormats.find(
									( f ) => f.type === MODAL_FORMAT_NAME
								);
								if ( activeFormat?.attributes ) {
									const updatedAttributes = {
										...activeFormat.attributes,
									};
									if ( newSize ) {
										updatedAttributes[ 'data-modal-size' ] = newSize;
									} else {
										delete updatedAttributes[ 'data-modal-size' ];
									}
									onChange(
										applyFormat( value, {
											type: MODAL_FORMAT_NAME,
											attributes: updatedAttributes,
										} )
									);
								}
							}
						} }
					/>
				</Popover>
			) }
		</>
	);
};

// Format type registration moved to modal-format.js

export default ModalLinkEdit;
