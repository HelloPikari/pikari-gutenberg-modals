/**
 * Block Script Loader
 *
 * Runs the classic scripts modal content needs. Content set with innerHTML
 * never executes its scripts, so they arrive from the modal-content endpoint
 * as data and are appended here one at a time, each after the one it may
 * depend on. Element ids follow WP_Scripts, so a script the page already
 * printed is recognised rather than run twice.
 */

/**
 * Get the pathname from a URL for comparison.
 *
 * @param {string} url - The URL to extract pathname from.
 * @return {string} The pathname.
 */
function getPathname( url ) {
	try {
		return new URL( url, window.location.origin ).pathname;
	} catch {
		return url;
	}
}

/**
 * Whether the page already has this handle.
 *
 * A file is matched by pathname as well as id, because the same file may have
 * been printed under a different version query.
 *
 * @param {Object} script - Script entry from the modal-content endpoint.
 * @return {boolean} True when the handle should be skipped.
 */
function isOnPage( script ) {
	const { handle, src } = script;

	if ( src ) {
		const pathname = getPathname( src );

		return (
			!! document.getElementById( `${ handle }-js` ) ||
			Array.from( document.scripts ).some(
				( element ) => element.src && getPathname( element.src ) === pathname
			)
		);
	}

	return [ 'extra', 'before', 'after' ].some( ( part ) =>
		document.getElementById( `${ handle }-js-${ part }` )
	);
}

/**
 * Append and run an inline script.
 *
 * @param {string} id   - Element id.
 * @param {string} code - JavaScript source. Nothing is appended when empty.
 */
function appendInline( id, code ) {
	if ( ! code ) {
		return;
	}

	const element = document.createElement( 'script' );
	element.id = id;
	element.textContent = code;
	document.body.appendChild( element );
}

/**
 * Append a script file and wait for it.
 *
 * Resolves on error too: one missing file should not keep the rest of the
 * modal's scripts from running.
 *
 * @param {string} id  - Element id.
 * @param {string} src - Script URL.
 * @return {Promise} Resolves once the file has loaded or failed.
 */
function appendFile( id, src ) {
	return new Promise( ( resolve ) => {
		const element = document.createElement( 'script' );
		element.id = id;
		element.src = src;
		element.onload = () => resolve();
		element.onerror = () => {
			// eslint-disable-next-line no-console
			console.warn( `Failed to load modal content script: ${ src }` );
			resolve();
		};
		document.body.appendChild( element );
	} );
}

/**
 * Run the scripts modal content needs, in order.
 *
 * A script loaded fresh initialises itself against the content; one the page
 * already had does not see it. The resolved handles tell callers which is which.
 *
 * @param {Object[]} scripts - Entries of { handle, src, data, before, after }, dependencies first.
 * @return {Promise<string[]>} Resolves with the handles appended, once every script has run.
 */
export async function loadBlockScripts( scripts ) {
	const loaded = [];

	for ( const script of scripts || [] ) {
		if ( isOnPage( script ) ) {
			continue;
		}

		appendInline( `${ script.handle }-js-extra`, script.data );
		appendInline( `${ script.handle }-js-before`, script.before );

		if ( script.src ) {
			// Sequential by design: the next script may depend on this one.
			// eslint-disable-next-line no-await-in-loop
			await appendFile( `${ script.handle }-js`, script.src );
		}

		appendInline( `${ script.handle }-js-after`, script.after );
		loaded.push( script.handle );
	}

	return loaded;
}
