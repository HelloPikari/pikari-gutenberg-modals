/**
 * Placement precedence and contextual sizing.
 *
 * @see src/frontend/modal-geometry.js
 */

import { resolveGeometry } from '../../../src/frontend/modal-geometry';

describe( 'resolveGeometry', () => {
	it( 'defaults to centered with no size', () => {
		expect( resolveGeometry() ).toEqual( { placement: '', size: '' } );
	} );

	it( 'uses the dialog placement when the trigger sets none', () => {
		expect(
			resolveGeometry( { dialogPlacement: 'right' } )
		).toEqual( { placement: 'right', size: '' } );
	} );

	it( 'lets the trigger override the dialog placement', () => {
		expect(
			resolveGeometry( {
				triggerPlacement: 'left',
				dialogPlacement: 'right',
			} )
		).toEqual( { placement: 'left', size: '' } );
	} );

	it( 'ignores an unknown trigger placement and falls back to the dialog', () => {
		expect(
			resolveGeometry( {
				triggerPlacement: 'top',
				dialogPlacement: 'right',
			} )
		).toEqual( { placement: 'right', size: '' } );
	} );

	it( 'ignores an unknown dialog placement and centers', () => {
		expect(
			resolveGeometry( { dialogPlacement: 'bottom' } )
		).toEqual( { placement: '', size: '' } );
	} );

	it( 'keeps a centered size slug when centered', () => {
		expect( resolveGeometry( { size: 'fullscreen' } ) ).toEqual( {
			placement: '',
			size: 'fullscreen',
		} );
	} );

	it( 'drops a centered size slug on a panel', () => {
		expect(
			resolveGeometry( { dialogPlacement: 'right', size: 'fullscreen' } )
		).toEqual( { placement: 'right', size: '' } );
	} );

	it( 'keeps a panel size slug on a panel', () => {
		expect(
			resolveGeometry( { dialogPlacement: 'right', size: 'wide' } )
		).toEqual( { placement: 'right', size: 'wide' } );
	} );

	it( 'drops a panel size slug when centered', () => {
		expect( resolveGeometry( { size: 'narrow' } ) ).toEqual( {
			placement: '',
			size: '',
		} );
	} );
} );
