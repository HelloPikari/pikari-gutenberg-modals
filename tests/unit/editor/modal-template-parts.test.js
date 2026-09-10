/**
 * Tests for the pure modal template part helpers.
 *
 * @see src/editor/modal-template-parts.js
 */

import {
	filterModalParts,
	getPartTitle,
	buildPartOptions,
	getUniqueTitle,
	getCleanSlug,
	createTemplatePartId,
	selectModalPatterns,
	MODAL_PATTERN_BLOCK_TYPE,
} from '../../../src/editor/modal-template-parts';

const part = ( slug, title, area = 'modal' ) => ( {
	slug,
	area,
	title: { rendered: title },
} );

describe( 'filterModalParts', () => {
	it( 'returns an empty array for null', () => {
		expect( filterModalParts( null ) ).toEqual( [] );
	} );

	it( 'returns an empty array for a non-array', () => {
		expect( filterModalParts( undefined ) ).toEqual( [] );
	} );

	it( 'keeps only parts in the modal area', () => {
		const records = [
			part( 'modal', 'Modal' ),
			part( 'header', 'Header', 'header' ),
			part( 'sidebar', 'Sidebar' ),
		];
		expect( filterModalParts( records ).map( ( p ) => p.slug ) ).toEqual( [
			'modal',
			'sidebar',
		] );
	} );

	it( 'tolerates records without an area', () => {
		expect( filterModalParts( [ { slug: 'x' } ] ) ).toEqual( [] );
	} );
} );

describe( 'getPartTitle', () => {
	it( 'prefers the rendered title', () => {
		expect( getPartTitle( { slug: 's', title: { rendered: 'R' } } ) ).toBe( 'R' );
	} );

	it( 'falls back to the raw title', () => {
		expect( getPartTitle( { slug: 's', title: { raw: 'W' } } ) ).toBe( 'W' );
	} );

	it( 'falls back to the slug when there is no title', () => {
		expect( getPartTitle( { slug: 's' } ) ).toBe( 's' );
	} );
} );

describe( 'buildPartOptions', () => {
	const resolved = { hasResolved: true, isResolving: false };

	it( 'returns only the default option while resolving', () => {
		expect(
			buildPartOptions( {
				parts: [ part( 'sidebar', 'Sidebar' ) ],
				selectedSlug: '',
				hasResolved: false,
				isResolving: true,
			} )
		).toEqual( [ { label: 'Default', value: '' } ] );
	} );

	it( 'maps the modal slug to the default option rather than duplicating it', () => {
		const options = buildPartOptions( {
			parts: [ part( 'modal', 'Modal' ), part( 'sidebar', 'Sidebar' ) ],
			selectedSlug: '',
			...resolved,
		} );
		expect( options ).toEqual( [
			{ label: 'Default', value: '' },
			{ label: 'Sidebar', value: 'sidebar' },
		] );
	} );

	it( 'adds a default option when no modal-slug part exists', () => {
		const options = buildPartOptions( {
			parts: [ part( 'sidebar', 'Sidebar' ) ],
			selectedSlug: '',
			...resolved,
		} );
		expect( options[ 0 ] ).toEqual( { label: 'Default', value: '' } );
		expect( options ).toHaveLength( 2 );
	} );

	it( 'puts the default option first regardless of record order', () => {
		const options = buildPartOptions( {
			parts: [ part( 'sidebar', 'Sidebar' ), part( 'modal', 'Modal' ) ],
			selectedSlug: '',
			...resolved,
		} );
		expect( options[ 0 ].value ).toBe( '' );
	} );

	it( 'adds a missing entry for a selection that no longer resolves', () => {
		const options = buildPartOptions( {
			parts: [ part( 'modal', 'Modal' ) ],
			selectedSlug: 'deleted-part',
			...resolved,
		} );
		expect( options ).toEqual( [
			{ label: 'Default', value: '' },
			{ label: 'deleted-part (missing)', value: 'deleted-part' },
		] );
	} );

	it( 'does not add a missing entry for the default selection', () => {
		const options = buildPartOptions( {
			parts: [ part( 'modal', 'Modal' ) ],
			selectedSlug: '',
			...resolved,
		} );
		expect( options ).toEqual( [ { label: 'Default', value: '' } ] );
	} );

	it( 'does not add a missing entry when the selection resolves', () => {
		const options = buildPartOptions( {
			parts: [ part( 'modal', 'Modal' ), part( 'sidebar', 'Sidebar' ) ],
			selectedSlug: 'sidebar',
			...resolved,
		} );
		expect( options ).toHaveLength( 2 );
	} );

	it( 'handles a null parts list', () => {
		expect(
			buildPartOptions( { parts: null, selectedSlug: '', ...resolved } )
		).toEqual( [ { label: 'Default', value: '' } ] );
	} );
} );

describe( 'getUniqueTitle', () => {
	it( 'returns the base when nothing collides', () => {
		expect( getUniqueTitle( 'Modal', [] ) ).toBe( 'Modal' );
	} );

	it( 'appends 2 on the first collision', () => {
		expect( getUniqueTitle( 'Modal', [ part( 'modal', 'Modal' ) ] ) ).toBe( 'Modal 2' );
	} );

	it( 'skips over existing numbered titles', () => {
		const parts = [
			part( 'a', 'Modal' ),
			part( 'b', 'Modal 2' ),
			part( 'c', 'Modal 3' ),
		];
		expect( getUniqueTitle( 'Modal', parts ) ).toBe( 'Modal 4' );
	} );

	it( 'ignores parts without titles', () => {
		expect( getUniqueTitle( 'Modal', [ { slug: 'x' } ] ) ).toBe( 'Modal' );
	} );
} );

describe( 'getCleanSlug', () => {
	it( 'lowercases and hyphenates', () => {
		expect( getCleanSlug( 'Booking Sidebar' ) ).toBe( 'booking-sidebar' );
	} );

	it( 'strips punctuation', () => {
		expect( getCleanSlug( "Steve's Modal!" ) ).toBe( 'steve-s-modal' );
	} );

	it( 'trims leading and trailing separators', () => {
		expect( getCleanSlug( '  --Modal--  ' ) ).toBe( 'modal' );
	} );

	it( 'returns an empty string for empty input', () => {
		expect( getCleanSlug( '' ) ).toBe( '' );
	} );
} );

describe( 'createTemplatePartId', () => {
	it( 'joins theme and slug with a double slash', () => {
		expect( createTemplatePartId( 'twentytwentyfive', 'sidebar' ) ).toBe(
			'twentytwentyfive//sidebar'
		);
	} );

	it( 'returns null without a theme', () => {
		expect( createTemplatePartId( '', 'sidebar' ) ).toBeNull();
	} );

	it( 'returns null without a slug', () => {
		expect( createTemplatePartId( 'theme', '' ) ).toBeNull();
	} );
} );

describe( 'selectModalPatterns', () => {
	it( 'returns an empty array for null', () => {
		expect( selectModalPatterns( null ) ).toEqual( [] );
	} );

	it( 'keeps only patterns scoped to the modal template part area', () => {
		const patterns = [
			{ name: 'a', blockTypes: [ MODAL_PATTERN_BLOCK_TYPE ] },
			{ name: 'b', blockTypes: [ 'core/template-part/header' ] },
			{ name: 'c' },
		];
		expect( selectModalPatterns( patterns ).map( ( p ) => p.name ) ).toEqual( [ 'a' ] );
	} );
} );
