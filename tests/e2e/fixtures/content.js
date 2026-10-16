/**
 * Deterministic content for the end-to-end suite.
 *
 * TARGET is the page every trigger opens. TRIGGERS carries one trigger of
 * each open-mode type, all pointing at TARGET. Its content is built once
 * TARGET exists, because the inline format stores the target's post ID and
 * the Group trigger matches its primary link by absolute URL.
 */

const TARGET = {
	title: 'Modal Target',
	slug: 'modal-target',
	path: '/modal-target/',
	body: 'This paragraph is the body of the modal target page.',
};

const TRIGGERS = {
	title: 'Modal Triggers',
	slug: 'modal-triggers',
	path: '/modal-triggers/',
};

// Each trigger's visible text, which is also its accessible name.
const LABELS = {
	inline: 'inline trigger',
	button: 'Open button trigger',
	group: 'Read the target page',
};

/**
 * Serialize block attributes the way a block comment carries them.
 *
 * @param {Object} attrs Block attributes.
 * @return {string} JSON for the block comment.
 */
const blockAttrs = ( attrs ) => JSON.stringify( attrs );

/**
 * Block markup for the TRIGGERS page.
 *
 * @param {string} targetUrl Absolute permalink of TARGET.
 * @param {number} targetId  Post ID of TARGET.
 * @return {string} Post content.
 */
const triggersContent = ( targetUrl, targetId ) => {
	const linkData = JSON.stringify( { url: targetUrl, id: targetId } )
		.replace( /"/g, '&quot;' );

	return `
<!-- wp:paragraph -->
<p>An <span class="modal-trigger" data-modal-trigger="${ linkData }" data-modal-content-type="page" data-modal-content-id="${ targetId }">${ LABELS.inline }</span> inside a paragraph.</p>
<!-- /wp:paragraph -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button ${ blockAttrs( {
		pikariModalAction: 'open',
	} ) } -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="${ targetUrl }">${ LABELS.button }</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons -->

<!-- wp:group ${ blockAttrs( {
		pikariModalAction: 'open',
		pikariModalPrimaryLinkId: JSON.stringify( { linkUrl: targetUrl } ),
		layout: { type: 'constrained' },
	} ) } -->
<div class="wp-block-group"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">A clickable card</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><a href="${ targetUrl }">${ LABELS.group }</a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->
`;
};

module.exports = {
	LABELS,
	TARGET,
	TRIGGERS,
	triggersContent,
};
