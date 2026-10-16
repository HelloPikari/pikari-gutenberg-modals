/**
 * The dialog title is for assistive technology only: present in the
 * accessibility tree, visually hidden. #162 moved `.sr-only` from the
 * deprecated `clip` to `clip-path: inset(50%)`.
 */
const { test, expect } = require( '@playwright/test' );
const { TARGET, TRIGGERS } = require( '../fixtures/content' );
const { triggers, visitor, waitForModalContent } = require( '../utils' );

test.use( { storageState: visitor } );

test( 'the dialog title is visually hidden at 1×1', async ( { page } ) => {
	await page.goto( TRIGGERS.path );
	await triggers( page ).button.click();
	await waitForModalContent( page );

	const title = page.locator( '#modal-title--modal' );
	await expect( title ).toHaveText( TARGET.title );

	const box = await title.boundingBox();
	expect( box.width ).toBe( 1 );
	expect( box.height ).toBe( 1 );

	const style = await title.evaluate( ( element ) => {
		const computed = window.getComputedStyle( element );
		return {
			position: computed.position,
			overflow: computed.overflow,
			clipPath: computed.clipPath,
		};
	} );
	expect( style ).toEqual( {
		position: 'absolute',
		overflow: 'hidden',
		clipPath: 'inset(50%)',
	} );
} );
