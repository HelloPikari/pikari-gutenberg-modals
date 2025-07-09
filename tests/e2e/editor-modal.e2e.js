/**
 * End-to-end tests for modal creation in the editor
 */

const { test, expect } = require( '@playwright/test' );

test.describe( 'Modal Link Editor Experience', () => {
	test.beforeEach( async ( { page } ) => {
		// Navigate to create new post
		await page.goto( '/wp-admin/post-new.php' );
		
		// Wait for editor to load
		await page.waitForSelector( '.block-editor-writing-flow' );
	} );

	test( 'should add modal link to paragraph', async ( { page } ) => {
		// Add a paragraph block
		await page.click( 'button[aria-label="Add block"]' );
		await page.fill( 'input[placeholder="Search"]', 'paragraph' );
		await page.click( 'button[role="option"]:has-text("Paragraph")' );
		
		// Type some text
		await page.keyboard.type( 'This is a paragraph with a modal link.' );
		
		// Select "modal link" text
		await page.keyboard.press( 'Shift+Home' );
		for ( let i = 0; i < 11; i++ ) {
			await page.keyboard.press( 'Shift+ArrowLeft' );
		}
		
		// Click modal format button
		await page.click( 'button[aria-label="Modal Link"]' );
		
		// Wait for link control to appear
		await page.waitForSelector( '.block-editor-link-control' );
		
		// Search for content
		await page.fill( '.block-editor-link-control input[type="text"]', 'sample page' );
		await page.waitForSelector( '.block-editor-link-control__search-results' );
		
		// Select first result
		await page.click( '.block-editor-link-control__search-item:first-child' );
		
		// Submit
		await page.click( 'button[type="submit"]' );
		
		// Verify modal link was created
		const content = await page.locator( '.block-editor-rich-text__editable' ).innerHTML();
		expect( content ).toContain( 'modal-link-trigger' );
		expect( content ).toContain( 'data-modal-content-type' );
		expect( content ).toContain( 'data-modal-content-id' );
	} );

	test( 'should edit existing modal link', async ( { page } ) => {
		// Create paragraph with existing modal link
		await page.evaluate( () => {
			const block = wp.blocks.createBlock( 'core/paragraph', {
				content: 'Text with <span class="modal-link-trigger" data-modal-content-type="post" data-modal-content-id="1">existing modal</span>.',
			} );
			wp.data.dispatch( 'core/block-editor' ).insertBlock( block );
		} );
		
		// Click on the modal link text
		await page.click( '.modal-link-trigger' );
		
		// Click modal format button to edit
		await page.click( 'button[aria-label="Modal Link"][aria-pressed="true"]' );
		
		// Should show link control with existing data
		await page.waitForSelector( '.block-editor-link-control' );
		
		// Change the link
		await page.fill( '.block-editor-link-control input[type="text"]', 'different page' );
		await page.waitForSelector( '.block-editor-link-control__search-results' );
		await page.click( '.block-editor-link-control__search-item:first-child' );
		await page.click( 'button[type="submit"]' );
		
		// Verify update
		const content = await page.locator( '.block-editor-rich-text__editable' ).innerHTML();
		expect( content ).toContain( 'modal-link-trigger' );
	} );

	test( 'should remove modal link', async ( { page } ) => {
		// Create paragraph with modal link
		await page.evaluate( () => {
			const block = wp.blocks.createBlock( 'core/paragraph', {
				content: 'Text with <span class="modal-link-trigger" data-modal-content-type="post" data-modal-content-id="1">modal to remove</span>.',
			} );
			wp.data.dispatch( 'core/block-editor' ).insertBlock( block );
		} );
		
		// Select the modal link text
		await page.click( '.modal-link-trigger' );
		await page.keyboard.down( 'Shift' );
		await page.click( '.modal-link-trigger', { position: { x: 0, y: 10 } } );
		await page.keyboard.up( 'Shift' );
		
		// Click modal format button to remove
		await page.click( 'button[aria-label="Modal Link"][aria-pressed="true"]' );
		
		// Verify modal link was removed
		const content = await page.locator( '.block-editor-rich-text__editable' ).innerHTML();
		expect( content ).not.toContain( 'modal-link-trigger' );
		expect( content ).toContain( 'modal to remove' ); // Text should remain
	} );

	test( 'should handle external URL modal', async ( { page } ) => {
		// Add paragraph
		await page.click( 'button[aria-label="Add block"]' );
		await page.fill( 'input[placeholder="Search"]', 'paragraph' );
		await page.click( 'button[role="option"]:has-text("Paragraph")' );
		
		// Type and select text
		await page.keyboard.type( 'External link modal' );
		await page.keyboard.press( 'Control+A' );
		
		// Add modal link
		await page.click( 'button[aria-label="Modal Link"]' );
		
		// Enter external URL
		await page.fill( '.block-editor-link-control input[type="text"]', 'https://example.com' );
		await page.keyboard.press( 'Enter' );
		
		// Verify external URL modal
		const content = await page.locator( '.block-editor-rich-text__editable' ).innerHTML();
		expect( content ).toContain( 'data-modal-content-type="url"' );
		expect( content ).toContain( 'data-modal-content-id="https://example.com"' );
	} );

	test( 'should not show modal button for unsupported blocks', async ( { page } ) => {
		// Add a button block
		await page.click( 'button[aria-label="Add block"]' );
		await page.fill( 'input[placeholder="Search"]', 'button' );
		await page.click( 'button[role="option"]:has-text("Button")' );
		
		// Type text in button
		await page.keyboard.type( 'Button text' );
		
		// Select text
		await page.keyboard.press( 'Control+A' );
		
		// Modal button should not be available
		const modalButton = await page.locator( 'button[aria-label="Modal Link"]' );
		expect( await modalButton.count() ).toBe( 0 );
	} );
} );