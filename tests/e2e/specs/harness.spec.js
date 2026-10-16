/**
 * Smoke test: the fixture pages render, and the server turned each trigger
 * into an interactive element.
 */
const { test, expect } = require( '@playwright/test' );
const { TARGET, TRIGGERS } = require( '../fixtures/content' );
const { DIALOG, triggers, visitor } = require( '../utils' );

test.use( { storageState: visitor } );

test( 'renders the target page', async ( { page } ) => {
	const response = await page.goto( TARGET.path );

	expect( response.status() ).toBe( 200 );
	await expect( page.getByText( TARGET.body ) ).toBeVisible();
} );

test( 'renders every trigger type and one closed dialog', async ( { page } ) => {
	const response = await page.goto( TRIGGERS.path );
	expect( response.status() ).toBe( 200 );

	for ( const trigger of Object.values( triggers( page ) ) ) {
		await expect( trigger ).toHaveAttribute( 'aria-haspopup', 'dialog' );
	}

	await expect( page.locator( DIALOG ) ).toHaveCount( 1 );
	await expect( page.locator( DIALOG ) ).toBeHidden();
} );
