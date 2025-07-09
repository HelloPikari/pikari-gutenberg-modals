/**
 * End-to-end tests for modal display on frontend
 */

const { test, expect } = require( '@playwright/test' );

test.describe( 'Frontend Modal Display', () => {
	// Test post ID with modal content
	const testPostUrl = '/test-post-with-modal/';
	
	test.beforeEach( async ( { page } ) => {
		// Navigate to a post that contains modal links
		await page.goto( testPostUrl );
		
		// Wait for page to load
		await page.waitForLoadState( 'networkidle' );
	} );

	test( 'should open modal on trigger click', async ( { page } ) => {
		// Click modal trigger
		await page.click( '.modal-link-trigger' );
		
		// Wait for modal to appear
		await page.waitForSelector( '.modal-overlay.is-open' );
		
		// Verify modal is visible
		const modal = await page.locator( '.modal-overlay' );
		await expect( modal ).toBeVisible();
		
		// Verify content loaded
		const modalBody = await page.locator( '.modal-body' );
		await expect( modalBody ).not.toContainText( 'Loading...' );
		await expect( modalBody ).toContainText( 'modal content' );
	} );

	test( 'should close modal on X button click', async ( { page } ) => {
		// Open modal
		await page.click( '.modal-link-trigger' );
		await page.waitForSelector( '.modal-overlay.is-open' );
		
		// Click close button
		await page.click( '.modal-close' );
		
		// Verify modal is closed
		const modal = await page.locator( '.modal-overlay' );
		await expect( modal ).not.toHaveClass( 'is-open' );
	} );

	test( 'should close modal on overlay click', async ( { page } ) => {
		// Open modal
		await page.click( '.modal-link-trigger' );
		await page.waitForSelector( '.modal-overlay.is-open' );
		
		// Click overlay (outside content)
		await page.click( '.modal-overlay', {
			position: { x: 10, y: 10 }, // Click near edge
		} );
		
		// Verify modal is closed
		const modal = await page.locator( '.modal-overlay' );
		await expect( modal ).not.toHaveClass( 'is-open' );
	} );

	test( 'should not close modal on content click', async ( { page } ) => {
		// Open modal
		await page.click( '.modal-link-trigger' );
		await page.waitForSelector( '.modal-overlay.is-open' );
		
		// Click modal content
		await page.click( '.modal-content' );
		
		// Verify modal is still open
		const modal = await page.locator( '.modal-overlay' );
		await expect( modal ).toHaveClass( 'is-open' );
	} );

	test( 'should close modal on Escape key', async ( { page } ) => {
		// Open modal
		await page.click( '.modal-link-trigger' );
		await page.waitForSelector( '.modal-overlay.is-open' );
		
		// Press Escape
		await page.keyboard.press( 'Escape' );
		
		// Verify modal is closed
		const modal = await page.locator( '.modal-overlay' );
		await expect( modal ).not.toHaveClass( 'is-open' );
	} );

	test( 'should trap focus within modal', async ( { page } ) => {
		// Open modal
		await page.click( '.modal-link-trigger' );
		await page.waitForSelector( '.modal-overlay.is-open' );
		
		// Tab through focusable elements
		await page.keyboard.press( 'Tab' );
		
		// Should focus close button first
		const closeButton = await page.locator( '.modal-close' );
		await expect( closeButton ).toBeFocused();
		
		// Tab to content
		await page.keyboard.press( 'Tab' );
		
		// Tab back to close button (focus trap)
		await page.keyboard.press( 'Tab' );
		await expect( closeButton ).toBeFocused();
	} );

	test( 'should return focus to trigger after close', async ( { page } ) => {
		// Get trigger element
		const trigger = await page.locator( '.modal-link-trigger' ).first();
		
		// Focus and click trigger
		await trigger.focus();
		await trigger.click();
		
		// Wait for modal
		await page.waitForSelector( '.modal-overlay.is-open' );
		
		// Close modal
		await page.keyboard.press( 'Escape' );
		
		// Verify focus returned to trigger
		await expect( trigger ).toBeFocused();
	} );

	test( 'should handle multiple modals on same page', async ( { page } ) => {
		// Assuming page has multiple modal triggers
		const triggers = await page.locator( '.modal-link-trigger' );
		const count = await triggers.count();
		
		if ( count > 1 ) {
			// Click first modal
			await triggers.nth( 0 ).click();
			await page.waitForSelector( '.modal-overlay.is-open' );
			
			// Get content
			const firstContent = await page.locator( '.modal-body' ).textContent();
			
			// Close modal
			await page.keyboard.press( 'Escape' );
			
			// Click second modal
			await triggers.nth( 1 ).click();
			await page.waitForSelector( '.modal-overlay.is-open' );
			
			// Get content
			const secondContent = await page.locator( '.modal-body' ).textContent();
			
			// Content should be different
			expect( firstContent ).not.toEqual( secondContent );
		}
	} );

	test( 'should load external URL content', async ( { page } ) => {
		// Find external URL modal trigger
		const externalTrigger = await page.locator(
			'.modal-link-trigger[data-modal-content-type="url"]'
		).first();
		
		if ( await externalTrigger.count() > 0 ) {
			await externalTrigger.click();
			
			// Wait for modal and content
			await page.waitForSelector( '.modal-overlay.is-open' );
			await page.waitForFunction(
				() => ! document.querySelector( '.modal-body' )?.textContent?.includes( 'Loading' ),
				{ timeout: 10000 }
			);
			
			// Verify external content loaded
			const modalBody = await page.locator( '.modal-body' );
			await expect( modalBody ).not.toBeEmpty();
		}
	} );

	test( 'should handle keyboard navigation for triggers', async ( { page } ) => {
		// Tab to first modal trigger
		await page.keyboard.press( 'Tab' );
		
		// Find focused trigger
		const focusedTrigger = await page.locator( '.modal-link-trigger:focus' );
		
		if ( await focusedTrigger.count() > 0 ) {
			// Activate with Enter key
			await page.keyboard.press( 'Enter' );
			
			// Verify modal opened
			await expect( page.locator( '.modal-overlay' ) ).toHaveClass( 'is-open' );
			
			// Close
			await page.keyboard.press( 'Escape' );
			
			// Activate with Space key
			await page.keyboard.press( ' ' );
			
			// Verify modal opened
			await expect( page.locator( '.modal-overlay' ) ).toHaveClass( 'is-open' );
		}
	} );

	test( 'should show error for failed content load', async ( { page } ) => {
		// Intercept API request to simulate failure
		await page.route( '**/wp-json/pikari-gutenberg-modals/v1/modal-content/*', ( route ) => {
			route.abort( 'failed' );
		} );
		
		// Click modal trigger
		await page.click( '.modal-link-trigger' );
		
		// Wait for error message
		await page.waitForSelector( '.modal-error' );
		
		// Verify error displayed
		const errorMessage = await page.locator( '.modal-error' );
		await expect( errorMessage ).toContainText( 'Error loading content' );
	} );
} );