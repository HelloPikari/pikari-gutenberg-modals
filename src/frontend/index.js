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
    
    const modalId = modalTrigger.dataset[CONFIG.attributes.modalId];
    
    if (!modalId) {
        console.warn('Modal trigger missing data-modal-id attribute:', modalTrigger);
        return;
    }
    
    openModal(modalId);
}

/**
 * Open a modal by ID.
 * 
 * Attempts to use Alpine.js if available, falls back to vanilla JS.
 * 
 * @param {string} modalId - The modal element ID
 */
function openModal(modalId) {
    // Check if Alpine.js is available
    if (isAlpineAvailable()) {
        openModalWithAlpine(modalId);
    } else {
        openModalFallback(modalId);
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
 * @param {string} modalId - The modal element ID
 */
function openModalWithAlpine(modalId) {
    window.dispatchEvent(new CustomEvent(CONFIG.events.openModal, {
        detail: { id: modalId }
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
 * @param {string} modalId - The modal element ID
 */
function openModalFallback(modalId) {
    const modal = document.getElementById(modalId);
    
    if (!modal) {
        console.error(`Modal with ID "${modalId}" not found`);
        return;
    }
    
    // Show the modal
    showModal(modal);
    
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
    
    // Return focus to the trigger element
    returnFocusToTrigger(modal);
}

/**
 * Return focus to the element that triggered the modal.
 * 
 * @param {HTMLElement} modal - The modal element
 */
function returnFocusToTrigger(modal) {
    const triggerId = modal.id;
    const trigger = document.querySelector(`[data-modal-id="${triggerId}"]`);
    
    if (trigger) {
        trigger.focus();
    }
}