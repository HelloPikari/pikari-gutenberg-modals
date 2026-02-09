/**
 * WordPress dependencies
 */
import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

export default function Edit() {
	const blockProps = useBlockProps( {
		className: 'modal-close',
		'aria-label': __( 'Close modal', 'pikari-gutenberg-modals' ),
		disabled: true,
	} );

	return (
		<button { ...blockProps }>
			<span aria-hidden="true">&times;</span>
		</button>
	);
}
