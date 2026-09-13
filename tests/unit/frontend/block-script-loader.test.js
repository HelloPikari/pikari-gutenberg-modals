/**
 * Block script loader.
 *
 * Scripts inside content set with innerHTML never run, so the scripts a
 * modal's content needs arrive as data and are appended one at a time — each
 * may depend on the one before it. Element ids follow WP_Scripts ("{handle}-js",
 * "-js-extra", "-js-before", "-js-after") so a script the page already printed
 * is recognised and not run twice.
 */

import { loadBlockScripts } from '../../../src/frontend/block-script-loader';

/**
 * Build a script entry as the modal-content endpoint returns it.
 *
 * @param {string}      handle Script handle.
 * @param {string|null} src    Script URL, or null for inline only.
 * @param {Object}      extra  data, before and after.
 * @return {Object} The entry.
 */
function entry( handle, src = null, extra = {} ) {
	return { handle, src, data: '', before: '', after: '', ...extra };
}

/**
 * Let pending promise callbacks run.
 *
 * @return {Promise} Resolves on the next macrotask.
 */
function flush() {
	return new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
}

/**
 * Fire an event on a script element by id.
 *
 * @param {string} id   Element id.
 * @param {string} type Event type.
 */
function fire( id, type = 'load' ) {
	document.getElementById( id ).dispatchEvent( new Event( type ) );
}

describe( 'block script loader', () => {
	beforeEach( () => {
		document.head.innerHTML = '';
		document.body.innerHTML = '';
	} );

	it( 'resolves with nothing to load', async () => {
		await expect( loadBlockScripts( [] ) ).resolves.toEqual( [] );
		await expect( loadBlockScripts( undefined ) ).resolves.toEqual( [] );
	} );

	it( 'appends a script for a src, with the id core would give it', () => {
		loadBlockScripts( [ entry( 'wpforms', 'https://example.com/wpforms.min.js?ver=1' ) ] );

		const script = document.getElementById( 'wpforms-js' );
		expect( script ).not.toBeNull();
		expect( script.src ).toBe( 'https://example.com/wpforms.min.js?ver=1' );
	} );

	it( 'waits for each script to load before appending the next', async () => {
		const done = loadBlockScripts( [
			entry( 'jquery-core', 'https://example.com/jquery.js' ),
			entry( 'wpforms', 'https://example.com/wpforms.js' ),
		] );

		await flush();
		expect( document.getElementById( 'wpforms-js' ) ).toBeNull();

		fire( 'jquery-core-js' );
		await flush();
		expect( document.getElementById( 'wpforms-js' ) ).not.toBeNull();

		fire( 'wpforms-js' );
		await expect( done ).resolves.toEqual( [ 'jquery-core', 'wpforms' ] );
	} );

	it( 'runs localized data, before, the file and after, in that order', async () => {
		const done = loadBlockScripts( [
			entry( 'wpforms', 'https://example.com/wpforms.js', {
				data: 'var wpforms_utils = {};',
				before: 'window.before = 1;',
				after: 'window.after = 1;',
			} ),
		] );

		await flush();
		fire( 'wpforms-js' );
		await done;

		const ids = Array.from( document.querySelectorAll( 'script' ) ).map( ( s ) => s.id );
		expect( ids ).toEqual( [ 'wpforms-js-extra', 'wpforms-js-before', 'wpforms-js', 'wpforms-js-after' ] );
		expect( document.getElementById( 'wpforms-js-extra' ).textContent ).toBe( 'var wpforms_utils = {};' );
		expect( document.getElementById( 'wpforms-js-after' ).textContent ).toBe( 'window.after = 1;' );
	} );

	it( 'runs an inline-only handle without waiting for a load event', async () => {
		await loadBlockScripts( [ entry( 'init-forms', null, { after: 'window.initRan = true;' } ) ] );

		expect( document.getElementById( 'init-forms-js-after' ) ).not.toBeNull();
		expect( window.initRan ).toBe( true );
		delete window.initRan;
	} );

	it( 'skips a file the page already loaded, whatever its version', async () => {
		const existing = document.createElement( 'script' );
		existing.src = 'https://example.com/wp-content/plugins/wpforms-lite/wpforms.min.js?ver=0.9';
		document.body.appendChild( existing );

		await loadBlockScripts( [
			entry( 'wpforms', 'https://example.com/wp-content/plugins/wpforms-lite/wpforms.min.js?ver=1.9', {
				data: 'var wpforms_utils = {};',
			} ),
		] );

		expect( document.getElementById( 'wpforms-js' ) ).toBeNull();
		expect( document.getElementById( 'wpforms-js-extra' ) ).toBeNull();
	} );

	it( 'skips a handle whose element core already printed', async () => {
		document.body.innerHTML = '<script id="asenha-js-after">already();</script>';

		await loadBlockScripts( [ entry( 'asenha', null, { after: 'already();' } ) ] );

		expect( document.querySelectorAll( '#asenha-js-after' ) ).toHaveLength( 1 );
	} );

	/**
	 * A script loaded fresh initialises itself; one the page already had
	 * does not see the new content. Callers need to know which is which.
	 */
	it( 'reports the handles it appended, not the ones it skipped', async () => {
		document.body.innerHTML = '<script id="asenha-js-after">window.asenha = 1;</script>';

		const done = loadBlockScripts( [
			entry( 'asenha', null, { after: 'window.asenha = 1;' } ),
			entry( 'wpforms', 'https://example.com/wpforms.js' ),
		] );

		await flush();
		fire( 'wpforms-js' );

		await expect( done ).resolves.toEqual( [ 'wpforms' ] );
	} );

	it( 'carries on past a file that fails to load', async () => {
		const warn = jest.spyOn( console, 'warn' ).mockImplementation( () => {} );

		const done = loadBlockScripts( [
			entry( 'broken', 'https://example.com/404.js' ),
			entry( 'next', null, { after: 'window.nextRan = true;' } ),
		] );

		await flush();
		fire( 'broken-js', 'error' );
		await done;

		expect( window.nextRan ).toBe( true );
		delete window.nextRan;
		warn.mockRestore();
	} );
} );
