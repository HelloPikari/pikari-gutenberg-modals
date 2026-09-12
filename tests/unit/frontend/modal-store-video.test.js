/**
 * The store sizes a URL modal around the media it frames.
 *
 * @see src/frontend/video-providers.js for the resolution rules themselves.
 */

import { store, getContext } from '@wordpress/interactivity';

import '../../../src/frontend/modal-store';

/**
 * Build a modal container on the page.
 *
 * @return {HTMLElement} The container element.
 */
function setUpContainer() {
	document.body.innerHTML = `
		<div id="pikari-modal" class="modal-overlay">
			<div class="modal-content">
				<div class="modal-body"></div>
			</div>
		</div>
	`;
	return document.getElementById( 'pikari-modal' );
}

/**
 * Run the openModal generator to completion.
 *
 * @param {Object} actions Store actions.
 */
function runOpen( actions ) {
	const generator = actions.openModal();
	let step = generator.next();
	while ( ! step.done ) {
		step = generator.next();
	}
}

describe( 'modal store video sizing', () => {
	let actions;

	beforeAll( () => {
		( { actions } = store.getStore( 'pikari-modal' ) );
	} );

	beforeEach( () => {
		jest.clearAllMocks();
	} );

	afterEach( () => {
		jest.useFakeTimers();
		actions.closeModal();
		jest.advanceTimersByTime( 200 );
		jest.useRealTimers();
	} );

	describe( 'fit mode', () => {
		it( 'fits the dialog to a known video host', () => {
			const container = setUpContainer();
			getContext.mockReturnValue( {
				contentSource: 'url',
				postId: 'https://www.youtube.com/embed/dQw4w9WgXcQ',
				modalId: 'url-video',
			} );

			runOpen( actions );

			expect( container.getAttribute( 'data-fit' ) ).toBe( 'video' );
			expect(
				container.style.getPropertyValue( '--modal-video-ratio' ).trim()
			).toBe( '16 / 9' );
		} );

		it( 'honours an explicit portrait ratio', () => {
			const container = setUpContainer();
			getContext.mockReturnValue( {
				contentSource: 'url',
				postId: 'https://www.youtube.com/embed/dQw4w9WgXcQ',
				modalId: 'url-video',
				aspectRatio: '9-16',
			} );

			runOpen( actions );

			expect(
				container.style.getPropertyValue( '--modal-video-ratio' ).trim()
			).toBe( '9 / 16' );
		} );

		it( 'applies an explicit ratio to a host it does not know', () => {
			const container = setUpContainer();
			getContext.mockReturnValue( {
				contentSource: 'url',
				postId: 'https://videos.example.com/abc',
				modalId: 'url-other',
				aspectRatio: '4-3',
			} );

			runOpen( actions );

			expect( container.getAttribute( 'data-fit' ) ).toBe( 'video' );
		} );

		it( 'leaves an ordinary page iframe filling the dialog', () => {
			const container = setUpContainer();
			getContext.mockReturnValue( {
				contentSource: 'url',
				postId: 'https://example.com/page',
				modalId: 'url-page',
			} );

			runOpen( actions );

			expect( container.hasAttribute( 'data-fit' ) ).toBe( false );
		} );

		it( 'leaves a side panel to its own geometry', () => {
			const container = setUpContainer();
			getContext.mockReturnValue( {
				contentSource: 'url',
				postId: 'https://www.youtube.com/embed/dQw4w9WgXcQ',
				modalId: 'url-video',
				placement: 'right',
			} );

			runOpen( actions );

			expect( container.hasAttribute( 'data-fit' ) ).toBe( false );
		} );

		it( 'clears fit mode when the modal closes', () => {
			const container = setUpContainer();
			getContext.mockReturnValue( {
				contentSource: 'url',
				postId: 'https://www.youtube.com/embed/dQw4w9WgXcQ',
				modalId: 'url-video',
			} );

			runOpen( actions );

			jest.useFakeTimers();
			actions.closeModal();
			jest.advanceTimersByTime( 200 );
			jest.useRealTimers();

			expect( container.hasAttribute( 'data-fit' ) ).toBe( false );
			expect(
				container.style.getPropertyValue( '--modal-video-ratio' )
			).toBe( '' );
		} );
	} );

	describe( 'embed URLs', () => {
		it( 'frames the embed URL for a pasted watch link', () => {
			const container = setUpContainer();
			getContext.mockReturnValue( {
				contentSource: 'url',
				postId: 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
				modalId: 'url-video',
			} );

			runOpen( actions );

			expect( container.querySelector( 'iframe' ).getAttribute( 'src' ) ).toBe(
				'https://www.youtube.com/embed/dQw4w9WgXcQ'
			);
		} );

		it( 'leaves a non-video URL as the author wrote it', () => {
			const container = setUpContainer();
			getContext.mockReturnValue( {
				contentSource: 'url',
				postId: 'https://example.com/page',
				modalId: 'url-page',
			} );

			runOpen( actions );

			expect( container.querySelector( 'iframe' ).getAttribute( 'src' ) ).toBe(
				'https://example.com/page'
			);
		} );
	} );
} );
