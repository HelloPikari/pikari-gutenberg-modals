/**
 * WordPress dependencies
 */
import { useBlockProps, InnerBlocks } from '@wordpress/block-editor';
import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

export default function Edit( { attributes, setAttributes } ) {
	const blockProps = useBlockProps();
	const { title, anchor } = attributes;

	// Auto-generate a stable anchor on block creation. This persists in the
	// block comment so render.php can read it from $attributes['anchor'].
	// Without this, the server fallback (wp_unique_id) won't match the editor.
	useEffect( () => {
		if ( ! anchor ) {
			const id = Math.random().toString( 36 ).substring( 2, 10 );
			setAttributes( { anchor: `modal-content-${ id }` } );
		}
	}, [ anchor, setAttributes ] );

	return (
		<div { ...blockProps }>
			<div className="modal-content-header">
				<span className="modal-content-label">
					{ __( 'Modal Content', 'pikari-gutenberg-modals' ) }
				</span>
				<input
					className="modal-content-title"
					type="text"
					placeholder={ __(
						'Title (for trigger selection)',
						'pikari-gutenberg-modals'
					) }
					value={ title }
					onChange={ ( event ) =>
						setAttributes( { title: event.target.value } )
					}
				/>
				{ anchor && (
					<span className="modal-content-anchor">
						#{ anchor }
					</span>
				) }
			</div>
			<InnerBlocks />
		</div>
	);
}
