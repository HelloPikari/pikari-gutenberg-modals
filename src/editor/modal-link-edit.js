/**
 * WordPress dependencies
 */
import { Popover } from '@wordpress/components';
import { LinkControl, RichTextToolbarButton } from '@wordpress/block-editor';
import { useState, useMemo, useRef } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { external } from '@wordpress/icons';
import { applyFormat, removeFormat } from '@wordpress/rich-text';

const MODAL_FORMAT_NAME = 'modal-toolbar-button/modal-link';

/**
 * Modal Link Edit Component
 * 
 * Provides the UI for adding/editing modal links in the block editor.
 * Integrates with WordPress's RichText format API to apply modal link
 * formatting to selected text.
 * 
 * @param {Object} props - Component properties
 * @param {boolean} props.isActive - Whether the format is currently active
 * @param {Object} props.value - RichText value object
 * @param {Function} props.onChange - Callback to update the RichText value
 * @param {Object} props.contentRef - Reference to the content element
 * @returns {JSX.Element} The modal link edit UI
 */
const ModalLinkEdit = ({ isActive, value, onChange, contentRef }) => {
    const [isOpen, setIsOpen] = useState(false);
    const [linkValue, setLinkValue] = useState(null);
    const buttonRef = useRef();
    
    // Get the currently selected block to check if we should show the button
    const selectedBlock = useSelect((select) => {
        return select('core/block-editor').getSelectedBlock();
    }, []);
    
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
    const isBlockSupported = selectedBlock && supportedBlocks.includes(selectedBlock.name);
    
    // Don't render anything if the block doesn't support modal links
    if (!isBlockSupported) {
        return null;
    }
    
    /**
     * Extract existing modal link data when the format becomes active.
     * This allows editing of previously created modal links.
     */
    useMemo(() => {
        if (!isActive || !value.activeFormats) {
            return;
        }

        const activeFormat = value.activeFormats.find(
            format => format.type === MODAL_FORMAT_NAME
        );
        
        if (!activeFormat?.attributes) {
            return;
        }

        try {
            // Try to parse the JSON data stored in the format
            const modalData = JSON.parse(activeFormat.attributes['data-modal-link'] || '{}');
            setLinkValue(modalData);
        } catch (error) {
            // Fallback for legacy format or corrupted data
            console.warn('Failed to parse modal link data:', error);
            setLinkValue({
                url: activeFormat.attributes['data-modal-content-id'] || '',
                type: activeFormat.attributes['data-modal-content-type'] || 'post',
            });
        }
    }, [isActive, value.activeFormats]);
    
    const openModal = () => {
        setIsOpen(true);
    };
    
    const closeModal = () => {
        setIsOpen(false);
    };
    
    /**
     * Handle form submission when user selects/enters a link.
     * 
     * @param {Object} newValue - The link value from LinkControl
     * @param {string} newValue.url - The URL (external) or slug (internal)
     * @param {number} [newValue.id] - Post ID for internal links
     * @param {string} [newValue.type] - Post type for internal links
     * @param {string} [newValue.title] - Title of the linked content
     */
    const onSubmit = (newValue) => {
        // If no URL provided, remove the format entirely
        if (!newValue || !newValue.url) {
            onChange(removeFormat(value, MODAL_FORMAT_NAME));
            closeModal();
            return;
        }
        
        // Determine content type: 'url' for external links, post type for internal
        let contentType = 'url';
        let contentId = newValue.url;
        
        if (newValue.id) {
            // Internal WordPress content (post, page, etc.)
            contentType = newValue.type || 'post';
            contentId = newValue.id;
        }
        
        // Prepare complete link data for storage
        // This preserves all information needed for display without additional queries
        const linkData = {
            ...newValue,
            title: newValue.title || newValue.url
        };
        
        // Create the format object with all necessary attributes
        const format = {
            type: MODAL_FORMAT_NAME,
            attributes: {
                'data-modal-link': JSON.stringify(linkData), // Full data for editor display
                'data-modal-content-type': contentType,      // Quick access for backend
                'data-modal-content-id': String(contentId),  // ID or URL for backend
                'href': '#',                                 // Prevents default link behavior
            },
        };
        
        onChange(applyFormat(value, format));
        closeModal();
    };
    
    const onRemove = () => {
        onChange(removeFormat(value, MODAL_FORMAT_NAME));
        closeModal();
    };
    
    return (
        <>
            <RichTextToolbarButton
                ref={buttonRef}
                icon={external}
                title={__('Modal Link', 'pikari-gutenberg-modals')}
                onClick={openModal}
                isActive={isActive}
                className="modal-toolbar-button"
                shortcutType="primary"
                shortcutCharacter="m"
            />
            {isOpen && (
                <Popover
                    onClose={closeModal}
                    position="bottom center"
                    className="modal-link-popover"
                    anchorRef={contentRef}
                    focusOnMount="firstElement"
                >
                    <LinkControl
                        searchInputPlaceholder={__('Search or enter URL', 'pikari-gutenberg-modals')}
                        value={linkValue}
                        onChange={onSubmit}
                        onRemove={onRemove}
                        showInitialSuggestions={true}
                        showSuggestions={true}
                        settings={[]}
                    />
                </Popover>
            )}
        </>
    );
};

// Format type registration moved to modal-format.js

export default ModalLinkEdit;