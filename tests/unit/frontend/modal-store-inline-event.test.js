/**
 * Inline content announces itself too.
 *
 * Inline content is cloned from a hidden element on the page with innerHTML,
 * so anything that binds on document ready — a WPForms form — never sees the
 * clone. The store fires the same bubbling `pikari-modal:content-loaded` event
 * the REST path fires, once the clone is in the dialog. Nothing is loaded:
 * there is no endpoint to hand over scripts, and the page already has them.
 */

import { store, getContext } from '@wordpress/interactivity';

import '../../../src/frontend/modal-store';

/**
 * Build a hidden inline source and a modal container on the page.
 *
 * @return {HTMLElement} The container element.
 */
function setUpContainer() {
	document.body.innerHTML = `
		<div data-modal-inline-content="book" data-modal-inline-title="Book a conversation" hidden>
			<form class="wpforms-form"></form>
		</div>
		<div id="pikari-modal" class="modal-overlay">
			<div class="modal-content">
				<div class="modal-body"></div>
			</div>
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

describe( 'modal store inline content event', () => {
	let actions;
	let listener;

	beforeAll( () => {
		( { actions } = store.getStore( 'pikari-modal' ) );
	} );

	beforeEach( () => {
		jest.clearAllMocks();
		global.fetch = jest.fn();
		listener = jest.fn();
		document.addEventListener( 'pikari-modal:content-loaded', listener );
	} );

	afterEach( () => {
		document.removeEventListener( 'pikari-modal:content-loaded', listener );
		jest.useFakeTimers();
		actions.closeModal();
		jest.advanceTimersByTime( 200 );
		jest.useRealTimers();
		delete global.fetch;
	} );

	it( 'announces the clone once it is in the dialog', () => {
		const container = setUpContainer();
		let bodyWhenAnnounced = null;

		listener.mockImplementation( () => {
			bodyWhenAnnounced = container.querySelector( '.modal-body' ).innerHTML;
		} );
		getContext.mockReturnValue( {
			contentSource: 'inline',
			inlineAnchor: 'book',
			modalId: 'inline-book',
		} );

		runOpen( actions );

		expect( listener ).toHaveBeenCalledTimes( 1 );

		const event = listener.mock.calls[ 0 ][ 0 ];
		expect( event.target ).toBe( container );
		expect( event.detail ).toEqual( {
			slug: 'modal',
			postId: null,
			loaded: [],
		} );
		expect( bodyWhenAnnounced ).toContain( 'wpforms-form' );
	} );

	it( 'stays silent when the inline source is not on the page', () => {
		setUpContainer();
		getContext.mockReturnValue( {
			contentSource: 'inline',
			inlineAnchor: 'missing',
			modalId: 'inline-missing',
		} );

		runOpen( actions );

		expect( listener ).not.toHaveBeenCalled();
	} );

	it( 'never reaches the REST endpoint', () => {
		setUpContainer();
		getContext.mockReturnValue( {
			contentSource: 'inline',
			inlineAnchor: 'book',
			modalId: 'inline-book',
		} );

		runOpen( actions );

		expect( global.fetch ).not.toHaveBeenCalled();
	} );
} );
