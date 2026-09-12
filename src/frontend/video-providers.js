/**
 * Video provider detection for external URL modals.
 *
 * External URLs load in an iframe that fills the dialog, which is right for a
 * page but letterboxes a 16:9 video. Known video hosts get an aspect-ratio
 * treatment instead.
 */

/**
 * Hosts whose embeds are 16:9. Matched exactly or as a parent domain, so
 * m.youtube.com and player.vimeo.com are covered without listing them.
 *
 * @type {string[]}
 */
const VIDEO_PROVIDERS = [ 'youtube.com', 'youtu.be', 'vimeo.com' ];

/**
 * Aspect ratio slugs an author can choose, mapped to CSS ratio values.
 *
 * The value is substituted into both `aspect-ratio` and a `calc()` that
 * derives width from height, so it must stay a bare `<number> / <number>`:
 * `calc(75vh * 16 / 9)` is valid CSS, `calc(75vh * 1.78)` loses the
 * correspondence with the `aspect-ratio` declaration beside it.
 *
 * @type {Object<string, string>}
 */
const ASPECT_RATIOS = {
	'16-9': '16 / 9',
	'9-16': '9 / 16',
	'4-3': '4 / 3',
	'1-1': '1 / 1',
};

/**
 * Whether a hostname is, or sits under, a given provider domain.
 *
 * @param {string} host     Lowercased hostname.
 * @param {string} provider Provider domain.
 * @return {boolean} True when the host belongs to the provider.
 */
function matchesHost( host, provider ) {
	return host === provider || host.endsWith( `.${ provider }` );
}

/**
 * Whether a URL points at a known video provider.
 *
 * Matches on host only. A provider name appearing in the path, the query
 * string, or as userinfo is not a match.
 *
 * @param {string} url The URL to test.
 * @return {boolean} True when the URL is a known video embed.
 */
export function isVideoEmbedUrl( url ) {
	if ( ! url ) {
		return false;
	}

	let host;
	try {
		host = new URL( url ).hostname.toLowerCase();
	} catch {
		return false;
	}

	return VIDEO_PROVIDERS.some( ( provider ) =>
		matchesHost( host, provider )
	);
}

/**
 * Seconds expressed by a YouTube `t` parameter.
 *
 * Share links use bare seconds (`t=90`), a trailing unit (`t=90s`), or a
 * compound form (`t=1m30s`). Anything else is treated as absent rather than
 * guessed at.
 *
 * @param {string|null} value The raw parameter value.
 * @return {number|null} Seconds, or null when there is no usable value.
 */
function parseStartSeconds( value ) {
	if ( ! value ) {
		return null;
	}

	if ( /^\d+s?$/.test( value ) ) {
		return parseInt( value, 10 );
	}

	const parts = value.match( /^(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s)?$/ );
	if ( ! parts || ! ( parts[ 1 ] || parts[ 2 ] || parts[ 3 ] ) ) {
		return null;
	}

	const hours = Number( parts[ 1 ] || 0 );
	const minutes = Number( parts[ 2 ] || 0 );
	const seconds = Number( parts[ 3 ] || 0 );

	return ( hours * 3600 ) + ( minutes * 60 ) + seconds;
}

/**
 * The YouTube video id a URL refers to, in any of its published shapes.
 *
 * @param {URL} parsed The parsed URL.
 * @return {string|null} The video id, or null when the URL names no single video.
 */
function youtubeVideoId( parsed ) {
	const host = parsed.hostname.toLowerCase();

	// youtu.be/<id> — the whole path is the id.
	if ( matchesHost( host, 'youtu.be' ) ) {
		return parsed.pathname.slice( 1 ).split( '/' )[ 0 ] || null;
	}

	const segments = parsed.pathname.split( '/' ).filter( Boolean );

	// /embed/<id>, /shorts/<id>, /live/<id>
	if (
		segments.length >= 2 &&
		[ 'embed', 'shorts', 'live' ].includes( segments[ 0 ] )
	) {
		return segments[ 1 ];
	}

	// /watch?v=<id> — a playlist URL with no v param names no single video.
	return parsed.searchParams.get( 'v' );
}

/**
 * Turn a URL an author is likely to paste into one a browser can frame.
 *
 * A YouTube or Vimeo page URL refuses to be framed, so pasting the link from
 * the address bar produces a blank modal. The watch URL is still the right
 * destination for the trigger's own href — this converts only the iframe
 * source, so the no-JavaScript fallback keeps going to the human-facing page.
 *
 * Unrecognised URLs, and URLs that are already embeddable, come back unchanged.
 *
 * @param {string} url The URL to normalize.
 * @return {string} An embeddable URL, or the original.
 */
export function normalizeEmbedUrl( url ) {
	if ( ! url ) {
		return url;
	}

	let parsed;
	try {
		parsed = new URL( url );
	} catch {
		return url;
	}

	const host = parsed.hostname.toLowerCase();

	if (
		matchesHost( host, 'youtube.com' ) ||
		matchesHost( host, 'youtu.be' )
	) {
		// Already an embed URL: leave the author's own parameters alone.
		if ( parsed.pathname.startsWith( '/embed/' ) ) {
			return url;
		}

		const videoId = youtubeVideoId( parsed );
		if ( ! videoId ) {
			return url;
		}

		const start = parseStartSeconds( parsed.searchParams.get( 't' ) );
		const query = start ? `?start=${ start }` : '';

		return `https://www.youtube.com/embed/${ videoId }${ query }`;
	}

	if ( matchesHost( host, 'vimeo.com' ) ) {
		// player.vimeo.com/video/<id> is already embeddable.
		if ( parsed.pathname.startsWith( '/video/' ) ) {
			return url;
		}

		const segments = parsed.pathname.split( '/' ).filter( Boolean );
		const videoId = segments[ 0 ];

		if ( ! videoId || ! /^\d+$/.test( videoId ) ) {
			return url;
		}

		// An unlisted video carries a privacy hash as its second segment,
		// which the player URL expects as ?h=.
		const hash = segments[ 1 ] ? `?h=${ segments[ 1 ] }` : '';

		return `https://player.vimeo.com/video/${ videoId }${ hash }`;
	}

	return url;
}

/**
 * The aspect ratio a URL modal should hold, as a CSS ratio.
 *
 * An author's explicit choice wins, and applies to any host — there is no
 * reliable way to detect orientation otherwise. A YouTube Shorts embed URL is
 * byte-identical to a landscape one, and YouTube's own oEmbed reports 200x113
 * for both, so "auto" can only mean 16:9.
 *
 * @param {string} slug The stored aspect ratio slug ('' for auto).
 * @param {string} url  The URL the modal will frame.
 * @return {string|null} A CSS ratio, or null when the iframe should fill the dialog.
 */
export function resolveVideoRatio( slug, url ) {
	// hasOwn, not a truthiness test: a bare property read reaches the
	// prototype, so 'constructor' and 'toString' would both come back truthy
	// and be written into --modal-video-ratio as stringified functions.
	// TriggerContext::build() already gates the slug server-side; this keeps
	// the module correct on its own terms.
	if ( Object.hasOwn( ASPECT_RATIOS, slug ) ) {
		return ASPECT_RATIOS[ slug ];
	}

	return isVideoEmbedUrl( url ) ? ASPECT_RATIOS[ '16-9' ] : null;
}
