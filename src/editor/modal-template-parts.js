/**
 * Pure helpers for modal template parts.
 *
 * Everything in this module is deliberately free of `@wordpress/*` imports
 * except `@wordpress/i18n`, which is mapped to a mock in jest.config.js.
 * The editor packages this feature uses (core-data, components, block-editor)
 * are webpack externals and are NOT installed, so they cannot be resolved by
 * Jest. Keeping the decisions here means they stay testable.
 */

import { __, sprintf } from '@wordpress/i18n';

/**
 * Template part area this plugin registers.
 */
export const MODAL_TEMPLATE_PART_AREA = 'modal';

/**
 * Block type used to scope starter patterns to the modal area.
 */
export const MODAL_PATTERN_BLOCK_TYPE = 'core/template-part/modal';

/**
 * Slug of the default modal template part.
 *
 * Triggers store an empty string to mean "the default", so this slug is
 * mapped to '' in the options list rather than offered twice.
 */
export const DEFAULT_MODAL_SLUG = 'modal';

/**
 * Keep only template part records belonging to the modal area.
 *
 * @param {Array|null} records Entity records, or null while unresolved.
 * @return {Array} Modal template parts.
 */
export function filterModalParts( records ) {
	if ( ! Array.isArray( records ) ) {
		return [];
	}

	return records.filter( ( record ) => record?.area === MODAL_TEMPLATE_PART_AREA );
}

/**
 * Human label for a template part.
 *
 * @param {Object} part Template part record.
 * @return {string} Title, falling back to the slug.
 */
export function getPartTitle( part ) {
	return part?.title?.rendered || part?.title?.raw || part?.slug || '';
}

/**
 * Build SelectControl options for the modal template panel.
 *
 * @param {Object}     options              Options.
 * @param {Array|null} options.parts        Modal template parts.
 * @param {string}     options.selectedSlug Currently stored slug ('' = default).
 * @param {boolean}    options.hasResolved  Whether the entity query resolved.
 * @param {boolean}    options.isResolving  Whether the entity query is in flight.
 * @return {Array<{label: string, value: string}>} Options.
 */
export function buildPartOptions( {
	parts,
	selectedSlug,
	hasResolved,
	isResolving,
} ) {
	const defaultOption = {
		label: __( 'Default', 'pikari-gutenberg-modals' ),
		value: '',
	};

	if ( ! hasResolved || isResolving ) {
		return [ defaultOption ];
	}

	const list = Array.isArray( parts ) ? parts : [];

	const options = list.map( ( part ) =>
		part.slug === DEFAULT_MODAL_SLUG
			? defaultOption
			: { label: getPartTitle( part ), value: part.slug }
	);

	if ( ! options.some( ( option ) => option.value === '' ) ) {
		options.unshift( defaultOption );
	}

	options.sort( ( a, b ) => {
		if ( a.value === '' ) {
			return -1;
		}
		if ( b.value === '' ) {
			return 1;
		}
		return 0;
	} );

	// A slug that no longer resolves is surfaced rather than silently
	// discarded, so the user can see what was lost and choose a replacement.
	const isMissing =
		selectedSlug && ! list.some( ( part ) => part.slug === selectedSlug );

	if ( isMissing ) {
		options.splice( 1, 0, {
			label: sprintf(
				/* translators: %s: template part slug. */
				__( '%s (missing)', 'pikari-gutenberg-modals' ),
				selectedSlug
			),
			value: selectedSlug,
		} );
	}

	return options;
}

/**
 * Produce a title that does not collide with existing parts.
 *
 * @param {string} base  Desired title.
 * @param {Array}  parts Existing modal template parts.
 * @return {string} Unique title.
 */
export function getUniqueTitle( base, parts ) {
	const existing = new Set(
		( Array.isArray( parts ) ? parts : [] )
			.map( ( part ) => part?.title?.rendered || part?.title?.raw || '' )
			.filter( Boolean )
	);

	if ( ! existing.has( base ) ) {
		return base;
	}

	let suffix = 2;
	while ( existing.has( `${ base } ${ suffix }` ) ) {
		suffix++;
	}

	return `${ base } ${ suffix }`;
}

/**
 * Convert a title into a URL-safe template part slug.
 *
 * @param {string} title Title.
 * @return {string} Slug.
 */
export function getCleanSlug( title ) {
	return ( title || '' )
		.toLowerCase()
		.replace( /[^a-z0-9]+/g, '-' )
		.replace( /^-+|-+$/g, '' );
}

/**
 * Build the entity id WordPress uses for template parts.
 *
 * @param {string} theme Theme stylesheet.
 * @param {string} slug  Template part slug.
 * @return {string|null} Id in `theme//slug` form, or null if either is missing.
 */
export function createTemplatePartId( theme, slug ) {
	if ( ! theme || ! slug ) {
		return null;
	}

	return `${ theme }//${ slug }`;
}

/**
 * Keep only patterns registered as modal template part starters.
 *
 * @param {Array|null} patterns All registered block patterns.
 * @return {Array} Modal starter patterns.
 */
export function selectModalPatterns( patterns ) {
	if ( ! Array.isArray( patterns ) ) {
		return [];
	}

	return patterns.filter( ( pattern ) =>
		pattern?.blockTypes?.includes( MODAL_PATTERN_BLOCK_TYPE )
	);
}
