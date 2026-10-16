/**
 * Visual baselines: the closed triggers, and the default modal open on the
 * target page's content, at desktop and mobile widths.
 *
 * Baselines are `<name>-chromium.png` under `__snapshots__/`, generated on
 * macOS / Chromium and committed. Playwright does not run on CI, so nothing
 * regenerates them automatically, and a baseline from one platform will not
 * pixel-match a render on another: a CI job would need its own Linux
 * baselines or to skip this file. Every Playwright bump changes the bundled
 * Chromium, so expect to regenerate with `npm run test:e2e -- visual.spec.js
 * --update-snapshots` and review the new images before committing them.
 */
const { test, expect } = require( '@playwright/test' );
const { TRIGGERS } = require( '../fixtures/content' );
const {
	DIALOG,
	triggers,
	visitor,
	waitForModalContent,
} = require( '../utils' );

test.use( { storageState: visitor } );

const VIEWPORTS = {
	desktop: { width: 1280, height: 800 },
	mobile: { width: 390, height: 844 },
};

for ( const [ name, viewport ] of Object.entries( VIEWPORTS ) ) {
	test.describe( name, () => {
		test.use( { viewport } );

		test( 'closed triggers', async ( { page } ) => {
			await page.goto( TRIGGERS.path );

			await expect( page.locator( 'main' ) ).toHaveScreenshot(
				`triggers-${ name }.png`,
				{ animations: 'disabled' }
			);
		} );

		test( 'open default modal', async ( { page } ) => {
			await page.goto( TRIGGERS.path );
			await triggers( page ).button.click();
			await waitForModalContent( page );
			await expect( page.locator( DIALOG ) ).toHaveClass( /is-open/ );

			// The whole viewport, so the backdrop and placement are pinned too.
			await expect( page ).toHaveScreenshot( `modal-open-${ name }.png`, {
				animations: 'disabled',
			} );
		} );
	} );
}
