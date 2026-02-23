/**
 * Tests for findLinksInBlocks utility.
 *
 * @see src/editor/find-links-in-blocks.js
 */

import findLinksInBlocks from '../../../src/editor/find-links-in-blocks';

// Helper to create a mock block
function createBlock( name, attributes = {}, innerBlocks = [] ) {
	return { name, attributes, innerBlocks };
}

describe( 'findLinksInBlocks', () => {
	describe( 'empty and no-link blocks', () => {
		it( 'should return empty array for empty blocks array', () => {
			expect( findLinksInBlocks( [] ) ).toEqual( [] );
		} );

		it( 'should return empty array for blocks without links', () => {
			const blocks = [
				createBlock( 'core/paragraph', { content: 'No links here' } ),
				createBlock( 'core/heading', { content: 'Just text' } ),
			];
			expect( findLinksInBlocks( blocks ) ).toEqual( [] );
		} );

		it( 'should return empty array for button without URL', () => {
			const blocks = [
				createBlock( 'core/button', { text: 'Click me' } ),
			];
			expect( findLinksInBlocks( blocks ) ).toEqual( [] );
		} );

		it( 'should return empty array for image without href', () => {
			const blocks = [
				createBlock( 'core/image', { alt: 'A photo' } ),
			];
			expect( findLinksInBlocks( blocks ) ).toEqual( [] );
		} );
	} );

	describe( 'button block detection', () => {
		it( 'should detect button with URL', () => {
			const blocks = [
				createBlock( 'core/button', {
					url: 'https://example.com',
					text: 'Click me',
				} ),
			];
			const result = findLinksInBlocks( blocks );

			expect( result ).toHaveLength( 1 );
			expect( result[ 0 ].identifier ).toEqual( {
				blockPath: '0',
				blockName: 'core/button',
				linkUrl: 'https://example.com',
			} );
			expect( result[ 0 ].label ).toContain( 'Click me' );
		} );

		it( 'should use URL as fallback label when text is missing', () => {
			const blocks = [
				createBlock( 'core/button', {
					url: 'https://example.com',
				} ),
			];
			const result = findLinksInBlocks( blocks );

			expect( result[ 0 ].label ).toContain( 'https://example.com' );
		} );
	} );

	describe( 'image block detection', () => {
		it( 'should detect image with href', () => {
			const blocks = [
				createBlock( 'core/image', {
					href: 'https://example.com/page',
					alt: 'My photo',
				} ),
			];
			const result = findLinksInBlocks( blocks );

			expect( result ).toHaveLength( 1 );
			expect( result[ 0 ].identifier ).toEqual( {
				blockPath: '0',
				blockName: 'core/image',
				linkUrl: 'https://example.com/page',
			} );
			expect( result[ 0 ].label ).toContain( 'My photo' );
		} );

		it( 'should use href as fallback label when alt is missing', () => {
			const blocks = [
				createBlock( 'core/image', {
					href: 'https://example.com/page',
				} ),
			];
			const result = findLinksInBlocks( blocks );

			expect( result[ 0 ].label ).toContain( 'https://example.com/page' );
		} );
	} );

	describe( 'navigation-link block detection', () => {
		it( 'should detect navigation-link with URL', () => {
			const blocks = [
				createBlock( 'core/navigation-link', {
					url: 'https://example.com',
					label: 'Home',
				} ),
			];
			const result = findLinksInBlocks( blocks );

			expect( result ).toHaveLength( 1 );
			expect( result[ 0 ].identifier ).toEqual( {
				blockPath: '0',
				blockName: 'core/navigation-link',
				linkUrl: 'https://example.com',
			} );
			expect( result[ 0 ].label ).toContain( 'Home' );
		} );

		it( 'should return empty for navigation-link without URL', () => {
			const blocks = [
				createBlock( 'core/navigation-link', { label: 'Home' } ),
			];
			expect( findLinksInBlocks( blocks ) ).toEqual( [] );
		} );
	} );

	describe( 'heading and paragraph inline links', () => {
		it( 'should detect inline link in paragraph', () => {
			const blocks = [
				createBlock( 'core/paragraph', {
					content: 'Visit <a href="https://example.com">our site</a> today',
				} ),
			];
			const result = findLinksInBlocks( blocks );

			expect( result ).toHaveLength( 1 );
			expect( result[ 0 ].identifier ).toEqual( {
				blockPath: '0.0',
				blockName: 'core/paragraph',
				linkUrl: 'https://example.com',
			} );
			expect( result[ 0 ].label ).toContain( 'our site' );
		} );

		it( 'should detect inline link in heading', () => {
			const blocks = [
				createBlock( 'core/heading', {
					content: '<a href="https://example.com">Linked Heading</a>',
				} ),
			];
			const result = findLinksInBlocks( blocks );

			expect( result ).toHaveLength( 1 );
			expect( result[ 0 ].identifier.blockName ).toBe( 'core/heading' );
			expect( result[ 0 ].label ).toContain( 'Linked Heading' );
		} );

		it( 'should detect multiple inline links in one block', () => {
			const blocks = [
				createBlock( 'core/paragraph', {
					content: '<a href="https://one.com">First</a> and <a href="https://two.com">Second</a>',
				} ),
			];
			const result = findLinksInBlocks( blocks );

			expect( result ).toHaveLength( 2 );
			expect( result[ 0 ].identifier.linkUrl ).toBe( 'https://one.com' );
			expect( result[ 0 ].identifier.blockPath ).toBe( '0.0' );
			expect( result[ 1 ].identifier.linkUrl ).toBe( 'https://two.com' );
			expect( result[ 1 ].identifier.blockPath ).toBe( '0.1' );
		} );

		it( 'should return empty for paragraph without links', () => {
			const blocks = [
				createBlock( 'core/paragraph', {
					content: 'Just plain text',
				} ),
			];
			expect( findLinksInBlocks( blocks ) ).toEqual( [] );
		} );

		it( 'should return empty for paragraph with empty content', () => {
			const blocks = [
				createBlock( 'core/paragraph', { content: '' } ),
			];
			expect( findLinksInBlocks( blocks ) ).toEqual( [] );
		} );
	} );

	describe( 'Query Loop post-link blocks', () => {
		it( 'should detect post-title with isLink enabled', () => {
			const blocks = [
				createBlock( 'core/post-title', { isLink: true } ),
			];
			const result = findLinksInBlocks( blocks );

			expect( result ).toHaveLength( 1 );
			expect( result[ 0 ].identifier ).toEqual( {
				blockPath: '0',
				blockName: 'core/post-title',
				linkType: 'post-link',
				linkUrl: null,
			} );
		} );

		it( 'should not detect post-title without isLink', () => {
			const blocks = [
				createBlock( 'core/post-title', {} ),
			];
			expect( findLinksInBlocks( blocks ) ).toEqual( [] );
		} );

		it( 'should detect post-featured-image with isLink', () => {
			const blocks = [
				createBlock( 'core/post-featured-image', { isLink: true } ),
			];
			const result = findLinksInBlocks( blocks );

			expect( result ).toHaveLength( 1 );
			expect( result[ 0 ].identifier.blockName ).toBe( 'core/post-featured-image' );
			expect( result[ 0 ].identifier.linkType ).toBe( 'post-link' );
		} );

		it( 'should detect post-date with isLink', () => {
			const blocks = [
				createBlock( 'core/post-date', { isLink: true } ),
			];
			const result = findLinksInBlocks( blocks );

			expect( result ).toHaveLength( 1 );
			expect( result[ 0 ].identifier.blockName ).toBe( 'core/post-date' );
			expect( result[ 0 ].identifier.linkType ).toBe( 'post-link' );
		} );

		it( 'should always detect read-more block', () => {
			const blocks = [
				createBlock( 'core/read-more', {} ),
			];
			const result = findLinksInBlocks( blocks );

			expect( result ).toHaveLength( 1 );
			expect( result[ 0 ].identifier ).toEqual( {
				blockPath: '0',
				blockName: 'core/read-more',
				linkType: 'post-link',
				linkUrl: null,
			} );
		} );

		it( 'should detect post-excerpt with non-empty moreText', () => {
			const blocks = [
				createBlock( 'core/post-excerpt', { moreText: 'Read full article' } ),
			];
			const result = findLinksInBlocks( blocks );

			expect( result ).toHaveLength( 1 );
			expect( result[ 0 ].identifier.blockName ).toBe( 'core/post-excerpt' );
			expect( result[ 0 ].label ).toContain( 'Read full article' );
		} );

		it( 'should not detect post-excerpt with empty moreText', () => {
			const blocks = [
				createBlock( 'core/post-excerpt', { moreText: '' } ),
			];
			expect( findLinksInBlocks( blocks ) ).toEqual( [] );
		} );

		it( 'should not detect post-excerpt with whitespace-only moreText', () => {
			const blocks = [
				createBlock( 'core/post-excerpt', { moreText: '   ' } ),
			];
			expect( findLinksInBlocks( blocks ) ).toEqual( [] );
		} );
	} );

	describe( 'recursive inner block traversal', () => {
		it( 'should detect links in nested inner blocks', () => {
			const blocks = [
				createBlock( 'core/group', {}, [
					createBlock( 'core/button', {
						url: 'https://example.com',
						text: 'Nested button',
					} ),
				] ),
			];
			const result = findLinksInBlocks( blocks );

			expect( result ).toHaveLength( 1 );
			expect( result[ 0 ].identifier.blockPath ).toBe( '0.0' );
		} );

		it( 'should handle deeply nested blocks', () => {
			const blocks = [
				createBlock( 'core/group', {}, [
					createBlock( 'core/columns', {}, [
						createBlock( 'core/column', {}, [
							createBlock( 'core/button', {
								url: 'https://deep.com',
								text: 'Deep button',
							} ),
						] ),
					] ),
				] ),
			];
			const result = findLinksInBlocks( blocks );

			expect( result ).toHaveLength( 1 );
			expect( result[ 0 ].identifier.blockPath ).toBe( '0.0.0.0' );
		} );

		it( 'should detect multiple links across nested levels', () => {
			const blocks = [
				createBlock( 'core/button', {
					url: 'https://top.com',
					text: 'Top',
				} ),
				createBlock( 'core/group', {}, [
					createBlock( 'core/image', {
						href: 'https://nested.com',
						alt: 'Nested image',
					} ),
				] ),
			];
			const result = findLinksInBlocks( blocks );

			expect( result ).toHaveLength( 2 );
			expect( result[ 0 ].identifier.blockPath ).toBe( '0' );
			expect( result[ 0 ].identifier.linkUrl ).toBe( 'https://top.com' );
			expect( result[ 1 ].identifier.blockPath ).toBe( '1.0' );
			expect( result[ 1 ].identifier.linkUrl ).toBe( 'https://nested.com' );
		} );
	} );

	describe( 'path generation', () => {
		it( 'should generate correct top-level paths', () => {
			const blocks = [
				createBlock( 'core/button', { url: 'https://a.com', text: 'A' } ),
				createBlock( 'core/button', { url: 'https://b.com', text: 'B' } ),
				createBlock( 'core/button', { url: 'https://c.com', text: 'C' } ),
			];
			const result = findLinksInBlocks( blocks );

			expect( result[ 0 ].identifier.blockPath ).toBe( '0' );
			expect( result[ 1 ].identifier.blockPath ).toBe( '1' );
			expect( result[ 2 ].identifier.blockPath ).toBe( '2' );
		} );

		it( 'should respect parentPath parameter', () => {
			const blocks = [
				createBlock( 'core/button', { url: 'https://a.com', text: 'A' } ),
			];
			const result = findLinksInBlocks( blocks, '3.1' );

			expect( result[ 0 ].identifier.blockPath ).toBe( '3.1.0' );
		} );
	} );

	describe( 'close mode: buttons without URLs', () => {
		it( 'should not detect buttons without URL by default', () => {
			const blocks = [
				createBlock( 'core/button', { text: 'Cancel' } ),
			];
			expect( findLinksInBlocks( blocks ) ).toEqual( [] );
		} );

		it( 'should detect buttons without URL when includeButtonsWithoutUrl is true', () => {
			const blocks = [
				{
					...createBlock( 'core/button', { text: 'Cancel' } ),
					clientId: 'abc-123',
				},
			];
			const result = findLinksInBlocks( blocks, '', {
				includeButtonsWithoutUrl: true,
			} );

			expect( result ).toHaveLength( 1 );
			expect( result[ 0 ].identifier ).toEqual( {
				blockPath: '0',
				blockName: 'core/button',
				clientId: 'abc-123',
			} );
			expect( result[ 0 ].label ).toContain( 'Cancel' );
		} );

		it( 'should use fallback label when button text is empty', () => {
			const blocks = [
				{
					...createBlock( 'core/button', {} ),
					clientId: 'def-456',
				},
			];
			const result = findLinksInBlocks( blocks, '', {
				includeButtonsWithoutUrl: true,
			} );

			expect( result ).toHaveLength( 1 );
			expect( result[ 0 ].label ).toContain( 'Button' );
		} );

		it( 'should still detect buttons with URL when includeButtonsWithoutUrl is true', () => {
			const blocks = [
				createBlock( 'core/button', {
					url: 'https://example.com',
					text: 'Open',
				} ),
			];
			const result = findLinksInBlocks( blocks, '', {
				includeButtonsWithoutUrl: true,
			} );

			expect( result ).toHaveLength( 1 );
			expect( result[ 0 ].identifier.linkUrl ).toBe(
				'https://example.com'
			);
		} );

		it( 'should detect both URL and non-URL buttons together', () => {
			const blocks = [
				createBlock( 'core/button', {
					url: 'https://example.com',
					text: 'Submit',
				} ),
				{
					...createBlock( 'core/button', { text: 'Cancel' } ),
					clientId: 'cancel-btn',
				},
			];
			const result = findLinksInBlocks( blocks, '', {
				includeButtonsWithoutUrl: true,
			} );

			expect( result ).toHaveLength( 2 );
			expect( result[ 0 ].identifier.linkUrl ).toBe(
				'https://example.com'
			);
			expect( result[ 1 ].identifier.clientId ).toBe( 'cancel-btn' );
		} );
	} );

	describe( 'unrecognized block types', () => {
		it( 'should ignore unknown block types', () => {
			const blocks = [
				createBlock( 'core/spacer', {} ),
				createBlock( 'core/separator', {} ),
				createBlock( 'my-plugin/custom-block', { url: 'https://example.com' } ),
			];
			expect( findLinksInBlocks( blocks ) ).toEqual( [] );
		} );
	} );
} );
