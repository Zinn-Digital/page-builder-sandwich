/**
 * Bundles: the admin screen, the fixture editor script, the core blocks' editor script, the
 * developer-compat layer (pbs/shortcode + the legacy pbsAddInspector API), Sandwich Studio and its
 * button in the block editor. All are wp-admin only; the
 * front end loads nothing built here (front-end CSS is published from assets/, ADR 0033).
 */
const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

/**
 * A premium entry's derived RTL sheet (`x-pro__premium_only-rtl.css`) carries the premium marker
 * MID-name, so neither the free build's strip rule nor Freemius's removes it, and
 * wp/bin/build-plugin.php refuses such a zip. No premium bundle enqueues an RTL variant (their CSS
 * is logical-property only, so it mirrors by itself), so the derived file is dropped at emit.
 */
class DropPremiumRtl {
	apply( compiler ) {
		compiler.hooks.thisCompilation.tap( 'DropPremiumRtl', ( compilation ) => {
			compilation.hooks.processAssets.tap(
				{
					name: 'DropPremiumRtl',
					stage: compiler.webpack.Compilation.PROCESS_ASSETS_STAGE_REPORT,
				},
				() => {
					for ( const name of Object.keys( compilation.assets ) ) {
						if ( /__premium_only-rtl\.css$/.test( name ) ) {
							compilation.deleteAsset( name );
						}
					}
				}
			);
		} );
	}
}

module.exports = {
	...defaultConfig,
	plugins: [ ...( defaultConfig.plugins || [] ), new DropPremiumRtl() ],
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
		// Lane L12 (P14): workflow panels; Pro/Agency bundles live in premium-only paths.
		workflow: path.resolve( __dirname, 'src/workflow/index.js' ),
		'workflow-pro__premium_only': path.resolve(
			__dirname,
			'src/workflow/pro__premium_only/admin.js'
		),
		'review-panel-pro__premium_only': path.resolve(
			__dirname,
			'src/workflow/pro__premium_only/review-panel.js'
		),
		'review-layer-pro__premium_only': path.resolve(
			__dirname,
			'src/workflow/pro__premium_only/review-layer.js'
		),
		'network-pro__premium_only': path.resolve(
			__dirname,
			'src/workflow/pro__premium_only/network.js'
		),
	},
};
