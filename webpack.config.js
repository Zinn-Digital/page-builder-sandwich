/**
 * Bundles: the admin screen, the editor script, the core blocks' editor script, the
 * developer-compat layer (pbsw/shortcode + the legacy pbswAddInspector API), Sandwich Studio and its
 * button in the block editor. All are wp-admin only; the
 * front end loads nothing built here (front-end CSS is published from assets/, ADR 0033).
 *
 * `npm ci && npm run build` rebuilds every bundle in build/ from src/.
 */
const fs = require( 'fs' );
const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

const config = {
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
		// pbs-p4: the design system (Style tab, preview, ids) — every block's design panel.
		design: path.resolve( __dirname, 'src/design/index.js' ),
		// Lane L09: the design-system blocks.
		blocks: path.resolve( __dirname, 'src/blocks/index.js' ),
		// pbs-a1: the Accessibility checker (block editor sidebar and Studio panel).
		'a11y-checker': path.resolve( __dirname, 'src/design/a11y/index.js' ),
		// P4 design globals (L09 W2): the Site design sidebar and page.
		'site-design': path.resolve(
			__dirname,
			'src/design/site-design/editor.js'
		),
		'site-design-page': path.resolve(
			__dirname,
			'src/design/site-design/admin.js'
		),
		// Lane L12 (P12): AI inside the builder.
		ai: path.resolve( __dirname, 'src/ai/index.js' ),
		// Lane L12 (P13): SEO + performance.
		seo: path.resolve( __dirname, 'src/seo/index.js' ),
		// Lane L12 (P16): add-ons' style controls in the editor.
		dev: path.resolve( __dirname, 'src/dev/index.js' ),
		// Lane L12 (P14): workflow panels.
		workflow: path.resolve( __dirname, 'src/workflow/index.js' ),
	},
};

/*
 * An add-on package may place entry files beside this one, named webpack.entries.<name>.js, each
 * exporting { entry, plugins }. They are merged here when present.
 */
for ( const name of fs.readdirSync( __dirname ).sort() ) {
	if ( /^webpack\.entries\..+\.js$/.test( name ) ) {
		const extra = require( path.join( __dirname, name ) );
		Object.assign( config.entry, extra.entry || {} );
		config.plugins = [
			...( config.plugins || [] ),
			...( extra.plugins || [] ),
		];
	}
}

module.exports = config;
