<?php
/**
 * Cache-plugin purges (pbs-p12).
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Assets\Perf;

use ZinnDigital\PBS\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clears the page cache of LiteSpeed Cache, WP Rocket, W3 Total Cache and Zinn Cache when a PBS
 * page is published or updated, and whenever its compiled stylesheet changes WITHOUT a save
 * (a plugin update or a prefix change makes every page's stylesheet new: a cached page would go
 * on pointing at the old file). Each purge runs only when that plugin is active, through that
 * plugin's own public API:
 *
 * | plugin          | detected by                                   | per post                                | everything                    |
 * |-----------------|-----------------------------------------------|-----------------------------------------|-------------------------------|
 * | LiteSpeed Cache | `LSCWP_V`                                     | `do_action( 'litespeed_purge_post' )`   | `litespeed_purge_all`         |
 * | WP Rocket       | `rocket_clean_post()`                         | `rocket_clean_post()`                   | `rocket_clean_domain()`       |
 * | W3 Total Cache  | `w3tc_flush_post()`                           | `w3tc_flush_post()`                     | `w3tc_flush_all()`            |
 * | Zinn Cache      | its `Purge_Controller` on `save_post`         | `Purge_Controller::on_save_post()`      | `Purge_Controller::purge_all()` |
 *
 * ⭐ Zinn Cache has no public purge hook; its per-post purge is the public `on_save_post()` of the
 * controller it registers on `save_post`, so that registered instance is called directly — the
 * same object, with the same settings (auto-purge off means it never registered, and so is
 * never called). On a save it has already run from `save_post`, so it is not called twice.
 */
final class Cache_Purge {

