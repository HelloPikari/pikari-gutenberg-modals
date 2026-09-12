/**
 * "Template only" triggers: the modal template part is the content.
 *
 * There is nothing to fetch and nothing on the page to clone, so the store
 * must open the container without a postId and without entering the loading
 * state — the two things every other open mode depends on.
 */

import { store, getContext } from '@wordpress/interactivity';

import '../../../src/frontend/modal-store';

/**
 * Build a modal container on the page.
 *
 * @param {string} slug Template part slug.
 * @return {HTMLElement} The container element.
 */
function setUpContainer( slug = 'modal' ) {
	const id = slug === 'modal' ? 'pikari-modal' : `pikari-modal--${ slug }`;

	document.body.innerHTML = `
		<div id="${ id }" class="modal-overlay" aria-label="Modal dialog">
			<div class="modal-content">
				<div class="modal-body"></div>
			</div>
		</div>
	`;
	return document.getElementById( id );
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

describe( 'modal store template-only content', () => {
	let actions;
	let state;

	beforeAll( () => {
		( { actions, state } = store.getStore( 'pikari-modal' ) );
	} );

	beforeEach( () => {
		jest.clearAllMocks();
		global.fetch = jest.fn();
	} );

	afterEach( () => {
		jest.useFakeTimers();
		actions.closeModal();
		jest.advanceTimersByTime( 200 );
		jest.useRealTimers();
		delete global.fetch;
	} );

	it( 'opens the container with no postId', () => {
		const container = setUpContainer();
		getContext.mockReturnValue( {
			contentSource: 'none',
			modalId: 'template-modal',
		} );

		runOpen( actions );

		expect( state.isOpen ).toBe( true );
		expect( container.classList.contains( 'is-open' ) ).toBe( true );
	} );

	it( 'never reaches the REST endpoint', () => {
		setUpContainer();
		getContext.mockReturnValue( {
			contentSource: 'none',
			modalId: 'template-modal',
		} );

		runOpen( actions );

		expect( global.fetch ).not.toHaveBeenCalled();
	} );

	it( 'does not enter the loading state', () => {
		setUpContainer();
		getContext.mockReturnValue( {
			contentSource: 'none',
			modalId: 'template-modal',
		} );

		runOpen( actions );

		expect( state.loading ).toBe( false );
		expect( state.hasError ).toBe( false );
	} );

	it( 'leaves the body empty for the template to fill', () => {
		const container = setUpContainer();
		getContext.mockReturnValue( {
			contentSource: 'none',
			modalId: 'template-modal',
		} );

		runOpen( actions );

		expect( container.querySelector( '.modal-body' ).innerHTML ).toBe( '' );
	} );

	it( 'marks the trigger expanded so aria-expanded binds', () => {
		setUpContainer();
		getContext.mockReturnValue( {
			contentSource: 'none',
			modalId: 'template-booking',
		} );

		runOpen( actions );

		expect( state.activeModalId ).toBe( 'template-booking' );
		expect( state.isExpanded ).toBe( true );
	} );

	it( 'opens the container belonging to its own template part', () => {
		const container = setUpContainer( 'booking' );
		getContext.mockReturnValue( {
			contentSource: 'none',
			modalId: 'template-booking',
			templatePart: 'booking',
		} );

		runOpen( actions );

		expect( container.classList.contains( 'is-open' ) ).toBe( true );
	} );

	it( "names the dialog after the trigger's own label", () => {
		const container = setUpContainer();
		getContext.mockReturnValue( {
			contentSource: 'none',
			modalId: 'template-modal',
			label: 'Book a conversation',
		} );

		runOpen( actions );

		expect( container.getAttribute( 'aria-label' ) ).toBe(
			'Book a conversation'
		);
	} );
} );
