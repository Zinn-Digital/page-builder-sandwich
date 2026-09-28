<?php
/**
 * The block registry (lane L09, P5/P6): ONE list of the design-system blocks, read by the
 * registration below, by the per-block stylesheet map (Assets\Perf\Block_Assets::map()), by the
 * per-block test bar (PHPUnit, vitest, the Docker harness) and by the scaffolder.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Blocks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The free design-system blocks.
 *
 * ⛔ One layout for every block (docs contract L09 §3), so a block is a LINE here plus files at
 * fixed paths — never a hand-written registration:
 *
 *     blocks/<slug>/block.json                  metadata (apiVersion 3, category pbs-*)
 *     src/blocks/<slug>/{index,edit,save,deprecated}.js   the editor half (webpack entry `blocks`)
 *     assets/blocks/<slug>.css                  front-end styles, `__PREFIX__`, logical CSS only
 *     assets/modules/<slug>.js                  optional Interactivity API view module
 *     includes/blocks/class-<group>.php         `render_<slug_with_underscores>()` on class <Group>
 *     wp/tests/fixtures/pbs-blocks/<slug>.html  the canonical instance every test leg renders
 *
 * `wp/bin/pbs-new-block.php <slug> --group=<group>` writes all of them and the line below; the
 * test bar reads THIS list, so a block missing any leg fails the suite (it cannot be forgotten).
 * The Pro twin is \ZinnDigital\PBS\Pro\Blocks\Registry, booted from Pro::boot().
 */
final class Registry {

	/** Editor script handle for the free blocks — wp-admin only, so it may carry the plugin's name. */
	public const EDITOR_HANDLE = 'pbsw-blocks-editor';

	/** The six block categories: slug suffix → nothing (titles are translated in categories()). */
	public const GROUPS = array( 'design', 'content', 'media', 'marketing', 'site', 'data' );

	/**
	 * The P1 blocks registered by Core\Blocks that have a front-end stylesheet — kept in the same
	 * map so Block_Assets has ONE source (these four were its hardcoded MAP until L09).
	 */
	public const CORE_STYLES = array(
		'pbs/row'    => 'block/row.css',
		'pbs/column' => 'block/column.css',
		'pbs/button' => 'block/button.css',
		'pbs/icon'   => 'block/icon.css',
	);

	/**
	 * The P1 blocks held to the per-block test bar as well (fixture, render test, harness).
	 * row/column are containers whose canonical instance is exercised by the footprint suite.
	 */
	public const CORE_TESTED = array( 'button', 'icon' );

	/**
	 * Free blocks: slug → group (class-<group>.php, category pbs-<group> by default).
	 * ⛔ Keep the markers: wp/bin/pbs-new-block.php inserts above the end marker, and the vitest
	 * leg reads the lines between them.
	 */
	public const BLOCKS = array(
		// pbs-blocks:start.
		'alert'     => 'content',
		'section'   => 'design',
		'container' => 'design',
		// pbs-blocks:end.
	);

