<?php
/**
 * The P2 performance engine: registration and shared helpers.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Assets\Perf;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Boots every part of the performance engine (lane L08, milestone P2):
 *
 * | feature  | class         | what it does                                                   |
 * |----------|---------------|----------------------------------------------------------------|
 * | pbs-p1   | Block_Assets  | a block's CSS loads only where the block renders               |
 * | pbs-p2   | Page_Css      | one compiled, cached stylesheet per page                       |
 * | pbs-p3   | Modules       | Interactivity API view modules via the neutral path; no jQuery |
 * | pbs-p4   | Layout_Guard  | images/video get their dimensions; fonts swap                  |
 * | pbs-p5   | Lcp           | first large above-the-fold image first, the rest lazy          |
 * | pbs-p6   | Fonts         | Google Fonts downloaded and served locally                     |
 * | pbs-p12  | Cache_Purge   | cache plugins purged on publish and on compiled-CSS change     |
 *
 * pbs-p8 (critical CSS) is premium and lives under the premium-only directory; pbs-p9 (minimal
 * DOM) is a property of Core\Render and is held by its unit test.
 */
final class Perf {

	/**
	 * Register every part.
	 *
	 * @return void
	 */
	public static function register(): void {
		Block_Assets::register();
		Page_Css::register();
		Modules::register();
		Layout_Guard::register();
		Lcp::register();
		Fonts::register();
		Cache_Purge::register();
		if ( function_exists( 'pbsw_fs' ) ) {
			pbsw_fs()->add_action( 'after_uninstall', array( self::class, 'uninstall' ) );
		}
	}

	/**
	 * Does this content carry PBS blocks or legacy PBS markup (so the engine should act on it)?
	 *
	 * @param string $content Post content.
	 * @return bool
	 */
	public static function is_pbs_content( string $content ): bool {
		return str_contains( $content, '<!-- wp:pbs/' ) || str_contains( $content, '[pbs_' );
	}

	/**
	 * Uninstall: delete every generated file and the compiled state of every post.
	 *
	 * @return void
	 */
	public static function uninstall(): void {
		Files::delete_all();
		delete_post_meta_by_key( Page_Css::META );
		delete_post_meta_by_key( Page_Css::META_FILE );
		delete_option( Fonts::OPTION );
	}
}
