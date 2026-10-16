/**
 * Progressive enhancement: without JavaScript every trigger is a real link
 * that navigates to the content the modal would have shown.
 */
const { test, expect } = require( '@playwright/test' );
const { TARGET, TRIGGERS } = require( '../fixtures/content' );
const { triggers, visitor } = require( '../utils' );

test.use( { javaScriptEnabled: false, storageState: visitor } );

for ( const type of [ 'inline', 'button', 'group' ] ) {
	test( `the ${ type } trigger navigates to the target`, async ( { page } ) => {
		await page.goto( TRIGGERS.path );
		await triggers( page )[ type ].click();

		await page.waitForURL( `**${ TARGET.path }` );
		await expect( page.getByText( TARGET.body ) ).toBeVisible();
	} );
}
