/**
 * Hook to provide available modal template parts for the editor.
 *
 * Reads from the localized pikariGutenbergModals.modalTemplateParts array
 * and returns formatted options for SelectControl, plus a flag indicating
 * whether the selector should be shown (2+ template parts).
 */

import { useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

export default function useModalTemplateParts() {
	return useMemo( () => {
		const parts = window.pikariGutenbergModals?.modalTemplateParts || [];

		const slugs = new Set( parts.map( ( part ) => part.slug ) );

		const options = parts.map( ( part ) => ( {
			label:
				part.slug === 'modal'
					? __( 'Default', 'pikari-gutenberg-modals' )
					: part.title || part.slug,
			value: part.slug === 'modal' ? '' : part.slug,
		} ) );

		// Ensure the default option is first
		options.sort( ( a, b ) => {
			if ( a.value === '' ) {
				return -1;
			}
			if ( b.value === '' ) {
				return 1;
			}
			return 0;
		} );

		return {
			options,
			hasMultiple: parts.length >= 2,
			/**
			 * Check if a template part selection is still valid.
			 * Empty string (default) is always valid.
			 *
			 * @param {string} value The selected template part slug.
			 * @return {boolean} Whether the selection exists.
			 */
			isValidSelection: ( value ) => ! value || slugs.has( value ),
		};
	}, [] );
}
