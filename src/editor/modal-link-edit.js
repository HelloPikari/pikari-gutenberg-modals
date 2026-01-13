/**
 * WordPress dependencies
 */
import { Popover } from '@wordpress/components';
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
const ModalLinkEdit = ( { isActive, value, onChange, contentRef } ) => {
	const [ addingLink, setAddingLink ] = useState( false );
	// Track what opened the popover: 'toolbar' or 'click'
	const [ openedBy, setOpenedBy ] = useState( null );

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
		const format = {
			type: MODAL_FORMAT_NAME,
			attributes: {
				'data-modal-link': JSON.stringify( linkData ), // Full data for editor display
				'data-modal-content-type': contentType, // Quick access for backend
				'data-modal-content-id': String( contentId ), // ID or URL for backend
				href: '#', // Prevents default link behavior
			},
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
				</Popover>
			) }
		</>
	);
};

// Format type registration moved to modal-format.js

export default ModalLinkEdit;
