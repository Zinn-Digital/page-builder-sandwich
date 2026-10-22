<?php
/**
 * Working as one with Zinn® SEO, our own SEO plugin (zs-135, zs-230).
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Seo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * With Zinn® SEO active there is ONE of everything on a page, and each plugin does its own part:
 *
 * - The AI helper's meta title and description are saved into Zinn® SEO's own fields, through
 *   its API, so its history, its llms.txt and the page cache hear of the change.
 * - Zinn® SEO reads the page as visitors get it by itself (it renders every builder's page on
 *   the server), so the editor script that hands the rendered page to Yoast SEO and Rank Math is
 *   not loaded when neither of those is there.
 * - The structured data of the FAQ, how-to, pricing, review and breadcrumb blocks joins the one
 *   graph Zinn® SEO prints in the head (its adapter reads them through `pbsw_schema_skip` and
 *   this plugin's public block data). Outside a page view — the editor's "what does this page
 *   print" report — the same answer is given here, so the report says what the page really does.
 *
 * ⛔ Everything asks first whether Zinn® SEO is active: a site without it runs none of this.
 * Zinn® SEO is reached only through what it publishes (its version constant and `zinn_seo()`).
 */
final class Zinn_Seo {

	/** Our key for Zinn® SEO in the AI helper's list of SEO plugins. */
	public const KEY = 'zinn-seo';

	/** The structured-data types Zinn® SEO takes from this plugin's blocks into its graph. */
	public const HANDED = array( 'FAQPage', 'HowTo', 'Product', 'Review' );

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'pbsw_ai_seo_plugins', array( self::class, 'ai_plugins' ) );
		add_filter( 'pbsw_ai_seo_write', array( self::class, 'ai_write' ), 10, 5 );
		add_filter( 'pbsw_schema_skip', array( self::class, 'schema_skip' ), 30 );
	}

	/**
	 * Is Zinn® SEO active on this site?
	 *
	 * @return bool
	 */
	public static function active(): bool {
		return defined( 'ZINN_SEO_VERSION' ) && function_exists( 'zinn_seo' );
	}

	/**
	 * Does Zinn® SEO print the page's head and its structured data (it stands back while another
	 * SEO plugin does)?
	 *
	 * @return bool
	 */
	public static function prints_head(): bool {
		if ( ! self::active() ) {
			return false;
		}
		$head = '\ZinnDigital\ZinnSEO\Head\Head';

		return ! is_callable( array( $head, 'should_print' ) ) || (bool) call_user_func( array( $head, 'should_print' ) );
	}

	/**
	 * Has the site switched Zinn® SEO's work with this plugin off (its Integrations screen)?
	 *
	 * @return bool
	 */
	public static function switched_off(): bool {
		if ( ! self::active() ) {
			return true;
		}
		$api = call_user_func( 'zinn_seo' );
		$off = is_object( $api ) && is_callable( array( $api, 'setting' ) ) ? (array) $api->setting( 'integrations_off' ) : array();

		return in_array( 'page-builder-sandwich', array_map( 'strval', $off ), true );
	}

	/**
	 * Is a module of Zinn® SEO switched on?
	 *
	 * @param string $module Module id.
	 * @return bool
	 */
	private static function module_on( string $module ): bool {
		$api = self::active() ? call_user_func( 'zinn_seo' ) : null;

		return is_object( $api ) && is_callable( array( $api, 'module_enabled' ) ) && (bool) $api->module_enabled( $module );
	}

	/**
	 * Is Zinn® SEO the only plugin analysing the page in the editor? Then the script that feeds
	 * the rendered page to Yoast SEO and Rank Math has nobody to feed.
	 *
	 * @return bool
	 */
	public static function sole_analyser(): bool {
		return self::active() && ! defined( 'WPSEO_VERSION' ) && ! defined( 'RANK_MATH_VERSION' ) && ! class_exists( '\\RankMath', false );
	}

	/**
	 * `pbsw_ai_seo_plugins`: the AI helper can save into Zinn® SEO.
	 *
	 * @param mixed $plugins Keys of the active SEO plugins.
	 * @return array<int, string>
	 */
	public static function ai_plugins( $plugins ): array {
		$plugins = array_values( array_map( 'strval', (array) $plugins ) );
		if ( self::active() && ! in_array( self::KEY, $plugins, true ) ) {
			array_unshift( $plugins, self::KEY );
		}

		return $plugins;
	}

	/**
	 * `pbsw_ai_seo_write`: save a meta title and description into Zinn® SEO's fields.
	 *
	 * @param mixed  $written     Already written by another listener.
	 * @param string $plugin      Our key of the plugin to write to.
	 * @param int    $post_id     Post.
	 * @param string $title       Meta title.
	 * @param string $description Meta description.
	 * @return bool
	 */
	public static function ai_write( $written, $plugin = '', $post_id = 0, $title = '', $description = '' ): bool {
		if ( self::KEY !== $plugin || ! self::active() ) {
			return (bool) $written;
		}
		$api = call_user_func( 'zinn_seo' );
		if ( ! is_object( $api ) || ! is_callable( array( $api, 'set_post_field' ) ) ) {
			return (bool) $written;
		}

		return true === $api->set_post_field( (int) $post_id, 'title', (string) $title ) && true === $api->set_post_field( (int) $post_id, 'desc', (string) $description );
	}

	/**
	 * `pbsw_schema_skip`, outside a page view: the types Zinn® SEO prints in its own graph for a
	 * page (on a page view its adapter answers with the exact types it took, at priority 20).
	 *
	 * @param mixed $taken Types left to the SEO plugin.
	 * @return array<int, string>
	 */
	public static function schema_skip( $taken ): array {
		$taken = array_values( array_map( 'strval', (array) $taken ) );
		if ( ! self::prints_head() || self::switched_off() || ( ! is_admin() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) && ! ( defined( 'WP_CLI' ) && WP_CLI ) ) ) {
			return $taken;
		}
		$ours = self::module_on( 'blocks' ) ? self::HANDED : array();
		if ( self::module_on( 'schema' ) ) {
			$ours[] = 'BreadcrumbList';
		}

		return array_values( array_unique( array_merge( $taken, $ours ) ) );
	}
}
