/**
 * WordPress dependencies
 */
import { registerFormatType } from '@wordpress/rich-text';
import { __ } from '@wordpress/i18n';
import ModalLinkEdit from './modal-link-edit';

// Register the modal link format as a span with data attributes
registerFormatType( 'modal-toolbar-button/modal-link', {
	title: __( 'Modal Link', 'pikari-gutenberg-modals' ),
	tagName: 'span',
	className: 'modal-link-trigger',
	attributes: {
		'data-modal-link': 'data-modal-link',
		'data-modal-content-type': 'data-modal-content-type',
		'data-modal-content-id': 'data-modal-content-id',
	},
	edit: ModalLinkEdit,
} );
