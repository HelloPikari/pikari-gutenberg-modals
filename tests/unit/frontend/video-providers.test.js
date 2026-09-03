/**
 * Tests for isVideoEmbedUrl utility.
 *
 * @see src/frontend/video-providers.js
 */

import { isVideoEmbedUrl } from '../../../src/frontend/video-providers';

describe( 'isVideoEmbedUrl', () => {
	describe( 'recognised providers', () => {
		it.each( [
			'https://www.youtube.com/embed/dQw4w9WgXcQ',
			'https://youtube.com/watch?v=dQw4w9WgXcQ',
			'https://m.youtube.com/watch?v=dQw4w9WgXcQ',
			'https://youtu.be/dQw4w9WgXcQ',
			'https://vimeo.com/76979871',
			'https://player.vimeo.com/video/76979871',
		] )( 'recognises %s', ( url ) => {
			expect( isVideoEmbedUrl( url ) ).toBe( true );
		} );

		it( 'ignores case in the host', () => {
			expect( isVideoEmbedUrl( 'https://WWW.YOUTUBE.COM/embed/x' ) ).toBe(
				true
			);
		} );

		it( 'recognises http as well as https', () => {
			expect( isVideoEmbedUrl( 'http://vimeo.com/76979871' ) ).toBe(
				true
			);
		} );
	} );

	describe( 'other hosts', () => {
		it.each( [
			'https://example.com/page',
			'https://example.com/watch?v=abc',
			'https://wistia.com/medias/abc',
		] )( 'does not recognise %s', ( url ) => {
			expect( isVideoEmbedUrl( url ) ).toBe( false );
		} );
	} );

	describe( 'hosts that merely resemble a provider', () => {
		it( 'does not match a provider name used as a suffix of another domain', () => {
			expect( isVideoEmbedUrl( 'https://notyoutube.com/watch' ) ).toBe(
				false
			);
		} );

		it( 'does not match a provider name appearing in the path', () => {
			expect(
				isVideoEmbedUrl( 'https://example.com/youtube.com/embed/x' )
			).toBe( false );
		} );

		it( 'does not match a provider name appearing in the query string', () => {
			expect(
				isVideoEmbedUrl( 'https://example.com/?next=youtu.be/x' )
			).toBe( false );
		} );

		it( 'does not match a provider name used as a userinfo prefix', () => {
			expect(
				isVideoEmbedUrl( 'https://youtube.com@evil.example/x' )
			).toBe( false );
		} );
	} );

	describe( 'unusable input', () => {
		it.each( [ '', 'not a url', '//youtube.com/embed/x' ] )(
			'returns false for %p',
			( url ) => {
				expect( isVideoEmbedUrl( url ) ).toBe( false );
			}
		);

		it( 'returns false for a missing argument', () => {
			expect( isVideoEmbedUrl() ).toBe( false );
		} );
	} );
} );
