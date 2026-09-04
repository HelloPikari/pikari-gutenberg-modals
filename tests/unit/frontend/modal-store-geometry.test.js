/**
 * The store writes effective geometry to the container.
 *
 * @see src/frontend/modal-geometry.js for the resolution rules themselves.
 */

import { store, getContext } from '@wordpress/interactivity';

import '../../../src/frontend/modal-store';

/**
 * Build a modal container with an inline content source on the page.
 *
 * @param {string} dialogPlacement Value for data-default-placement, or ''.
 * @return {HTMLElement} The container element.
 */
function setUpContainer( dialogPlacement = '' ) {
	document.body.innerHTML = `
		<div id="pikari-modal" class="modal-overlay">
			<div class="modal-content"${
	dialogPlacement
		? ` data-default-placement="${ dialogPlacement }"`
		: ''
}>
				<div class="modal-body"></div>
			</div>
		</div>
		<div data-modal-inline-content="promo" data-modal-inline-title="Promo">
			<p>Inline content</p>
		</div>
	`;
	return document.getElementById( 'pikari-modal' );
}

/**
 * Run the openModal generator to completion.
 *
 * @param {Object} actions Store actions.
 */
function runOpen( actions ) {
	const generator = actions.openModal();
	let step = generator.next();
	while ( ! step.done ) {
		step = generator.next();
	}
}

describe( 'modal store geometry', () => {
	let actions;

	beforeAll( () => {
		( { actions } = store.getStore( 'pikari-modal' ) );
	} );

	beforeEach( () => {
		jest.clearAllMocks();
	} );

	it( 'leaves a centered dialog with no placement attribute', () => {
		const container = setUpContainer();
		getContext.mockReturnValue( {
			contentSource: 'inline',
			inlineAnchor: 'promo',
		} );

		runOpen( actions );

		expect( container.hasAttribute( 'data-placement' ) ).toBe( false );
	} );

	it( "applies the dialog's own placement", () => {
		const container = setUpContainer( 'right' );
		getContext.mockReturnValue( {
			contentSource: 'inline',
			inlineAnchor: 'promo',
		} );

		runOpen( actions );

		expect( container.getAttribute( 'data-placement' ) ).toBe( 'right' );
	} );

	it( 'lets the trigger override the dialog placement', () => {
		const container = setUpContainer( 'right' );
		getContext.mockReturnValue( {
			contentSource: 'inline',
			inlineAnchor: 'promo',
			placement: 'left',
		} );

		runOpen( actions );

		expect( container.getAttribute( 'data-placement' ) ).toBe( 'left' );
	} );

	it( 'drops a centered size slug on a panel', () => {
		const container = setUpContainer( 'right' );
		getContext.mockReturnValue( {
			contentSource: 'inline',
			inlineAnchor: 'promo',
			size: 'fullscreen',
		} );

		runOpen( actions );

		expect( container.hasAttribute( 'data-size' ) ).toBe( false );
	} );

	it( 'keeps a panel size slug on a panel', () => {
		const container = setUpContainer( 'right' );
		getContext.mockReturnValue( {
			contentSource: 'inline',
			inlineAnchor: 'promo',
			size: 'wide',
		} );

		runOpen( actions );

		expect( container.getAttribute( 'data-size' ) ).toBe( 'wide' );
	} );
} );
