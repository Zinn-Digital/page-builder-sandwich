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
