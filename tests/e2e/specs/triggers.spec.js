/**
 * Every open-mode trigger type opens the default modal with the target's
 * content, moves focus into the dialog and keeps it there, and hands focus
 * back to the trigger when the dialog closes by Esc or by its close button.
 */
const { test, expect } = require( '@playwright/test' );
const { TARGET, TRIGGERS } = require( '../fixtures/content' );
const {
	DIALOG,
	triggers,
	visitor,
	waitForModalContent,
} = require( '../utils' );

test.use( { storageState: visitor } );

/**
 * Where focus is, relative to the dialog.
 *
 * The trap is `inert` on the page landmarks plus a focusin redirect, so
 * Tab past the dialog's last element leaves the document for the browser's
 * own UI (activeElement is <body>), and the next Tab comes back to the
 * dialog. That is how a native modal <dialog> behaves too. What must never
 * happen is focus landing on a page element outside the dialog.
 *
 * @param {import('@playwright/test').Page} page Page.
 * @return {Promise<string>} 'inside', 'document' (no element), or 'outside'.
 */
const focusPosition = ( page ) =>
	page.evaluate( ( selector ) => {
		const active = document.activeElement;
		if ( document.querySelector( selector ).contains( active ) ) {
			return 'inside';
		}
		return ! active || active === document.body ? 'document' : 'outside';
	}, DIALOG );

for ( const type of [ 'inline', 'button', 'group' ] ) {
	test.describe( `${ type } trigger`, () => {
		test.beforeEach( async ( { page } ) => {
			await page.goto( TRIGGERS.path );
		} );

		test( 'opens the dialog with the target content', async ( { page } ) => {
			const trigger = triggers( page )[ type ];
			const dialog = page.locator( DIALOG );

			await trigger.click();
			await waitForModalContent( page );

			await expect( dialog ).toBeVisible();
			await expect( dialog ).toHaveAttribute( 'role', 'dialog' );
			await expect( dialog ).toHaveAttribute( 'aria-modal', 'true' );
			await expect( dialog.getByText( TARGET.body ) ).toBeVisible();

			// The click opened a dialog; it did not navigate.
			expect( new URL( page.url() ).pathname ).toBe( TRIGGERS.path );
		} );

		test( 'is named by the target title', async ( { page } ) => {
			await triggers( page )[ type ].click();
			await waitForModalContent( page );

			// aria-labelledby points at a title the store writes on open.
			const dialog = page.locator( DIALOG );
			await expect( dialog ).toHaveAttribute(
				'aria-labelledby',
				'modal-title--modal'
			);
			await expect( page.locator( '#modal-title--modal' ) ).toHaveText(
				TARGET.title
			);
			await expect(
				page.getByRole( 'dialog', { name: TARGET.title } )
			).toBeVisible();
		} );

		test( 'moves focus into the dialog and traps it', async ( { page } ) => {
			await triggers( page )[ type ].click();
			await waitForModalContent( page );

			await expect.poll( () => focusPosition( page ) ).toBe( 'inside' );

			// More presses than the dialog has focusable elements, both ways.
			for ( const key of [ 'Tab', 'Shift+Tab' ] ) {
				const seen = new Set();
				for ( let i = 0; i < 8; i++ ) {
					await page.keyboard.press( key );
					seen.add( await focusPosition( page ) );
				}
				expect( seen ).not.toContain( 'outside' );
				expect( seen ).toContain( 'inside' );
			}
		} );

		test( 'makes the page behind it inert while open', async ( { page } ) => {
			const main = page.locator( 'main' );

			await triggers( page )[ type ].click();
			await waitForModalContent( page );
			await expect( main ).toHaveAttribute( 'inert', '' );

			await page.keyboard.press( 'Escape' );
			await expect( main ).not.toHaveAttribute( 'inert' );
		} );

		test( 'closes on Esc and returns focus to the trigger', async ( {
			page,
		} ) => {
			const trigger = triggers( page )[ type ];

			await trigger.click();
			await waitForModalContent( page );
			await page.keyboard.press( 'Escape' );

			await expect( page.locator( DIALOG ) ).toBeHidden();
			await expect( trigger ).toBeFocused();
		} );

		test( 'announces its expanded state', async ( { page } ) => {
			const trigger = triggers( page )[ type ];

			await expect( trigger ).toHaveAttribute( 'aria-expanded', 'false' );
			await trigger.click();
			await waitForModalContent( page );
			await expect( trigger ).toHaveAttribute( 'aria-expanded', 'true' );
			await page.keyboard.press( 'Escape' );
			await expect( trigger ).toHaveAttribute( 'aria-expanded', 'false' );
		} );

		test( 'closes from the close button and returns focus to the trigger', async ( {
			page,
		} ) => {
			const trigger = triggers( page )[ type ];
			const dialog = page.locator( DIALOG );

			await trigger.click();
			await waitForModalContent( page );
			await dialog.getByRole( 'button', { name: 'Close dialog' } ).click();

			await expect( dialog ).toBeHidden();
			await expect( trigger ).toBeFocused();
		} );
	} );
}
