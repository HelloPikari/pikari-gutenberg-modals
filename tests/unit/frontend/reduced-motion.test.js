/**
 * Guards the prefers-reduced-motion override against selector drift.
 *
 * The override is only useful if it names the same elements that actually
 * animate. Those two lists were allowed to diverge once already: the media
 * block targeted `.modal-entering` / `.modal-leaving` while the store applied
 * `is-open` / `is-closing`, so reduced-motion users kept getting the full
 * fade-and-scale.
 *
 * @see src/blocks/modal-dialog/style.css
 */

import { readFileSync } from 'fs';
import { join } from 'path';

const CSS_PATH = join(
	__dirname,
	'../../../src/blocks/modal-dialog/style.css'
);

/**
 * Strip comments so they cannot contribute selectors or declarations.
 *
 * @param {string} css Raw stylesheet text.
 * @return {string} Stylesheet without comments.
 */
function stripComments( css ) {
	return css.replace( /\/\*[\s\S]*?\*\//g, '' );
}

/**
 * Extract the body of the prefers-reduced-motion media block.
 *
 * @param {string} css Stylesheet text.
 * @return {string} The block body, or an empty string when absent.
 */
function reducedMotionBlock( css ) {
	const start = css.search(
		/@media[^{]*prefers-reduced-motion\s*:\s*reduce[^{]*\{/
	);
	if ( start === -1 ) {
		return '';
	}

	// Walk braces from the media block's opening brace to its matching close.
	const from = css.indexOf( '{', start );
	let depth = 0;
	for ( let i = from; i < css.length; i++ ) {
		if ( css[ i ] === '{' ) {
			depth++;
		} else if ( css[ i ] === '}' ) {
			depth--;
			if ( depth === 0 ) {
				return css.slice( from + 1, i );
			}
		}
	}
	return '';
}

/**
 * Collect selectors of rules declaring an `animation` shorthand.
 *
 * @param {string} css Stylesheet text.
 * @return {string[]} Selectors, trimmed and flattened across comma lists.
 */
function selectorsThatAnimate( css ) {
	const found = [];
	const rulePattern = /([^{}]+)\{([^{}]*)\}/g;
	let match;
	while ( ( match = rulePattern.exec( css ) ) !== null ) {
		const [ , selector, body ] = match;
		if ( /(^|[;\s])animation\s*:/.test( body ) ) {
			selector
				.split( ',' )
				.map( ( s ) => s.trim() )
				.filter( Boolean )
				.forEach( ( s ) => found.push( s ) );
		}
	}
	return found;
}

describe( 'prefers-reduced-motion override', () => {
	const css = stripComments( readFileSync( CSS_PATH, 'utf8' ) );
	const block = reducedMotionBlock( css );

	// Rules outside the media block are the ones that actually animate.
	const animating = selectorsThatAnimate( css.replace( block, '' ) );
	const overridden = selectorsThatAnimate( block );

	it( 'has a prefers-reduced-motion block', () => {
		expect( block ).not.toBe( '' );
	} );

	it( 'animates something worth overriding', () => {
		expect( animating.length ).toBeGreaterThan( 0 );
	} );

	it( 'overrides every selector that animates', () => {
		const missing = animating.filter( ( s ) => ! overridden.includes( s ) );
		expect( missing ).toEqual( [] );
	} );

	it( 'overrides nothing that does not animate', () => {
		const stale = overridden.filter( ( s ) => ! animating.includes( s ) );
		expect( stale ).toEqual( [] );
	} );
} );
