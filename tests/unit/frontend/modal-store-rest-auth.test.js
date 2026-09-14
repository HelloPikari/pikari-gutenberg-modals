/**
 * Logged-in viewers fetch modal content as themselves.
 *
 * Without WordPress's REST nonce the endpoint renders content as a logged-out
 * request, and a form that expects a logged-in user's nonce (WPForms) refuses
 * the submit. The config carries a nonce only for a logged-in viewer; the
 * store sends it, bypasses the browser's copy of an anonymous response, falls
 * back to the anonymous render when the nonce has gone stale, and does not
 * prefetch a response the server will not let the browser keep.
 */

import { store, getConfig, getContext } from '@wordpress/interactivity';

import '../../../src/frontend/modal-store';

jest.mock( '../../../src/frontend/block-script-loader', () => ( {
	loadBlockScripts: jest.fn( () => Promise.resolve( [] ) ),
} ) );

const REST_URL = 'https://example.com/wp-json/pikari-gutenberg-modals/v1/';
const FETCH_URL = `${ REST_URL }modal-content/365?modal_id=page-365`;

/**
 * Build a modal container on the page.
 */
function setUpContainer() {
	document.body.innerHTML = `
		<div id="pikari-modal" class="modal-overlay">
			<div class="modal-content">
				<div class="modal-body"></div>
			</div>
		</div>
	`;
}

describe( 'modal store REST authentication', () => {
	let actions;

	beforeAll( () => {
		( { actions } = store.getStore( 'pikari-modal' ) );
	} );

	beforeEach( () => {
		jest.clearAllMocks();
		setUpContainer();
		global.fetch = jest.fn( () => 'pending fetch' );
		getContext.mockReturnValue( { postId: 365, modalId: 'page-365' } );
	} );

	afterEach( () => {
		jest.useFakeTimers();
		actions.closeModal();
		jest.advanceTimersByTime( 200 );
		jest.useRealTimers();
		delete global.fetch;
		getConfig.mockReturnValue( { restUrl: REST_URL } );
	} );

	it( 'sends the nonce and skips the cached anonymous body for a logged-in viewer', () => {
		getConfig.mockReturnValue( { restUrl: REST_URL, nonce: 'abc123' } );

		actions.openModal().next();

		expect( global.fetch ).toHaveBeenCalledWith(
			FETCH_URL,
			expect.objectContaining( {
				headers: { 'X-WP-Nonce': 'abc123' },
				cache: 'no-cache',
			} )
		);
	} );

	it( 'fetches exactly as before for a logged-out visitor', () => {
		getConfig.mockReturnValue( { restUrl: REST_URL } );

		actions.openModal().next();

		expect( global.fetch ).toHaveBeenCalledWith( FETCH_URL );
	} );

	it( 'retries without the nonce when the server refuses it', () => {
		getConfig.mockReturnValue( { restUrl: REST_URL, nonce: 'stale' } );

		const generator = actions.openModal();
		generator.next(); // fetch() with the nonce
		generator.next( { ok: false, status: 403 } );

		expect( global.fetch ).toHaveBeenCalledTimes( 2 );
		expect( global.fetch ).toHaveBeenLastCalledWith( FETCH_URL );
	} );

	it( 'does not prefetch for a logged-in viewer', () => {
		getConfig.mockReturnValue( { restUrl: REST_URL, nonce: 'abc123' } );

		const generator = actions.prefetchModal();
		generator.next();

		expect( global.fetch ).not.toHaveBeenCalled();
	} );
} );
