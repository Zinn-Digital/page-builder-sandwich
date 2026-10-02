<?php
/**
 * SEO plugin content analysis sees what the visitor sees (P13, free, pbs-p10).
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Seo;

use ZinnDigital\PBS\Rest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Yoast SEO, Rank Math and All in One SEO analyse the editor's SAVED markup in the browser, where
 * text that is filled in when the page is shown (dynamic content: block bindings, query loops,
 * the server-rendered blocks) is not there yet. This route renders the editor's current blocks
 * exactly as the page will (do_blocks, as the post) and the editor bundle (src/seo) hands that
 * HTML to each plugin's own content hook. SEOPress analyses on the server with do_blocks() and
 * needs nothing.
 */
final class Analysis {

	/** Editor script handle. */
	public const HANDLE = 'pbsw-seo-analysis';

	/** Largest editor content rendered, in bytes. */
	private const MAX_CONTENT = 2097152;

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
		add_action( 'enqueue_block_editor_assets', array( self::class, 'enqueue' ) );
	}

	/**
	 * REST route.
	 *
	 * @return void
	 */
	public static function routes(): void {
		register_rest_route(
			Rest::NAMESPACE,
			'/seo/rendered',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => static function ( \WP_REST_Request $request ) {
					$out = self::rendered( (int) $request['post_id'], (string) $request['content'] );
					return is_wp_error( $out ) ? $out : new \WP_REST_Response( array( 'html' => $out ) );
				},
				'permission_callback' => static fn( \WP_REST_Request $request ): bool => current_user_can( 'edit_post', (int) $request['post_id'] ),
				'args'                => array(
					'post_id' => array(
						'type'     => 'integer',
						'required' => true,
					),
					'content' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * The HTML a visitor would get for this content on this post (no scripts, no styles).
	 *
	 * @param int    $post_id Post.
	 * @param string $content Block markup (the editor's current state).
	 * @return string|\WP_Error
	 */
	public static function rendered( int $post_id, string $content ) {
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return new \WP_Error( 'pbsw_not_found', __( 'There is no such post.', 'page-builder-sandwich' ), array( 'status' => 404 ) );
		}
		if ( strlen( $content ) > self::MAX_CONTENT ) {
			return new \WP_Error( 'pbsw_too_large', __( 'The page is too large to analyse.', 'page-builder-sandwich' ), array( 'status' => 413 ) );
		}
		$key    = 'pbsw_seo_r_' . md5( $post_id . '|' . $content );
		$cached = get_transient( $key );
		if ( is_string( $cached ) ) {
			return $cached;
		}
		// Render as the page itself, so bindings and loops resolve against this post.
		global $post;
		$previous = $post;
		$post     = get_post( $post_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restored below; do_blocks() reads the current post.
		setup_postdata( $post );
		$html = do_blocks( $content );
		$post = $previous; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restoring.
		if ( $previous instanceof \WP_Post ) {
			setup_postdata( $previous );
		} else {
			wp_reset_postdata();
		}
		$html = (string) preg_replace( '#<(script|style|template|noscript)\b.*?</\1>#is', '', $html );
		set_transient( $key, $html, 10 * MINUTE_IN_SECONDS );

		return $html;
	}

	/**
	 * The editor bundle (post editor only: the SEO plugins' analysis lives there).
	 *
	 * @return void
	 */
	public static function enqueue(): void {
		$asset_file = PBSW_DIR . 'build/seo.asset.php';
		if ( ! is_readable( $asset_file ) || ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		$asset = require $asset_file;
		wp_enqueue_script( self::HANDLE, PBSW_URL . 'build/seo.js', (array) ( $asset['dependencies'] ?? array() ), (string) ( $asset['version'] ?? PBSW_VERSION ), true );
	}
}
