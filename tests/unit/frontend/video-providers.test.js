/**
 * Tests for isVideoEmbedUrl utility.
 *
 * @see src/frontend/video-providers.js
 */

import {
	isVideoEmbedUrl,
	normalizeEmbedUrl,
	resolveVideoRatio,
} from '../../../src/frontend/video-providers';

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

describe( 'normalizeEmbedUrl', () => {
	describe( 'YouTube', () => {
		it.each( [
			[ 'https://www.youtube.com/watch?v=dQw4w9WgXcQ' ],
			[ 'https://youtube.com/watch?v=dQw4w9WgXcQ' ],
			[ 'https://m.youtube.com/watch?v=dQw4w9WgXcQ' ],
			[ 'https://youtu.be/dQw4w9WgXcQ' ],
			[ 'https://www.youtube.com/shorts/dQw4w9WgXcQ' ],
			[ 'https://www.youtube.com/live/dQw4w9WgXcQ' ],
		] )( 'converts %s to an embed URL', ( url ) => {
			expect( normalizeEmbedUrl( url ) ).toBe(
				'https://www.youtube.com/embed/dQw4w9WgXcQ'
			);
		} );

		it( 'leaves an embed URL untouched', () => {
			const url = 'https://www.youtube.com/embed/dQw4w9WgXcQ';
			expect( normalizeEmbedUrl( url ) ).toBe( url );
		} );

		it( 'carries a start time across from watch URLs', () => {
			expect(
				normalizeEmbedUrl( 'https://www.youtube.com/watch?v=abc12345678&t=90s' )
			).toBe( 'https://www.youtube.com/embed/abc12345678?start=90' );
		} );

		it( 'carries a start time across from short links', () => {
			expect( normalizeEmbedUrl( 'https://youtu.be/abc12345678?t=42' ) ).toBe(
				'https://www.youtube.com/embed/abc12345678?start=42'
			);
		} );

		it( 'ignores a watch URL with no video id', () => {
			const url = 'https://www.youtube.com/watch?list=PL123';
			expect( normalizeEmbedUrl( url ) ).toBe( url );
		} );
	} );

	describe( 'Vimeo', () => {
		it( 'converts a plain Vimeo URL to a player URL', () => {
			expect( normalizeEmbedUrl( 'https://vimeo.com/76979871' ) ).toBe(
				'https://player.vimeo.com/video/76979871'
			);
		} );

		it( 'carries the privacy hash of an unlisted video', () => {
			expect( normalizeEmbedUrl( 'https://vimeo.com/76979871/abc123def' ) ).toBe(
				'https://player.vimeo.com/video/76979871?h=abc123def'
			);
		} );

		it( 'leaves a player URL untouched', () => {
			const url = 'https://player.vimeo.com/video/76979871';
			expect( normalizeEmbedUrl( url ) ).toBe( url );
		} );
	} );

	describe( 'everything else', () => {
		it.each( [
			'https://example.com/page',
			'https://example.com/watch?v=abc',
			'',
			'not a url',
		] )( 'leaves %s unchanged', ( url ) => {
			expect( normalizeEmbedUrl( url ) ).toBe( url );
		} );
	} );
} );

describe( 'resolveVideoRatio', () => {
	it.each( [
		[ '16-9', '16 / 9' ],
		[ '9-16', '9 / 16' ],
		[ '4-3', '4 / 3' ],
		[ '1-1', '1 / 1' ],
	] )( 'maps the %s slug to %s', ( slug, expected ) => {
		expect( resolveVideoRatio( slug, 'https://example.com/embed' ) ).toBe(
			expected
		);
	} );

	it( 'honours an explicit ratio on a host it does not know', () => {
		expect(
			resolveVideoRatio( '9-16', 'https://videos.example.com/x' )
		).toBe( '9 / 16' );
	} );

	it( 'falls back to 16:9 for a known video host', () => {
		expect(
			resolveVideoRatio( '', 'https://www.youtube.com/embed/abc' )
		).toBe( '16 / 9' );
	} );

	it( 'returns null for an unknown host with no explicit ratio', () => {
		expect( resolveVideoRatio( '', 'https://example.com/page' ) ).toBe(
			null
		);
	} );

	it( 'treats an unrecognised slug as auto', () => {
		expect( resolveVideoRatio( '21-9', 'https://example.com/page' ) ).toBe(
			null
		);
		expect(
			resolveVideoRatio( '21-9', 'https://vimeo.com/1' )
		).toBe( '16 / 9' );
	} );
} );
