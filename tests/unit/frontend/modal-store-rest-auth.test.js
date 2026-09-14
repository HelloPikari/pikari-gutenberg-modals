/**
 * Logged-in viewers fetch modal content as themselves.
 *
 * Without WordPress's REST nonce the endpoint renders content as a logged-out
 * request, and a form that expects a logged-in user's nonce (WPForms) refuses
 * the submit. The config carries a nonce only for a logged-in viewer. The
 * store sends it and bypasses the browser's copy of an anonymous response.
 * When the nonce is refused it asks core for a fresh one once, as api-fetch
 * does, before falling back to the anonymous render. It does not prefetch a
 * response the server will not let the browser keep.
 */

import { store, getConfig, getContext } from '@wordpress/interactivity';

import '../../../src/frontend/modal-store';

jest.mock( '../../../src/frontend/block-script-loader', () => ( {
	loadBlockScripts: jest.fn( () => Promise.resolve( [] ) ),
} ) );

const REST_URL = 'https://example.com/wp-json/pikari-gutenberg-modals/v1/';
const FETCH_URL = `${ REST_URL }modal-content/365?modal_id=page-365`;
const AJAX_URL = 'https://example.com/wp-admin/admin-ajax.php';
const NONCE_URL = `${ AJAX_URL }?action=rest-nonce`;

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
		getConfig.mockReturnValue( { restUrl: REST_URL, nonce: 'abc123', ajaxUrl: AJAX_URL } );

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

	it( 'refreshes a refused nonce and retries with the new one', () => {
		const config = { restUrl: REST_URL, nonce: 'stale', ajaxUrl: AJAX_URL };
		getConfig.mockReturnValue( config );

		const generator = actions.openModal();
		generator.next(); // fetch() with the stale nonce
		generator.next( { ok: false, status: 403 } ); // fetch() for a fresh nonce

		expect( global.fetch ).toHaveBeenLastCalledWith(
			NONCE_URL,
			expect.objectContaining( { cache: 'no-store' } )
		);

		generator.next( { ok: true, text: () => 'fresh' } ); // response.text()
		generator.next( 'fresh' ); // fetch() with the fresh nonce

		expect( global.fetch ).toHaveBeenLastCalledWith(
			FETCH_URL,
			expect.objectContaining( {
				headers: { 'X-WP-Nonce': 'fresh' },
				cache: 'no-cache',
			} )
		);
		// Later opens start from the fresh nonce, not the refused one.
		expect( config.nonce ).toBe( 'fresh' );
	} );

	it( 'falls back to the logged-out render when no fresh nonce can be had', () => {
		getConfig.mockReturnValue( { restUrl: REST_URL, nonce: 'stale', ajaxUrl: AJAX_URL } );

		const generator = actions.openModal();
		generator.next(); // fetch() with the stale nonce
		generator.next( { ok: false, status: 403 } ); // fetch() for a fresh nonce
		generator.next( { ok: false, status: 400 } ); // refused: logged out elsewhere

		expect( global.fetch ).toHaveBeenLastCalledWith( FETCH_URL );
	} );

	it( 'falls back to the logged-out render when the fresh nonce is refused too', () => {
		getConfig.mockReturnValue( { restUrl: REST_URL, nonce: 'stale', ajaxUrl: AJAX_URL } );

		const generator = actions.openModal();
		generator.next(); // fetch() with the stale nonce
		generator.next( { ok: false, status: 403 } ); // fetch() for a fresh nonce
		generator.next( { ok: true, text: () => 'fresh' } ); // response.text()
		generator.next( 'fresh' ); // fetch() with the fresh nonce
		generator.next( { ok: false, status: 403 } ); // refused again

		expect( global.fetch ).toHaveBeenCalledTimes( 4 );
		expect( global.fetch ).toHaveBeenLastCalledWith( FETCH_URL );
	} );

	it( 'retries only when the nonce is refused', () => {
		getConfig.mockReturnValue( { restUrl: REST_URL, nonce: 'abc123', ajaxUrl: AJAX_URL } );

		const generator = actions.openModal();
		generator.next(); // fetch() with the nonce
		generator.next( { ok: false, status: 404 } );

		expect( global.fetch ).toHaveBeenCalledTimes( 1 );
		expect( console ).toHaveErrored();
	} );

	it( 'does not prefetch for a logged-in viewer', () => {
		getConfig.mockReturnValue( { restUrl: REST_URL, nonce: 'abc123', ajaxUrl: AJAX_URL } );

		const generator = actions.prefetchModal();
		generator.next();

		expect( global.fetch ).not.toHaveBeenCalled();
	} );
} );
