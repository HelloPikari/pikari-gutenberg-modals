/**
 * WordPress dependencies
 */
import { useBlockProps } from '@wordpress/block-editor';
import { Placeholder } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export default function Edit() {
	const blockProps = useBlockProps( {
		className: 'wp-block-pikari-gutenberg-modals-content-area',
	} );

	return (
		<div { ...blockProps }>
			<Placeholder
				label={ __( 'Modal Content Area', 'pikari-gutenberg-modals' ) }
				instructions={ __(
					'Modal content will be dynamically loaded here when a trigger is clicked.',
					'pikari-gutenberg-modals'
				) }
			/>
		</div>
	);
}
