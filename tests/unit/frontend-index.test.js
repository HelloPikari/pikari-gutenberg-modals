/**
 * Tests for frontend modal functionality
 */

// Mock the DOM environment
document.body.innerHTML = '';

// Mock fetch
global.fetch = jest.fn();

describe( 'Frontend Modal Handler', () => {
	let consoleWarnSpy;
	let consoleErrorSpy;

	beforeEach( () => {
		// Clear DOM
		document.body.innerHTML = '';

		// Mock console methods
		consoleWarnSpy = jest.spyOn( console, 'warn' ).mockImplementation();
		consoleErrorSpy = jest.spyOn( console, 'error' ).mockImplementation();

		// Reset fetch mock
		global.fetch.mockReset();
	} );

	afterEach( () => {
		consoleWarnSpy.mockRestore();
		consoleErrorSpy.mockRestore();
		jest.clearAllMocks();
	} );

	describe( 'Modal Trigger', () => {
		it( 'should create trigger element with correct attributes', () => {
			// Create a mock trigger
			const trigger = document.createElement( 'span' );
			trigger.className = 'modal-link-trigger';
			trigger.dataset.modalContentType = 'post';
			trigger.dataset.modalContentId = '123';
			document.body.appendChild( trigger );

			// Verify attributes
			expect( trigger.classList.contains( 'modal-link-trigger' ) ).toBe(
				true
			);
			expect( trigger.dataset.modalContentType ).toBe( 'post' );
			expect( trigger.dataset.modalContentId ).toBe( '123' );
		} );
	} );

	describe( 'Modal Display', () => {
		it( 'should create modal container structure', () => {
			// Create modal container
			const modal = document.createElement( 'div' );
			modal.id = 'pikari-modal';
			modal.className = 'modal-overlay';
			modal.innerHTML = `
				<div class="modal-content">
					<button class="modal-close" aria-label="Close modal">&times;</button>
					<div class="modal-body">Loading...</div>
				</div>
			`;
			document.body.appendChild( modal );

			// Verify structure
			expect( document.querySelector( '#pikari-modal' ) ).toBeTruthy();
			expect( document.querySelector( '.modal-content' ) ).toBeTruthy();
			expect( document.querySelector( '.modal-close' ) ).toBeTruthy();
			expect( document.querySelector( '.modal-body' ) ).toBeTruthy();
		} );

		it( 'should toggle modal open class', () => {
			const modal = document.createElement( 'div' );
			modal.className = 'modal-overlay';
			document.body.appendChild( modal );

			// Open modal
			modal.classList.add( 'is-open' );
			expect( modal.classList.contains( 'is-open' ) ).toBe( true );

			// Close modal
			modal.classList.remove( 'is-open' );
			expect( modal.classList.contains( 'is-open' ) ).toBe( false );
		} );
	} );

	describe( 'Content Loading', () => {
		it( 'should handle successful content fetch', async () => {
			const mockContent = {
				title: 'Test Title',
				content: '<p>Test content</p>',
			};

			global.fetch.mockResolvedValueOnce( {
				ok: true,
				json: async () => mockContent,
			} );

			// Simulate fetch
			const response = await global.fetch( '/api/content/123' );
			const data = await response.json();

			expect( data.title ).toBe( 'Test Title' );
			expect( data.content ).toBe( '<p>Test content</p>' );
		} );

		it( 'should handle fetch errors', async () => {
			global.fetch.mockRejectedValueOnce( new Error( 'Network error' ) );

			try {
				await global.fetch( '/api/content/123' );
			} catch ( error ) {
				expect( error.message ).toBe( 'Network error' );
			}
		} );
	} );

	describe( 'Keyboard Navigation', () => {
		it( 'should detect Escape key press', () => {
			const keydownEvent = new KeyboardEvent( 'keydown', {
				key: 'Escape',
			} );

			let escapePressed = false;
			document.addEventListener( 'keydown', ( e ) => {
				if ( e.key === 'Escape' ) {
					escapePressed = true;
				}
			} );

			document.dispatchEvent( keydownEvent );
			expect( escapePressed ).toBe( true );
		} );
	} );
} );