	/**
	 * Free blocks with an Interactivity API view module at assets/modules/<slug>.js (registered
	 * with Modules::add() by Block_Assets::register(); the block's render callback enqueues it).
	 * Declared rather than probed, so no request stats ~140 files; the test bar holds this list
	 * and the files to each other in both directions.
	 */
	public const MODULES = array(
		// pbs-modules:start.
		'alert',
		// pbs-modules:end.
	);

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'block_categories_all', array( self::class, 'categories' ), 10, 1 );
		// Priority 9: the editor script handle exists before block.json names it (core blocks: 10).
		add_action( 'init', array( self::class, 'register_blocks' ), 9 );
		add_action( 'enqueue_block_editor_assets', array( self::class, 'editor_data' ) );
		add_action( 'enqueue_block_assets', array( self::class, 'editor_styles' ) );
	}

	/**
	 * The editor renders blocks with the same neutral classes the front end uses, so it needs the
	 * site's prefix. wp-admin only (the handle exists only where the block editor loads it).
	 *
	 * @return void
	 */
	public static function editor_data(): void {
		wp_add_inline_script(
			self::EDITOR_HANDLE,
			'window.pbswBlocks = ' . wp_json_encode( array( 'prefix' => \ZinnDigital\PBS\Settings::prefix() ) ) . ';',
			'before'
		);
	}

	/**
	 * The block stylesheets inside the editor canvas (an iframe, so `enqueue_block_assets` is the
	 * hook that reaches it), so a block looks in the editor as it does on the page. Admin only:
	 * the front end keeps loading each stylesheet only where its block renders (Block_Assets).
	 *
	 * @return void
	 */
	public static function editor_styles(): void {
		if ( ! is_admin() ) {
			return;
		}
		$prefix = \ZinnDigital\PBS\Settings::prefix();
		$css    = '';
		foreach ( \ZinnDigital\PBS\Assets\Perf\Block_Assets::map() as $key ) {
			$css .= (string) \ZinnDigital\PBS\Assets::source( $key, $prefix ) . "\n";
		}
		wp_register_style( 'pbsw-blocks-editor-style', false, array(), PBSW_VERSION );
		wp_enqueue_style( 'pbsw-blocks-editor-style' );
		wp_add_inline_style( 'pbsw-blocks-editor-style', $css );
	}

	/**
	 * The six PBS categories, appended once, with translated titles.
	 *
	 * @param array<int, array<string, mixed>>|mixed $categories Registered categories.
	 * @return array<int, array<string, mixed>>|mixed
	 */
	public static function categories( $categories ) {
		if ( ! is_array( $categories ) ) {
			return $categories;
		}
		$have = array_column( $categories, 'slug' );
		foreach ( self::category_titles() as $slug => $title ) {
			if ( ! in_array( $slug, $have, true ) ) {
				$categories[] = array(
					'slug'  => $slug,
					'title' => $title,
					'icon'  => null,
				);
			}
		}

		return $categories;
	}

	/**
	 * Category slug → translated title.
	 *
	 * @return array<string, string>
	 */
	public static function category_titles(): array {
		return array(
			'pbs-design'    => __( 'Design', 'page-builder-sandwich' ),
			'pbs-content'   => __( 'Content', 'page-builder-sandwich' ),
			'pbs-media'     => __( 'Media', 'page-builder-sandwich' ),
			'pbs-marketing' => __( 'Marketing', 'page-builder-sandwich' ),
			'pbs-site'      => __( 'Site', 'page-builder-sandwich' ),
			'pbs-data'      => __( 'Data', 'page-builder-sandwich' ),
		);
	}

	/**
	 * `init`: the editor script, then every free block.
	 *
	 * @return void
	 */
	public static function register_blocks(): void {
		self::register_editor_script( self::EDITOR_HANDLE, 'blocks' );
		self::register_set( self::BLOCKS, '', __NAMESPACE__ );
	}

	/**
	 * Register a built editor bundle (build/<entry>.js), as Core\Blocks does for `core`.
	 *
	 * @param string $handle Script handle.
	 * @param string $entry  Webpack entry name.
	 * @return void
	 */
	public static function register_editor_script( string $handle, string $entry ): void {
		$asset_file = PBSW_DIR . 'build/' . $entry . '.asset.php';
		if ( ! is_readable( $asset_file ) ) {
			return;
		}
		$asset = require $asset_file;
		wp_register_script(
			$handle,
			PBSW_URL . 'build/' . $entry . '.js',
			(array) ( $asset['dependencies'] ?? array() ),
			(string) ( $asset['version'] ?? PBSW_VERSION ),
			true
		);
		wp_set_script_translations( $handle, 'page-builder-sandwich', PBSW_DIR . 'languages' );
	}

	/**
	 * Register a set of blocks from the fixed layout.
	 *
	 * @param array<string, string> $blocks    Slug → group.
	 * @param string                $segment   '' for free, the Pro registry's SEGMENT (with its slash) for Pro.
	 * @param string                $php_namespace PHP namespace of the group classes.
	 * @return void
	 */
	public static function register_set( array $blocks, string $segment, string $php_namespace ): void {
		foreach ( $blocks as $slug => $group ) {
			$callback = self::callback( $slug, $group, $segment, $php_namespace );
			register_block_type(
				PBSW_DIR . 'blocks/' . $segment . $slug,
				null === $callback ? array() : array( 'render_callback' => $callback )
			);
		}
	}

	/**
	 * The render callback of a block: `<Namespace>\<Group>::render_<slug>()`, its class file
	 * required on demand. Null when the group class or the method does not exist (a static block).
	 *
	 * @param string $slug      Block slug (no namespace).
	 * @param string $group     Group.
	 * @param string $segment   '' or the Pro registry's SEGMENT.
	 * @param string $php_namespace PHP namespace.
	 * @return callable|null
	 */
	public static function callback( string $slug, string $group, string $segment = '', string $php_namespace = __NAMESPACE__ ): ?callable {
		$file = PBSW_DIR . 'includes/' . $segment . 'blocks/class-' . $group . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
		$class  = $php_namespace . '\\' . self::class_name( $group );
		$method = self::method( $slug );
		if ( ! class_exists( $class ) || ! method_exists( $class, $method ) ) {
			return null;
		}
		$callback = array( $class, $method );

		return is_callable( $callback ) ? $callback : null;
	}

	/**
	 * Group → class name (`marketing` → `Marketing`).
	 *
	 * @param string $group Group.
	 * @return string
	 */
	public static function class_name( string $group ): string {
		return str_replace( ' ', '_', ucwords( str_replace( '-', ' ', $group ) ) );
	}

	/**
	 * Slug → render method (`pricing-table` → `render_pricing_table`).
	 *
	 * @param string $slug Slug.
	 * @return string
	 */
	public static function method( string $slug ): string {
		return 'render_' . str_replace( '-', '_', $slug );
	}

	/**
	 * Block name → published stylesheet key for every free block plus the four P1 blocks.
	 *
	 * @return array<string, string>
	 */
	public static function styles(): array {
		$map = self::CORE_STYLES;
		foreach ( array_keys( self::BLOCKS ) as $slug ) {
			$map[ 'pbs/' . $slug ] = 'block/' . $slug . '.css';
		}

		return $map;
	}

	/**
	 * Published stylesheet key → path inside the plugin, for the same set.
	 *
	 * @return array<string, string>
	 */
	public static function style_sources(): array {
		$out = array();
		foreach ( self::styles() as $name => $key ) {
			$out[ $key ] = 'assets/blocks/' . substr( $name, 4 ) . '.css';
		}

		return $out;
	}

	/**
	 * Every block held to the test bar: name → [segment, group|null] (null = a P1 core block).
	 *
	 * @return array<string, array{0: string, 1: string|null}>
	 */
	public static function tested(): array {
		$out = array();
		foreach ( self::CORE_TESTED as $slug ) {
			$out[ 'pbs/' . $slug ] = array( '', null );
		}
		foreach ( self::BLOCKS as $slug => $group ) {
			$out[ 'pbs/' . $slug ] = array( '', $group );
		}

		return $out;
	}
}
