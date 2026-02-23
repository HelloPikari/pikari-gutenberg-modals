/**
 * WordPress dependencies
 */
import { registerFormatType } from '@wordpress/rich-text';
import { __ } from '@wordpress/i18n';
import ModalTriggerEdit from './modal-trigger-edit';

// Register the modal trigger format as a span with data attributes
registerFormatType( 'modal-toolbar-button/modal-trigger', {
	title: __( 'Modal Trigger', 'pikari-gutenberg-modals' ),
	tagName: 'span',
	className: 'modal-trigger',
	attributes: {
		'data-modal-trigger': 'data-modal-trigger',
		'data-modal-content-type': 'data-modal-content-type',
		'data-modal-content-id': 'data-modal-content-id',
		'data-modal-size': 'data-modal-size',
		'data-modal-template-part': 'data-modal-template-part',
		'data-modal-action': 'data-modal-action',
	},
	edit: ModalTriggerEdit,
} );
