/**
 * Reads modal template parts from the core entity store.
 *
 * Replaces the previous localized-array approach: entities update the moment
 * a part is created, and they carry content, which the preview needs. The
 * localized array survives only for hybrid themes, which have no
 * wp_template_part entities at all.
 */

import { useMemo } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { useEntityRecords, store as coreStore } from '@wordpress/core-data';
import {
	filterModalParts,
	buildPartOptions,
	getPartTitle,
	DEFAULT_MODAL_SLUG,
} from './modal-template-parts';

export default function useModalTemplateEntities( selectedSlug ) {
	const isBlockTheme = Boolean( window.pikariGutenbergModals?.isBlockTheme );

	const { records, isResolving, hasResolved } = useEntityRecords(
		'postType',
		'wp_template_part',
		{ per_page: -1 },
		{ enabled: isBlockTheme }
	);

	const currentTheme = useSelect(
		( select ) => select( coreStore ).getCurrentTheme()?.stylesheet,
		[]
	);

	const parts = useMemo( () => {
		if ( ! isBlockTheme ) {
			// Hybrid themes: shape the localized array like entity records so
			// the rest of the panel does not need a second code path.
			return ( window.pikariGutenbergModals?.modalTemplateParts || [] ).map(
				( part ) => ( {
					slug: part.slug,
					area: 'modal',
					title: { rendered: part.title },
				} )
			);
		}

		return filterModalParts( records );
	}, [ isBlockTheme, records ] );

	const options = useMemo(
		() =>
			buildPartOptions( {
				parts,
				selectedSlug,
				hasResolved: isBlockTheme ? hasResolved : true,
				isResolving: isBlockTheme ? isResolving : false,
			} ),
		[ parts, selectedSlug, hasResolved, isResolving, isBlockTheme ]
	);

	const selectedPart = useMemo(
		() =>
			parts.find(
				( part ) => part.slug === ( selectedSlug || DEFAULT_MODAL_SLUG )
			) || null,
		[ parts, selectedSlug ]
	);

	return {
		parts,
		options,
		selectedPart,
		currentTheme,
		isBlockTheme,
		isResolving: isBlockTheme ? isResolving : false,
		hasResolved: isBlockTheme ? hasResolved : true,
		getPartTitle,
	};
}
