/**
 * WordPress dependencies
 */
import { useBlockProps } from '@wordpress/block-editor';
import { Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export default function Edit() {
	const blockProps = useBlockProps( {
		className: 'modal-close',
		'aria-label': __( 'Close modal', 'pikari-gutenberg-modals' ),
		disabled: true,
	} );

	return (
		<>
			<Notice
				status="warning"
				isDismissible={ false }
			>
				{ __(
					'This block is deprecated. Use a Modal Trigger block with "Close modal" action instead.',
					'pikari-gutenberg-modals'
				) }
			</Notice>
			<button { ...blockProps }>
				<svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round"><line x1="18" y1="6" x2="6" y2="18" /><line x1="6" y1="6" x2="18" y2="18" /></svg>
			</button>
		</>
	);
}
