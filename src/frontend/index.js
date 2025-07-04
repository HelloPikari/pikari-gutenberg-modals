/**
 * Frontend Modal Handler
 * 
 * Manages modal triggers and display on the frontend.
 * Supports both Alpine.js (preferred) and vanilla JS fallback.
 * 
 * @package PikariGutenbergModals
 */
import './style.scss';

// Configuration constants
const CONFIG = {
    selectors: {
        trigger: '.has-modal-link, .modal-link-trigger',
        modal: '.modal-overlay',
        closeButton: '.modal-close',
    },
    classes: {
        open: 'is-open',
    },
    keys: {
        escape: 'Escape',
        enter: 'Enter',
        space: ' ',
    },
    events: {
        openModal: 'open-modal',
    },
    attributes: {
        modalId: 'modalId',
        listenersAdded: 'listenersAdded',
    }
};

/**
 * Initialize modal functionality when DOM is ready.
 */
document.addEventListener('DOMContentLoaded', () => {
    // Set up event delegation for all modal triggers
    initializeModalTriggers();
    
    // Set up keyboard navigation for accessibility
    initializeKeyboardSupport();
});

/**
 * Initialize click handlers for modal triggers.
 */
function initializeModalTriggers() {
    document.addEventListener('click', handleTriggerClick);
}

/**
 * Handle clicks on modal trigger elements.
 * 
 * @param {Event} event - The click event
 */
function handleTriggerClick(event) {
    const modalTrigger = event.target.closest(CONFIG.selectors.trigger);
    
    if (!modalTrigger) {
        return;
    }
    
    event.preventDefault();
    
    // Get content data from trigger element
    const contentType = modalTrigger.dataset.modalContentType;
    const contentId = modalTrigger.dataset.modalContentId;
    
    if (!contentType || !contentId) {
        console.warn('Modal trigger missing content data:', modalTrigger);
        return;
    }
    
    openModal({ contentType, contentId });
}

/**
 * Open a modal with content.
 * 
 * Attempts to use Alpine.js if available, falls back to vanilla JS.
 * 
 * @param {Object} modalData - The modal data (contentType and contentId)
 */
function openModal(modalData) {
    // Check if Alpine.js is available
    if (isAlpineAvailable()) {
        openModalWithAlpine(modalData);
    } else {
        openModalFallback(modalData);
    }
}

/**
 * Check if Alpine.js is available.
 * 
 * @returns {boolean} True if Alpine is defined
 */
function isAlpineAvailable() {
    return typeof Alpine !== 'undefined';
}

/**
 * Open modal using Alpine.js event system.
 * 
 * @param {Object} modalData - The modal data
 */
function openModalWithAlpine(modalData) {
    window.dispatchEvent(new CustomEvent(CONFIG.events.openModal, {
        detail: modalData
    }));
}
/**
 * Initialize keyboard support for accessibility.
 */
function initializeKeyboardSupport() {
    document.addEventListener('keydown', handleKeyboardNavigation);
}

/**
 * Handle keyboard navigation for modal triggers.
 * 
 * Allows Enter and Space keys to activate span triggers.
 * 
 * @param {KeyboardEvent} event - The keyboard event
 */
function handleKeyboardNavigation(event) {
    const { key, target } = event;
    
    // Check if key is Enter or Space
    if (key !== CONFIG.keys.enter && key !== CONFIG.keys.space) {
        return;
    }
    
    // Check if target is a modal trigger span
    const modalTrigger = target.closest(CONFIG.selectors.trigger);
    if (!modalTrigger || modalTrigger.tagName !== 'SPAN') {
        return;
    }
    
    event.preventDefault();
    modalTrigger.click();
}

/**
 * Fallback modal implementation for non-Alpine environments.
 * 
 * @param {Object} modalData - The modal data
 */
