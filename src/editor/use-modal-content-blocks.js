/**
 * Hook to find all Modal Content blocks on the current page.
 *
 * Returns an array of { title, anchor, clientId } objects for use
 * in trigger content source selectors.
 */

import { useSelect } from '@wordpress/data';
import { useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

function findModalContentBlocks( blocks ) {
	const results = [];

	for ( const block of blocks ) {
		if ( block.name === 'pikari-gutenberg-modals/modal-content' ) {
			// Only include blocks with a persisted anchor attribute.
			// The edit component auto-generates one on creation.
			if ( ! block.attributes.anchor ) {
				continue;
			}

			results.push( {
				title:
					block.attributes.title ||
					__( 'Untitled Modal', 'pikari-gutenberg-modals' ),
				anchor: block.attributes.anchor,
				clientId: block.clientId,
			} );
		}

		if ( block.innerBlocks?.length ) {
			results.push( ...findModalContentBlocks( block.innerBlocks ) );
		}
	}

	return results;
}

export default function useModalContentBlocks() {
	const blocks = useSelect( ( select ) => {
		return select( 'core/block-editor' ).getBlocks();
	}, [] );

	return useMemo( () => findModalContentBlocks( blocks ), [ blocks ] );
}
