/**
 * Hook to detect if the current editing context is a modal template part.
 *
 * Returns true when editing any modal-area template part in the Site Editor,
 * or when a block is nested inside a core/template-part with area "modal".
 */

import { useSelect } from '@wordpress/data';

export default function useIsModalTemplatePart( clientId ) {
	return useSelect(
		( select ) => {
			// Check 1: Directly editing a modal template part in the Site Editor.
			// The core/edit-site store only exists in the Site Editor, not the post editor.
			const editSite = select( 'core/edit-site' );
			if ( editSite?.getEditedPostType?.() === 'wp_template_part' ) {
				const editedId = editSite?.getEditedPostId?.() || '';

				// Look up the template part entity to check its area
				const coreStore = select( 'core' );
				const templatePart = coreStore?.getEditedEntityRecord?.(
					'postType',
					'wp_template_part',
					editedId
				);

				if ( templatePart?.area === 'modal' ) {
					return true;
				}
			}

			// Check 2: Block is nested inside a core/template-part with area "modal"
			// (e.g., editing a template that contains a modal template part).
			if ( clientId ) {
				const { getBlockParents, getBlock } =
					select( 'core/block-editor' );
				const parentIds = getBlockParents( clientId );

				for ( const parentId of parentIds ) {
					const parentBlock = getBlock( parentId );
					if (
						parentBlock?.name === 'core/template-part' &&
						parentBlock?.attributes?.area === 'modal'
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
