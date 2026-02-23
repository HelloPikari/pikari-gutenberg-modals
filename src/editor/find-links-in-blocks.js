/**
 * Recursively find all links in inner blocks.
 *
 * Shared utility used by both the Modal Trigger block and the
 * legacy Group Modal Trigger extension to detect links in child blocks.
 *
 * @param {Array}   blocks                           Array of blocks to search.
 * @param {string}  parentPath                       Path to the parent block (e.g., "0.2").
 * @param {Object}  options                          Optional detection configuration.
 * @param {boolean} options.includeButtonsWithoutUrl Include buttons without URLs (for close mode).
 * @return {Array} Array of detected link objects.
 */

import { __ } from '@wordpress/i18n';

export default function findLinksInBlocks( blocks, parentPath = '', options = {} ) {
	const { includeButtonsWithoutUrl = false } = options;
	const links = [];

	blocks.forEach( ( block, index ) => {
		const currentPath = parentPath ? `${ parentPath }.${ index }` : `${ index }`;

		// Check for links based on block type
		switch ( block.name ) {
			case 'core/button':
				if ( block.attributes.url ) {
					links.push( {
						identifier: {
							blockPath: currentPath,
							blockName: block.name,
							linkUrl: block.attributes.url,
						},
						label: `${ __( 'Button', 'pikari-gutenberg-modals' ) }: ${ block.attributes.text || block.attributes.url }`,
					} );
				} else if ( includeButtonsWithoutUrl ) {
					links.push( {
						identifier: {
							blockPath: currentPath,
							blockName: block.name,
							clientId: block.clientId,
						},
						label: `${ __( 'Button', 'pikari-gutenberg-modals' ) }: ${ block.attributes.text || __( '(no text)', 'pikari-gutenberg-modals' ) }`,
					} );
				}
				break;

			case 'core/image':
				if ( block.attributes.href ) {
					links.push( {
						identifier: {
							blockPath: currentPath,
							blockName: block.name,
							linkUrl: block.attributes.href,
						},
						label: `${ __( 'Image', 'pikari-gutenberg-modals' ) }: ${ block.attributes.alt || block.attributes.href }`,
					} );
				}
				break;

			case 'core/navigation-link':
				if ( block.attributes.url ) {
					links.push( {
						identifier: {
							blockPath: currentPath,
							blockName: block.name,
							linkUrl: block.attributes.url,
						},
						label: `${ __( 'Nav Link', 'pikari-gutenberg-modals' ) }: ${ block.attributes.label || block.attributes.url }`,
					} );
				}
				break;

			case 'core/heading':
			case 'core/paragraph': {
				// Parse content for <a> tags
				const content = block.attributes.content || '';
				const anchorMatches = content.match( /<a[^>]+href="([^"]+)"[^>]*>([^<]*)<\/a>/g );

				if ( anchorMatches ) {
					anchorMatches.forEach( ( match, linkIndex ) => {
						const hrefMatch = match.match( /href="([^"]+)"/ );
						const textMatch = match.match( />([^<]*)<\/a>/ );

						if ( hrefMatch && hrefMatch[ 1 ] ) {
							const blockTypeLabel = block.name === 'core/heading'
								? __( 'Heading', 'pikari-gutenberg-modals' )
								: __( 'Paragraph', 'pikari-gutenberg-modals' );

							links.push( {
								identifier: {
									blockPath: `${ currentPath }.${ linkIndex }`,
									blockName: block.name,
									linkUrl: hrefMatch[ 1 ],
								},
								label: `${ blockTypeLabel }: ${ textMatch ? textMatch[ 1 ] : hrefMatch[ 1 ] }`,
							} );
						}
					} );
				}
				break;
			}

			// Query Loop post-link blocks (link to current post dynamically)
			case 'core/post-title':
			case 'core/post-featured-image':
			case 'core/post-date':
				if ( block.attributes.isLink ) {
					const postLinkLabels = {
						'core/post-title': __( 'Post Title', 'pikari-gutenberg-modals' ),
						'core/post-featured-image': __( 'Featured Image', 'pikari-gutenberg-modals' ),
						'core/post-date': __( 'Post Date', 'pikari-gutenberg-modals' ),
					};
					links.push( {
						identifier: {
							blockPath: currentPath,
							blockName: block.name,
							linkType: 'post-link',
							linkUrl: null,
						},
						label: `${ postLinkLabels[ block.name ] }: ${ __( 'Current Post', 'pikari-gutenberg-modals' ) }`,
					} );
				}
				break;

			case 'core/read-more':
				// Read More block always links to current post
				links.push( {
					identifier: {
						blockPath: currentPath,
						blockName: block.name,
						linkType: 'post-link',
						linkUrl: null,
					},
					label: `${ __( 'Read More', 'pikari-gutenberg-modals' ) }: ${ block.attributes.content || __( 'Current Post', 'pikari-gutenberg-modals' ) }`,
				} );
				break;

			case 'core/post-excerpt':
				// Only detect as link if moreText has a non-empty value
				if ( block.attributes.moreText && block.attributes.moreText.trim() !== '' ) {
					links.push( {
						identifier: {
							blockPath: currentPath,
							blockName: block.name,
							linkType: 'post-link',
							linkUrl: null,
						},
						label: `${ __( 'Excerpt', 'pikari-gutenberg-modals' ) }: ${ block.attributes.moreText }`,
					} );
				}
				break;
		}

		// Recursively search inner blocks
		if ( block.innerBlocks && block.innerBlocks.length > 0 ) {
			links.push( ...findLinksInBlocks( block.innerBlocks, currentPath, options ) );
		}
	} );

	return links;
}
