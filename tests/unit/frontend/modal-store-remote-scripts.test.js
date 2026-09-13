/**
 * Remote content brings the scripts it needs.
 *
 * Content set with innerHTML never runs its scripts. The store hands the
 * endpoint's `scripts` to the loader once the content is in the dialog — not
 * before, or a script looking for its form (WPForms) finds nothing — and then
 * announces the content with a bubbling `pikari-modal:content-loaded` event,
 * saying which handles were loaded fresh so an integration can tell a script
 * that initialised itself from one that was already on the page.
 */

import { store, getContext } from '@wordpress/interactivity';
import { loadBlockScripts } from '../../../src/frontend/block-script-loader';

import '../../../src/frontend/modal-store';

jest.mock( '../../../src/frontend/block-script-loader', () => ( {
	loadBlockScripts: jest.fn(),
} ) );

const SCRIPTS = [
	{
		handle: 'wpforms',
		src: 'https://example.com/wpforms.min.js',
		data: '',
		before: '',
		after: '',
	},
];

const RESPONSE = {
	id: 365,
	title: 'Book a conversation',
	content: '<form class="wpforms-form"></form>',
	styles: '',
	blockStyles: { urls: [] },
	scripts: SCRIPTS,
	type: 'page',
};

/**
 * Build a modal container on the page.
 *
 * @return {HTMLElement} The container element.
 */
function setUpContainer() {
	document.body.innerHTML = `
		<div id="pikari-modal" class="modal-overlay">
			<div class="modal-content">
				<div class="modal-body"></div>
			</div>
		</div>
	`;
	return document.getElementById( 'pikari-modal' );
}

/**
 * Run openModal to completion against a fake REST response.
 *
 * @param {Object}   actions Store actions.
 * @param {Object}   data    Parsed response body.
 * @param {string[]} loaded  What the script loader resolves with.
 */
function runOpen( actions, data, loaded = [] ) {
	const generator = actions.openModal();

	generator.next(); // fetch()
	generator.next( { ok: true, json: () => data } ); // response.json()
	let step = generator.next( data );

	while ( ! step.done ) {
		step = generator.next( loaded );
	}
}

describe( 'modal store remote content scripts', () => {
	let actions;

	beforeAll( () => {
		( { actions } = store.getStore( 'pikari-modal' ) );
	} );

	beforeEach( () => {
		jest.clearAllMocks();
		global.fetch = jest.fn( () => 'pending fetch' );
		getContext.mockReturnValue( { postId: 365, modalId: 'page-365' } );
	} );

	afterEach( () => {
		jest.useFakeTimers();
		actions.closeModal();
		jest.advanceTimersByTime( 200 );
		jest.useRealTimers();
		delete global.fetch;
	} );

	it( 'runs the scripts once the content is in the dialog', () => {
		const container = setUpContainer();
		let bodyWhenScriptsRan = null;

		loadBlockScripts.mockImplementation( () => {
			bodyWhenScriptsRan = container.querySelector( '.modal-body' ).innerHTML;
			return Promise.resolve( [] );
		} );

		runOpen( actions, RESPONSE );

		expect( loadBlockScripts ).toHaveBeenCalledWith( SCRIPTS );
		expect( bodyWhenScriptsRan ).toContain( 'wpforms-form' );
	} );

	it( 'announces the content after its scripts have run', () => {
		const container = setUpContainer();
		const order = [];

		loadBlockScripts.mockImplementation( () => {
			order.push( 'scripts' );
			return Promise.resolve( [ 'wpforms' ] );
		} );

		const listener = jest.fn( () => order.push( 'event' ) );
		document.addEventListener( 'pikari-modal:content-loaded', listener );

		runOpen( actions, RESPONSE, [ 'wpforms' ] );

		document.removeEventListener( 'pikari-modal:content-loaded', listener );

		expect( order ).toEqual( [ 'scripts', 'event' ] );

		const event = listener.mock.calls[ 0 ][ 0 ];
		expect( event.target ).toBe( container );
		expect( event.detail ).toEqual( {
			slug: 'modal',
			postId: 365,
			loaded: [ 'wpforms' ],
		} );
	} );
} );
