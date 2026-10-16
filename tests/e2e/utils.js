/**
 * Helpers shared by the end-to-end specs.
 */
const { LABELS } = require( './fixtures/content' );

// Browse as a logged-out visitor rather than the admin storage state. A
// logged-in viewer gets a REST nonce and skips hover prefetch, a different
// path from the one site visitors take.
const visitor = { cookies: [], origins: [] };

// The default modal container, rendered in wp_footer.
const DIALOG = '#pikari-modal';

/**
 * Each open-mode trigger on the TRIGGERS page, by type.
 *
 * The Group card's trigger is its primary link: the card wrapper takes the
 * click, but the link is what a keyboard user focuses.
 *
 * @param {import('@playwright/test').Page} page Page.
 * @return {Object<string, import('@playwright/test').Locator>} Triggers.
 */
const triggers = ( page ) => ( {
	inline: page.getByRole( 'link', { name: LABELS.inline } ),
	button: page.getByRole( 'link', { name: LABELS.button } ),
	group: page.getByRole( 'link', { name: LABELS.group } ),
} );

/**
 * Wait until the open modal has finished loading its content.
 *
 * @param {import('@playwright/test').Page} page Page.
 */
const waitForModalContent = async ( page ) => {
	const dialog = page.locator( DIALOG );
	await dialog.locator( '.modal-loading.hidden' ).waitFor( { state: 'attached' } );
	await dialog.locator( '.modal-body h2.sr-only' ).waitFor( { state: 'attached' } );
};

module.exports = {
	DIALOG,
	triggers,
	visitor,
	waitForModalContent,
};
