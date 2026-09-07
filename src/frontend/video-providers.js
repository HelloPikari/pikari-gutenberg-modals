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

	return VIDEO_PROVIDERS.some(
		( provider ) => host === provider || host.endsWith( `.${ provider }` )
	);
}
