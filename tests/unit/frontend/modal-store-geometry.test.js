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
 * @param {string} inlineTitle     Value for data-modal-inline-title.
 * @return {HTMLElement} The container element.
 */
function setUpContainer( dialogPlacement = '', inlineTitle = 'Promo' ) {
	const placementAttr = dialogPlacement
		? ` data-default-placement="${ dialogPlacement }"`
		: '';

	document.body.innerHTML = `
		<div id="pikari-modal" class="modal-overlay">
			<div class="modal-content"${ placementAttr }>
				<div class="modal-body"></div>
			</div>
		</div>
		<div data-modal-inline-content="promo" data-modal-inline-title="${ inlineTitle }">
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

	// `previousAriaLabel` lives at module scope in modal-store.js and the
	// module is imported once for the whole file. Closing the modal — and
	// letting the exit-animation timeout run — is what returns it to null, so
	// every test starts from the same state rather than inheriting the last
	// test's captured label.
	afterEach( () => {
		jest.useFakeTimers();
		actions.closeModal();
		jest.advanceTimersByTime( 200 );
		jest.useRealTimers();
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

	it( "labels the dialog with the trigger's accessible name", () => {
		const container = setUpContainer();
		container.setAttribute( 'aria-label', 'Modal dialog' );
		getContext.mockReturnValue( {
			contentSource: 'inline',
			inlineAnchor: 'promo',
			label: 'Watch the Talks',
		} );

		runOpen( actions );

		expect( container.getAttribute( 'aria-label' ) ).toBe(
			'Watch the Talks'
		);
	} );

	it( "falls back to the inline content's own title when the trigger supplies no label", () => {
		const container = setUpContainer();
		container.setAttribute( 'aria-label', 'Modal dialog' );
		getContext.mockReturnValue( {
			contentSource: 'inline',
			inlineAnchor: 'promo',
		} );

		runOpen( actions );

		expect( container.getAttribute( 'aria-label' ) ).toBe( 'Promo' );
	} );

	it( 'leaves the generic label alone when there is no name to be had', () => {
		const container = setUpContainer( '', '' );
		container.setAttribute( 'aria-label', 'Modal dialog' );
		getContext.mockReturnValue( {
			contentSource: 'inline',
			inlineAnchor: 'promo',
		} );

		runOpen( actions );

		expect( container.getAttribute( 'aria-label' ) ).toBe( 'Modal dialog' );
	} );

	it( 'restores the true original label after a same-container reopen with no intervening close', () => {
		const container = setUpContainer();
		container.setAttribute( 'aria-label', 'Modal dialog' );

		getContext.mockReturnValue( {
			contentSource: 'inline',
			inlineAnchor: 'promo',
			label: 'Watch the Talks',
		} );
		runOpen( actions );

		// Reopen the same container with a different label before it ever
		// closes — closeTimeoutId is still null, so the cancel-pending-close
		// restore does not run here.
		getContext.mockReturnValue( {
			contentSource: 'inline',
			inlineAnchor: 'promo',
			label: 'Meet the Team',
		} );
		runOpen( actions );

		jest.useFakeTimers();
		actions.closeModal();
		jest.advanceTimersByTime( 200 );

		expect( container.getAttribute( 'aria-label' ) ).toBe( 'Modal dialog' );
	} );
} );
