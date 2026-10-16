/**
 * Global setup: rebuild the fixture content on the wp-env tests instance.
 *
 * Runs after the `@wordpress/scripts` global setup has saved the admin storage
 * state. Everything it creates is deleted and recreated on every run, so the
 * suite does not depend on the monorepo's `npm run seed`.
 *
 * Template parts are left alone: the default modal is the plugin's own
 * file-based `parts/modal.html`.
 */
const fs = require( 'fs' );
const path = require( 'path' );
const { request } = require( '@playwright/test' );
const { RequestUtils } = require( '@wordpress/e2e-test-utils-playwright' );
const { TARGET, TRIGGERS, triggersContent } = require( '../fixtures/content' );

module.exports = async function fixtures( config ) {
	const buildDir = path.resolve( __dirname, '../../../build/blocks' );
	if ( ! fs.existsSync( buildDir ) ) {
		throw new Error(
			'build/blocks is missing. Run `npm run build` before `npm run test:e2e`.'
		);
	}

	const { storageState, baseURL } = config.projects[ 0 ].use;
	const requestContext = await request.newContext( { baseURL } );
	const requestUtils = new RequestUtils( requestContext, {
		storageStatePath: storageState,
	} );
	await requestUtils.setupRest();

	await requestUtils.deleteAllPosts();
	await requestUtils.deleteAllPages();

	const target = await requestUtils.rest( {
		method: 'POST',
		path: '/wp/v2/pages',
		data: {
			title: TARGET.title,
			slug: TARGET.slug,
			status: 'publish',
			content: `<!-- wp:paragraph -->\n<p>${ TARGET.body }</p>\n<!-- /wp:paragraph -->`,
		},
	} );

	await requestUtils.rest( {
		method: 'POST',
		path: '/wp/v2/pages',
		data: {
			title: TRIGGERS.title,
			slug: TRIGGERS.slug,
			status: 'publish',
			content: triggersContent( target.link, target.id ),
		},
	} );

	await requestContext.dispose();
};
