/**
 * WordPress dependencies
 */
import { unregisterBlockType } from '@wordpress/blocks';
import domReady from '@wordpress/dom-ready';
import { toggleFormat, applyFormat, removeFormat } from '@wordpress/rich-text';
import './modal-format';
import './button-modal-extension';
import './group-modal-trigger-extension';
import './style.scss';

/**
 * Hide post-content-only blocks from the Site Editor.
 *
 * Modal Content is only useful in post/page content, not in template parts.
 * Modal Trigger is kept registered because it supports close mode inside
 * modal template parts. window.pagenow is set by WordPress core on all
 * admin pages ('site-editor' for the Site Editor, 'post'/'page' for post editors).
 */
domReady( () => {
	if ( window.pagenow === 'site-editor' ) {
		unregisterBlockType( 'pikari-gutenberg-modals/modal-content' );
	}
} );

/**
 * Export utility functions for the edit component
 */
export { applyFormat, removeFormat, toggleFormat };
