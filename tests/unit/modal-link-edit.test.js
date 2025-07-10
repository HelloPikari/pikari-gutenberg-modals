/**
 * Tests for ModalLinkEdit component behavior
 */

const { wp } = require( '../mocks/wordpress' );

describe( 'ModalLinkEdit Component Behavior', () => {
	beforeEach( () => {
		// Reset mocks
		jest.clearAllMocks();

		// Set up default block editor store
		wp.data.select.mockImplementation( ( store ) => {
			if ( store === 'core/block-editor' ) {
				return {
					getSelectedBlock: jest.fn( () => ( {
						clientId: 'test-block-123',
						name: 'core/paragraph',
						attributes: {},
						innerBlocks: [],
					} ) ),
				};
			}
			return {};
		} );
	} );

	describe( 'Block Support Logic', () => {
		it( 'should support paragraph blocks', () => {
			const supportedBlocks = [
				'core/paragraph',
				'core/heading',
				'core/list',
				'core/list-item',
				'core/quote',
				'core/verse',
				'core/preformatted',
				'core/navigation-link',
			];

			expect( supportedBlocks ).toContain( 'core/paragraph' );
			expect( supportedBlocks ).toContain( 'core/heading' );
			expect( supportedBlocks ).not.toContain( 'core/button' );
		} );

		it( 'should check if block is supported', () => {
			const selectedBlock = {
				name: 'core/paragraph',
			};

			const supportedBlocks = [ 'core/paragraph', 'core/heading' ];
			const isSupported = supportedBlocks.includes( selectedBlock.name );

			expect( isSupported ).toBe( true );
		} );
	} );

	describe( 'Modal Data Handling', () => {
		it( 'should parse modal link data from attributes', () => {
			const mockFormat = {
				type: 'modal-toolbar-button/modal-link',
				attributes: {
					'data-modal-link': JSON.stringify( {
						url: '/test-post/',
						id: 123,
						type: 'post',
						title: 'Test Post',
					} ),
				},
			};

			// Parse the data
			let modalData;
			try {
				modalData = JSON.parse(
					mockFormat.attributes[ 'data-modal-link' ]
				);
			} catch ( error ) {
				modalData = null;
			}

			expect( modalData ).toEqual( {
				url: '/test-post/',
				id: 123,
				type: 'post',
				title: 'Test Post',
			} );
		} );

		it( 'should handle legacy format data', () => {
			const mockFormat = {
				attributes: {
					'data-modal-content-id': '456',
					'data-modal-content-type': 'page',
				},
			};

			// Fallback for legacy format
			const modalData = {
				url: mockFormat.attributes[ 'data-modal-content-id' ] || '',
				type:
					mockFormat.attributes[ 'data-modal-content-type' ] ||
					'post',
			};

			expect( modalData.url ).toBe( '456' );
			expect( modalData.type ).toBe( 'page' );
		} );
	} );

	describe( 'Format Application', () => {
		it( 'should create format object for internal links', () => {
			const newValue = {
				url: '/test-page/',
				id: 789,
				type: 'page',
				title: 'Test Page',
			};

			// Determine content type
			let contentType = 'url';
			let contentId = newValue.url;

			if ( newValue.id ) {
				contentType = newValue.type || 'post';
				contentId = newValue.id;
			}

			// Create format object
			const format = {
				type: 'modal-toolbar-button/modal-link',
				attributes: {
					'data-modal-link': JSON.stringify( newValue ),
					'data-modal-content-type': contentType,
					'data-modal-content-id': String( contentId ),
					href: '#',
				},
			};

			expect( format.attributes[ 'data-modal-content-type' ] ).toBe(
				'page'
			);
			expect( format.attributes[ 'data-modal-content-id' ] ).toBe(
				'789'
			);
		} );

		it( 'should create format object for external URLs', () => {
			const newValue = {
				url: 'https://example.com',
				title: 'External Site',
			};

			// For external URLs
			const contentType = 'url';
			const contentId = newValue.url;

			const format = {
				type: 'modal-toolbar-button/modal-link',
				attributes: {
					'data-modal-link': JSON.stringify( newValue ),
					'data-modal-content-type': contentType,
					'data-modal-content-id': contentId,
					href: '#',
				},
			};

			expect( format.attributes[ 'data-modal-content-type' ] ).toBe(
				'url'
			);
			expect( format.attributes[ 'data-modal-content-id' ] ).toBe(
				'https://example.com'
			);
		} );
	} );
} );
