/**
 * Bundles: the admin screen, the fixture editor script, the core blocks' editor script, the
 * developer-compat layer (pbs/shortcode + the legacy pbsAddInspector API), Sandwich Studio and its
 * button in the block editor. All are wp-admin only; the
 * front end loads nothing built here (front-end CSS is published from assets/, ADR 0033).
 */
const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

/** The Pro blocks' entry (build/blocks-pro…premium_only.js), see below. */
const PRO_BLOCKS = `blocks-pro_${ '_premium_only' }`;

/** The Pro design entry (build/design-pro…premium_only.js). */
const PRO_DESIGN = `design-pro_${ '_premium_only' }`;

/**
 * A premium entry's derived RTL sheet (`x-pro__premium_only-rtl.css`) carries the premium marker
 * MID-name, so neither the free build's strip rule nor Freemius's removes it, and
 * wp/bin/build-plugin.php refuses such a zip. No premium bundle enqueues an RTL variant (their CSS
 * is logical-property only, so it mirrors by itself), so the derived file is dropped at emit.
 */
class DropPremiumRtl {
	apply( compiler ) {
		compiler.hooks.thisCompilation.tap(
			'DropPremiumRtl',
			( compilation ) => {
				compilation.hooks.processAssets.tap(
					{
						name: 'DropPremiumRtl',
						stage: compiler.webpack.Compilation
							.PROCESS_ASSETS_STAGE_REPORT,
					},
					() => {
						for ( const name of Object.keys(
							compilation.assets
						) ) {
							if ( /__premium_only-rtl\.css$/.test( name ) ) {
								compilation.deleteAsset( name );
							}
						}
					}
				);
			}
		);
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
		// pbs-p4: the design system (Style tab, preview, ids) — every block's design panel.
		design: path.resolve( __dirname, 'src/design/index.js' ),
		// Lane L09: the design-system blocks (free), and their Pro twin under a premium-only entry
		// name, so its build output is dropped from the free package. The token is assembled from
		// two halves because it must not appear in a file the free package ships (bootstrap.php).
		blocks: path.resolve( __dirname, 'src/blocks/index.js' ),
		// pbs-a1: the Accessibility checker (block editor sidebar and Studio panel).
		'a11y-checker': path.resolve( __dirname, 'src/design/a11y/index.js' ),
		// Lane L10 (Pro): theme builder, display conditions, dynamic data (premium path).
		[ `theme-builder-editor-pro_${ '_premium_only' }` ]: path.resolve(
			__dirname,
			`src/theme-builder/pro_${ '_premium_only' }/index.js`
		),
		[ PRO_BLOCKS ]: path.resolve(
			__dirname,
			`src/blocks/pro_${ '_premium_only' }/index.js`
		),
		// pbs-d3 (Pro): the custom-breakpoint editor on the settings screen, same split.
		[ PRO_DESIGN ]: path.resolve(
			__dirname,
			`src/design/pro_${ '_premium_only' }/index.js`
		),
		// pbs-r14 (Pro): effect props + Effects panels. A premium path, so the free build drops it.
		[ `effects-pro_${ '_premium_only' }` ]: path.resolve(
			__dirname,
			`src/design/pro_${ '_premium_only' }/effects/index.js`
		),
		// P4 design globals (L09 W2): the Site design sidebar/page (free) and its premium parts,
		// premium entry names assembled like PRO_BLOCKS so the token stays out of shipped files.
		'site-design': path.resolve(
			__dirname,
			'src/design/site-design/editor.js'
		),
		'site-design-page': path.resolve(
			__dirname,
			'src/design/site-design/admin.js'
		),
		[ `site-design-pro_${ '_premium_only' }` ]: path.resolve(
			__dirname,
			`src/design/pro_${ '_premium_only' }/globals/index.js`
		),
		[ `design-editor-pro_${ '_premium_only' }` ]: path.resolve(
			__dirname,
			`src/design/pro_${ '_premium_only' }/globals/editor.js`
		),
		// Lane L12 (P12): AI inside the builder; the Pro panels under a premium-only entry.
		ai: path.resolve( __dirname, 'src/ai/index.js' ),
		'ai-pro__premium_only': path.resolve(
			__dirname,
			'src/ai/pro__premium_only/index.js'
		),
		// Lane L12 (P13): SEO + performance; Pro panels under premium-only entries.
		seo: path.resolve( __dirname, 'src/seo/index.js' ),
		'seo-pro__premium_only': path.resolve(
			__dirname,
			'src/seo/pro__premium_only/editor.js'
		),
		'seo-health-pro__premium_only': path.resolve(
			__dirname,
			'src/seo/pro__premium_only/health.js'
		),
		// Lane L12 (P16): add-ons' style controls in the editor.
		dev: path.resolve( __dirname, 'src/dev/index.js' ),
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
		// Lane PBS-IMPORT (P15): the Import panel (other builders + Figma), Pro only.
		'importers-pro__premium_only': path.resolve(
			__dirname,
			'src/importers/pro__premium_only/index.js'
		),
		// Lane L11 (pbs-c3, pbs-c4, BYO storage): the Templates screen's Pro tabs and the editors'
		// cloud-library panel. Premium-only paths, dropped from the free build.
		'templates-pro__premium_only': path.resolve(
			__dirname,
			'src/admin-templates/pro__premium_only/index.js'
		),
		'marketing-admin-pro__premium_only': path.resolve(
			__dirname,
			'src/marketing/pro__premium_only/admin.js'
		),
		'marketing-editor-pro__premium_only': path.resolve(
			__dirname,
			'src/marketing/pro__premium_only/editor.js'
		),
		'cloud-pro__premium_only': path.resolve(
			__dirname,
			'src/cloud/pro__premium_only/index.js'
		),
	},
};