	/**
	 * Posts purged on this request (a save fires both the insert hook and the CSS-changed hook).
	 *
	 * @var array<int, bool>
	 */
	private static array $done = array();

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'wp_after_insert_post', array( self::class, 'on_insert' ), 20, 2 );
		add_action( 'pbsw_page_css_changed', array( self::class, 'on_css_changed' ) );
		add_action( 'update_option_' . Settings::OPTION, array( self::class, 'purge_all' ) );
	}

	/**
	 * `wp_after_insert_post`: a published PBS post was created or updated.
	 *
	 * @param int           $post_id Post ID.
	 * @param \WP_Post|null $post    Post.
	 * @return void
	 */
	public static function on_insert( $post_id, $post = null ): void {
		if ( ! $post instanceof \WP_Post || 'publish' !== $post->post_status
			|| wp_is_post_revision( (int) $post_id ) || ! Perf::is_pbs_content( (string) $post->post_content ) ) {
			return;
		}
		self::purge_post( (int) $post_id, true );
	}

	/**
	 * `pbsw_page_css_changed`.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function on_css_changed( $post_id ): void {
		self::purge_post( (int) $post_id, doing_action( 'wp_after_insert_post' ) );
	}

	/**
	 * Purge one post from every active cache plugin.
	 *
	 * @param int  $post_id Post ID.
	 * @param bool $saving  Inside a save (Zinn Cache already purged from `save_post`).
	 * @return array<int, string> The plugins purged.
	 */
	public static function purge_post( int $post_id, bool $saving = false ): array {
		if ( $post_id <= 0 || isset( self::$done[ $post_id ] ) ) {
			return array();
		}
		self::$done[ $post_id ] = true;

		$purged = array();
		if ( defined( 'LSCWP_V' ) ) {
			do_action( 'litespeed_purge_post', $post_id ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- LiteSpeed Cache's own API.
			$purged[] = 'litespeed';
		}
		if ( function_exists( 'rocket_clean_post' ) ) {
			rocket_clean_post( $post_id );
			$purged[] = 'wp-rocket';
		}
		if ( function_exists( 'w3tc_flush_post' ) ) {
			w3tc_flush_post( $post_id );
			$purged[] = 'w3-total-cache';
		}
		$zinn = self::zinn_cache();
		if ( null !== $zinn && ! $saving ) {
			$zinn->on_save_post( $post_id );
			$purged[] = 'zinn-cache';
		}

		/**
		 * Fires after PBS purged a post from the active cache plugins.
		 *
		 * @param int                $post_id Post ID.
		 * @param array<int, string> $purged  Plugins purged.
		 */
		do_action( 'pbsw_cache_purged', $post_id, $purged );

		return $purged;
	}

	/**
	 * Purge everything (the class prefix changed: every page's markup and stylesheet did).
	 *
	 * @return array<int, string> The plugins purged.
	 */
	public static function purge_all(): array {
		$purged = array();
		if ( defined( 'LSCWP_V' ) ) {
			do_action( 'litespeed_purge_all' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- LiteSpeed Cache's own API.
			$purged[] = 'litespeed';
		}
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
			$purged[] = 'wp-rocket';
		}
		if ( function_exists( 'w3tc_flush_all' ) ) {
			w3tc_flush_all();
			$purged[] = 'w3-total-cache';
		}
		$zinn = self::zinn_cache();
		if ( null !== $zinn ) {
			$zinn->purge_all();
			$purged[] = 'zinn-cache';
		}

		return $purged;
	}

	/**
	 * The published front-end files changed (an update): empty every page cache within reach,
	 * then announce it.
	 *
	 * ⛔ NAV1 / 6.36.23: 6.36.22 renamed the navigation menu's and site header's scripts and
	 * deleted the old files, so a page a cache had saved went on naming them and its menu did
	 * nothing — on sites nobody can purge from outside (a licensing-service auto-update). Every
	 * publish that writes or deletes a file now lands here (Assets::publish()). Each cache is
	 * asked through its own public API, only when it is installed; a failing one never breaks the
	 * update that called it.
	 *
	 * @return array<int, string> The caches asked.
	 */
	public static function assets_changed(): array {
		try {
			$purged = self::purge_all(); // LiteSpeed, WP Rocket, W3 Total Cache, Zinn Cache.
		} catch ( \Throwable $e ) {
			$purged = array();
		}
		$ask = static function ( string $name, callable $purge ) use ( &$purged ): void {
			try {
				$purge();
				$purged[] = $name;
			} catch ( \Throwable $e ) {
				unset( $e ); // A cache plugin's failure must never break the update that called it.
			}
		};
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			$ask( 'wp-super-cache', static fn() => wp_cache_clear_cache() );
		}
		if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
			$ask( 'sg-optimizer', static fn() => sg_cachepress_purge_cache() );
		}
		if ( class_exists( '\CF\WordPress\Hooks' ) && method_exists( '\CF\WordPress\Hooks', 'purgeCacheEverything' ) ) {
			$ask( 'cloudflare', static fn() => ( new \CF\WordPress\Hooks() )->purgeCacheEverything() );
		}
		/**
		 * Filters the extra purgers run when the published files change: name => callable. A host
		 * adds its own (a CDN, a proxy); one that throws is skipped and not reported as purged.
		 *
		 * @param array<string, callable> $purgers Purgers.
		 */
		foreach ( (array) apply_filters( 'pbsw_cache_purgers', array() ) as $name => $purge ) {
			if ( is_string( $name ) && is_callable( $purge ) ) {
				$ask( $name, $purge );
			}
		}
		if ( ! in_array( 'zinn-cache', $purged, true ) && has_action( 'zinn_cache_purge_all' ) ) {
			$ask( 'zinn-cache', static fn() => do_action( 'zinn_cache_purge_all' ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Zinn Cache's own API.
		}
		/**
		 * Fires when PBS's published front-end files changed and the page caches it can reach
		 * were emptied: hook it to purge what PBS cannot reach (a CDN, a proxy, a host's cache).
		 *
		 * @param array<int, string> $purged The caches already asked.
		 */
		do_action( 'pbs_assets_changed', $purged ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- the public name of the legacy pbs_ hook family (pbs_enqueue_scripts).

		return $purged;
	}

	/**
	 * Forget per-request state (tests).
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$done = array();
	}

	/**
	 * Zinn Cache's registered purge controller, if it is active with auto-purge on.
	 *
	 * @return object|null
	 */
	private static function zinn_cache(): ?object {
		global $wp_filter;
		$hook = $wp_filter['save_post'] ?? null;
		if ( ! $hook instanceof \WP_Hook ) {
			return null;
		}
		foreach ( $hook->callbacks as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$fn = $callback['function'] ?? null;
				if ( is_array( $fn ) && is_object( $fn[0] ?? null ) && 'Zinn\Cache\Purge_Controller' === get_class( $fn[0] )
					&& method_exists( $fn[0], 'on_save_post' ) && method_exists( $fn[0], 'purge_all' ) ) {
					return $fn[0];
				}
			}
		}

		return null;
	}
}
