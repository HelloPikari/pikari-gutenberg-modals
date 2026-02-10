/**
 * Hook to detect if the current editing context is the modal template part.
 *
 * Returns true when editing the modal template part directly in the Site Editor,
 * or when a block is nested inside a core/template-part with slug "modal".
 */

import { useSelect } from '@wordpress/data';

export default function useIsModalTemplatePart( clientId ) {
	return useSelect(
		( select ) => {
			// Check 1: Directly editing the modal template part in the Site Editor.
			// The core/edit-site store only exists in the Site Editor, not the post editor.
			const editSite = select( 'core/edit-site' );
			if (
				editSite?.getEditedPostType?.() === 'wp_template_part' &&
				editSite?.getEditedPostId?.()?.endsWith( '//modal' )
			) {
				return true;
			}

			// Check 2: Block is nested inside a core/template-part with slug "modal"
			// (e.g., editing a template that contains the modal template part).
			if ( clientId ) {
				const { getBlockParents, getBlock } =
					select( 'core/block-editor' );
				const parentIds = getBlockParents( clientId );

				for ( const parentId of parentIds ) {
					const parentBlock = getBlock( parentId );
					if (
						parentBlock?.name === 'core/template-part' &&
						parentBlock?.attributes?.slug === 'modal'
					) {
						return true;
					}
				}
			}

			return false;
		},
		[ clientId ]
	);
}
