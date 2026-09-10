/**
 * Creates a new modal template part seeded from a starter pattern.
 *
 * Mirrors core's use-create-overlay.js, with one substitution: core resolves
 * its single starter via unlock( blockEditorStore ).getPatternBySlug(), a
 * private API. We read all registered patterns from the core store and filter
 * on the modal block type instead, which is also what powers the picker.
 */

import { useCallback } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { parse, serialize, createBlock } from '@wordpress/blocks';
import {
	getUniqueTitle,
	getCleanSlug,
	MODAL_TEMPLATE_PART_AREA,
	DEFAULT_MODAL_SLUG,
} from './modal-template-parts';

export default function useCreateModalTemplate( parts ) {
	const { saveEntityRecord } = useDispatch( coreStore );

	return useCallback(
		async ( { title, patternContent } ) => {
			const uniqueTitle = getUniqueTitle( title, parts );

			// getCleanSlug() strips every non-ASCII character rather than
			// transliterating, so a title in a non-Latin script (Chinese,
			// Japanese, Arabic, Greek...) -- or one that is punctuation-only
			// once trimmed -- cleans to ''. An empty slug is also how
			// buildPartOptions() represents "no selection", so posting one
			// is worth avoiding on principle even if WordPress recovers.
			// Core hits the same wall in its own equivalent
			// (getCleanTemplatePartSlug() in
			// packages/block-library/src/navigation/edit/utils.js) and falls
			// back to a fixed slug, relying on WordPress's own
			// wp_unique_post_slug() to suffix it on collision. Mirror that
			// fallback here rather than inventing a new one.
			//
			// getUniqueTitle() only dedupes on exact title string, while
			// getCleanSlug() also lowercases and strips punctuation, so a
			// title like "MODAL" or "Modal!" cleans to the same slug as the
			// literal word "modal" without ever colliding on title. That
			// slug is exactly DEFAULT_MODAL_SLUG, which buildPartOptions()
			// collapses into the { value: '' } Default option -- so the new
			// part's slug would match no option the SelectControl renders.
			// Route it through the same fallback as the empty-slug case.
			const cleanSlug = getCleanSlug( uniqueTitle );
			const slug =
				! cleanSlug || cleanSlug === DEFAULT_MODAL_SLUG
					? 'wp-custom-part'
					: cleanSlug;

			const blocks = patternContent
				? parse( patternContent, { __unstableSkipMigrationLogs: true } )
				: [ createBlock( 'core/paragraph' ) ];
			const content = serialize( blocks );

			return await saveEntityRecord(
				'postType',
				'wp_template_part',
				{
					slug,
					title: uniqueTitle,
					content,
					area: MODAL_TEMPLATE_PART_AREA,
				},
				{ throwOnError: true }
			);
		},
		[ parts, saveEntityRecord ]
	);
}
