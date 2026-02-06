/**
 * Block Style Loader
 *
 * Dynamically loads block stylesheets for modal content.
 * Prevents duplicate loading by checking existing stylesheets on the page.
 */

/**
 * Load a single stylesheet and return a promise.
 *
 * @param {string} url - The stylesheet URL to load.
 * @return {Promise} Promise that resolves when stylesheet is loaded.
 */
function loadStylesheet( url ) {
	return new Promise( ( resolve, reject ) => {
		const link = document.createElement( 'link' );
		link.rel = 'stylesheet';
		link.href = url;
		link.onload = () => resolve( url );
		link.onerror = () => reject( new Error( `Failed to load stylesheet: ${ url }` ) );
		document.head.appendChild( link );
	} );
}

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
 * Load block stylesheets that aren't already on the page.
 *
 * Checks existing stylesheets to avoid duplicates, then loads any new ones.
 * Returns a promise that resolves when all stylesheets are loaded.
 *
 * @param {string[]} styleUrls - Array of stylesheet URLs to load.
 * @return {Promise} Promise that resolves when all stylesheets are loaded.
 */
export function loadBlockStyles( styleUrls ) {
	if ( ! styleUrls || ! styleUrls.length ) {
		return Promise.resolve();
	}

	// Get pathnames of existing stylesheets for comparison
	const existingLinks = new Set(
		Array.from( document.querySelectorAll( 'link[rel="stylesheet"]' ) ).map(
			( link ) => getPathname( link.href )
		)
	);

	// Filter to only stylesheets not already loaded
	const newUrls = styleUrls.filter( ( url ) => {
		const pathname = getPathname( url );
		return ! existingLinks.has( pathname );
	} );

	if ( ! newUrls.length ) {
		return Promise.resolve();
	}

	// Load all new stylesheets in parallel
	return Promise.all( newUrls.map( loadStylesheet ) ).catch( ( error ) => {
		// Log error but don't reject - we still want to show modal content
		// eslint-disable-next-line no-console
		console.warn( 'Some block styles failed to load:', error );
	} );
}