async function openModalFallback(modalData) {
    // Get or create the single modal container
    let modal = document.getElementById('pikari-modal');
    
    if (!modal) {
        // Create modal if it doesn't exist
        modal = createModalContainer();
        document.body.appendChild(modal);
    }
    
    // Show loading state
    const modalBody = modal.querySelector('.modal-body');
    modalBody.innerHTML = '<div class="modal-loading">Loading...</div>';
    
    // Show the modal
    showModal(modal);
    
    try {
        // Fetch content via AJAX
        const apiUrl = window.pikariModalsData?.apiUrl || '/wp-json/pikari-gutenberg-modals/v1/modal-content/';
        const response = await fetch(`${apiUrl}${modalData.contentId}`);
        const data = await response.json();
        
        if (!response.ok) {
            throw new Error(data.message || 'Failed to load content');
        }
        
        // Update modal content with inline styles
        modalBody.innerHTML = `
            ${data.styles ? `<style>${data.styles}</style>` : ''}
            <article class="modal-post-content">
                <header class="modal-post-header">
                    <h2>${escapeHtml(data.title)}</h2>
                </header>
                <div class="modal-post-body">
                    ${data.content}
                </div>
            </article>
        `;
        
    } catch (error) {
        console.error('Error loading modal content:', error);
        modalBody.innerHTML = '<div class="modal-error">Error loading content. Please try again.</div>';
    }
    
    // Set up event listeners if not already done
    if (!modal.dataset[CONFIG.attributes.listenersAdded]) {
        setupModalListeners(modal);
    }
}

/**
 * Show a modal element.
 * 
 * @param {HTMLElement} modal - The modal element
 */
function showModal(modal) {
    modal.style.display = 'flex';
    modal.classList.add(CONFIG.classes.open);
    
    // Focus the close button for accessibility
    const closeBtn = modal.querySelector(CONFIG.selectors.closeButton);
    if (closeBtn) {
        closeBtn.focus();
    }
}

/**
 * Set up event listeners for a modal.
 * 
 * @param {HTMLElement} modal - The modal element
 */
function setupModalListeners(modal) {
    // Mark listeners as added
    modal.dataset[CONFIG.attributes.listenersAdded] = 'true';
    
    // Close on backdrop click
    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            closeModalFallback(modal);
        }
    });
    
    // Close button click
    const closeBtn = modal.querySelector(CONFIG.selectors.closeButton);
    if (closeBtn) {
        closeBtn.addEventListener('click', () => {
            closeModalFallback(modal);
        });
    }
    
    // Create escape key handler specific to this modal
    const escapeHandler = createEscapeHandler(modal);
    modal._escapeHandler = escapeHandler; // Store reference for cleanup
    document.addEventListener('keydown', escapeHandler);
}

/**
 * Create an escape key handler for a specific modal.
 * 
 * @param {HTMLElement} modal - The modal element
 * @returns {Function} The event handler function
 */
function createEscapeHandler(modal) {
    return (event) => {
        if (event.key === CONFIG.keys.escape && modal.classList.contains(CONFIG.classes.open)) {
            closeModalFallback(modal);
        }
    };
}

/**
 * Close a modal (fallback implementation).
 * 
 * @param {HTMLElement} modal - The modal element
 */
function closeModalFallback(modal) {
    // Hide the modal
    modal.style.display = 'none';
    modal.classList.remove(CONFIG.classes.open);
    
    // Clean up escape handler if it exists
    if (modal._escapeHandler) {
        document.removeEventListener('keydown', modal._escapeHandler);
        delete modal._escapeHandler;
    }
    
    // Clear content
    const modalBody = modal.querySelector('.modal-body');
    if (modalBody) {
        modalBody.innerHTML = '';
    }
    
    // Return focus to the trigger element
    returnFocusToTrigger(modal);
}

/**
 * Return focus to the element that triggered the modal.
 * 
 * @param {HTMLElement} modal - The modal element
 */
function returnFocusToTrigger(modal) {
    // Find the last clicked trigger
    const triggers = document.querySelectorAll(CONFIG.selectors.trigger);
    const lastTrigger = Array.from(triggers).find(el => el === document.activeElement);
    
    if (lastTrigger) {
        lastTrigger.focus();
    }
}

/**
 * Create the modal container for fallback mode.
 * 
 * @returns {HTMLElement} The modal element
 */
function createModalContainer() {
    const modal = document.createElement('div');
    modal.id = 'pikari-modal';
    modal.className = 'modal-overlay';
    modal.innerHTML = `
        <div class="modal-content">
            <button class="modal-close" aria-label="Close modal">
                <span aria-hidden="true">&times;</span>
            </button>
            <div class="modal-body">
                <!-- Content will be loaded here -->
            </div>
        </div>
    `;
    
    return modal;
}

/**
 * Escape HTML to prevent XSS.
 * 
 * @param {string} text - The text to escape
 * @returns {string} Escaped text
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}