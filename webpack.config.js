const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
// eslint-disable-next-line import/no-extraneous-dependencies -- Available via @wordpress/scripts
const DependencyExtractionWebpackPlugin = require( '@wordpress/dependency-extraction-webpack-plugin' );
// eslint-disable-next-line import/no-extraneous-dependencies -- Available via @wordpress/scripts
const CopyWebpackPlugin = require( 'copy-webpack-plugin' );
const path = require( 'path' );

// When using --experimental-modules, defaultConfig is an array: [scriptConfig, moduleConfig]
const scriptConfig = Array.isArray( defaultConfig )
	? defaultConfig[ 0 ]
	: defaultConfig;
const moduleConfig = Array.isArray( defaultConfig ) ? defaultConfig[ 1 ] : null;

// Editor uses standard script output (for block editor compatibility)
const editorConfig = {
	...scriptConfig,
	entry: {
		'editor/index': path.resolve( __dirname, 'src/editor/index.js' ),
		'blocks/close-button/index': path.resolve( __dirname, 'src/blocks/close-button/index.js' ),
		'blocks/content-area/index': path.resolve( __dirname, 'src/blocks/content-area/index.js' ),
		'blocks/modal-content/index': path.resolve( __dirname, 'src/blocks/modal-content/index.js' ),
		'blocks/modal-dialog/index': path.resolve( __dirname, 'src/blocks/modal-dialog/index.js' ),
	},
	output: {
		...scriptConfig.output,
		path: path.resolve( __dirname, 'build' ),
	},
	// Extend plugins to copy block CSS files (style.css, editor.css) to build/.
	// The default @wordpress/scripts CopyPlugin only copies block.json and *.php.
	// Standalone CSS referenced in block.json ("style": "file:./style.css") must
	// be copied separately.
	plugins: [
		...scriptConfig.plugins,
		new CopyWebpackPlugin( {
			patterns: [
				{
					from: 'blocks/**/*.css',
					context: 'src',
					noErrorOnMissing: true,
				},
			],
		} ),
	],
};

// Frontend uses ES module output (required for Interactivity API)
// Note: @wordpress/escape-html must be bundled because it's not available
// as a WordPress Script Module (only @wordpress/interactivity, @wordpress/interactivity-router,
// and @wordpress/a11y are exposed as script modules as of WordPress 6.7)
const frontendConfig = moduleConfig
	? {
		...moduleConfig,
		entry: {
			'frontend/index': path.resolve(
				__dirname,
				'src/frontend/index.js'
			),
		},
		output: {
			...moduleConfig.output,
			path: path.resolve( __dirname, 'build' ),
		},
		// Remove the default DependencyExtractionWebpackPlugin and add our own
		// with custom requestToExternalModule to allow bundling @wordpress/escape-html
		plugins: [
			...moduleConfig.plugins.filter(
				( plugin ) =>
					plugin.constructor.name !== 'DependencyExtractionWebpackPlugin'
			),
			new DependencyExtractionWebpackPlugin( {
				// Disable defaults to prevent the plugin from throwing errors
				// for @wordpress packages that aren't available as script modules
				useDefaults: false,
				// Custom handler to allow @wordpress/escape-html to be bundled
				requestToExternalModule( request ) {
					// These are the only @wordpress packages available as script modules
					if ( request === '@wordpress/interactivity' ) {
						return `module ${ request }`;
					}
					if (
						request === '@wordpress/interactivity-router' ||
						request === '@wordpress/a11y'
					) {
						return `import ${ request }`;
					}
					// Return undefined for everything else (including @wordpress/escape-html)
					// to bundle it into our code
					return undefined;
				},
			} ),
		],
	}
	: null;

// Export both configs (webpack supports array of configs)
module.exports = frontendConfig ? [ editorConfig, frontendConfig ] : editorConfig;
