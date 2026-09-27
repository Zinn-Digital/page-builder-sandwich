/**
 * Bundles: the admin screen, the fixture editor script, the core blocks' editor script, the
 * developer-compat layer (pbs/shortcode + the legacy pbsAddInspector API), Sandwich Studio and its
 * button in the block editor. All are wp-admin only; the
 * front end loads nothing built here (front-end CSS is published from assets/, ADR 0033).
 */
const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	entry: {
		settings: path.resolve( __dirname, 'src/admin/index.js' ),
		editor: path.resolve( __dirname, 'src/editor/index.js' ),
		core: path.resolve( __dirname, 'src/core/index.js' ),
		'dev-compat': path.resolve( __dirname, 'src/core/dev-compat/index.js' ),
		studio: path.resolve( __dirname, 'src/studio/index.js' ),
		'studio-button': path.resolve(
			__dirname,
			'src/studio/editor-button/index.js'
		),
	},
};
