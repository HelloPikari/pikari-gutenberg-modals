/**
 * Tests for trigger block identification and attribute helpers.
 *
 * @see src/editor/trigger-blocks.js
 */

import {
	TRIGGER_BLOCKS,
	isTriggerBlock,
	MODAL_ATTRIBUTES,
	hasModalAction,
} from '../../../src/editor/trigger-blocks';

describe( 'TRIGGER_BLOCKS', () => {
	it( 'covers group and button only', () => {
		expect( TRIGGER_BLOCKS ).toEqual( [ 'core/group', 'core/button' ] );
	} );

	it( 'does not include core/image', () => {
		// core attaches its own lightbox click handler to core/image;
		// two handlers on one click is the conflict this avoids.
		expect( TRIGGER_BLOCKS ).not.toContain( 'core/image' );
	} );
} );

describe( 'isTriggerBlock', () => {
	it( 'accepts a supported block', () => {
		expect( isTriggerBlock( 'core/group' ) ).toBe( true );
		expect( isTriggerBlock( 'core/button' ) ).toBe( true );
	} );

	it( 'rejects an unsupported block', () => {
		expect( isTriggerBlock( 'core/paragraph' ) ).toBe( false );
		expect( isTriggerBlock( 'core/image' ) ).toBe( false );
	} );

	it( 'rejects empty input', () => {
		expect( isTriggerBlock( '' ) ).toBe( false );
		expect( isTriggerBlock( undefined ) ).toBe( false );
	} );
} );

describe( 'MODAL_ATTRIBUTES', () => {
	it( 'declares every attribute the design names', () => {
		expect( Object.keys( MODAL_ATTRIBUTES ).sort() ).toEqual(
			[
				'pikariModalAccessibleLabel',
				'pikariModalAction',
				'pikariModalContentSource',
				'pikariModalDirectUrl',
				'pikariModalInlineAnchor',
				'pikariModalPlacement',
				'pikariModalPrimaryLinkId',
				'pikariModalSize',
				'pikariModalTemplatePart',
			].sort()
		);
	} );

	it( 'defaults every attribute to an empty string', () => {
		Object.values( MODAL_ATTRIBUTES ).forEach( ( def ) => {
			expect( def.type ).toBe( 'string' );
			expect( def.default ).toBe( '' );
		} );
	} );
} );

describe( 'hasModalAction', () => {
	it( 'is false when the action is unset', () => {
		expect( hasModalAction( {} ) ).toBe( false );
		expect( hasModalAction( { pikariModalAction: '' } ) ).toBe( false );
	} );

	it( 'is true for open and close', () => {
		expect( hasModalAction( { pikariModalAction: 'open' } ) ).toBe( true );
		expect( hasModalAction( { pikariModalAction: 'close' } ) ).toBe( true );
	} );

	it( 'is false for an unrecognised action', () => {
		expect( hasModalAction( { pikariModalAction: 'wobble' } ) ).toBe( false );
	} );

	it( 'tolerates null attributes', () => {
		expect( hasModalAction( null ) ).toBe( false );
	} );
} );
