<?php
/**
 * Convert a legacy post the moment someone opens it in an editor (pbs-f4).
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Migrate;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Converts (with backup) before the editor reads the post, so the person editing sees blocks,
 * not legacy markup — whether or not the background run has reached this post yet.
 *
 * Two doors, one rule:
 * - the block editor loads the post over REST (`GET /wp/v2/<type>/<id>?context=edit`), so the
 *   conversion runs in `rest_request_before_callbacks`, before the controller reads the row;
 * - the classic editor (and the block editor's first page load) goes through `post.php`, so it
 *   also runs on `load-post.php`.
 *
 * ⛔ Only for a user who may edit THAT post (`edit_post` on its id), never a post a person undid
 * (Batch::OPTOUT_META), never one already converted — and never on a front-end view.
 */
final class First_Open {

	/**
	 * Hook both doors.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'load-post.php', array( self::class, 'on_load_post' ) );
		add_filter( 'rest_request_before_callbacks', array( self::class, 'on_rest' ), 10, 3 );
	}

	/**
	 * `post.php?post=<id>&action=edit`.
	 *
	 * @return void
	 */
	public static function on_load_post(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a read of which post is being opened; the capability check below is the authorisation.
		$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- as above.
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		if ( $post_id > 0 && 'edit' === $action ) {
			self::maybe_convert( $post_id );
		}
	}

	/**
	 * A REST read of one post in edit context.
	 *
	 * @param mixed            $response Response so far (passed through untouched).
	 * @param mixed            $handler  Route handler.
	 * @param \WP_REST_Request $request  Request.
	 * @return mixed
	 */
	public static function on_rest( $response, $handler, $request ) {
		unset( $handler );
		if ( ! $request instanceof \WP_REST_Request || 'GET' !== $request->get_method() || 'edit' !== $request->get_param( 'context' ) ) {
			return $response;
		}
		if ( 1 !== preg_match( '#^/wp/v2/([a-z0-9_-]+)/(\d+)$#', (string) $request->get_route(), $m ) ) {
			return $response;
		}
		// ⛔ `/wp/v2/users/1` has the same shape: only a route that IS this post's own type counts.
		$post = get_post( (int) $m[2] );
		$type = $post instanceof \WP_Post ? get_post_type_object( $post->post_type ) : null;
		$base = null === $type ? '' : ( is_string( $type->rest_base ) && '' !== $type->rest_base ? $type->rest_base : $type->name );
		if ( '' === $base || $base !== $m[1] ) {
			return $response;
		}
		self::maybe_convert( (int) $m[2] );
		return $response;
	}

	/**
	 * Convert when every condition holds. Returns whether a conversion ran.
	 *
	 * @param int $post_id Post id.
	 * @return bool
	 */
	public static function maybe_convert( int $post_id ): bool {
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post || 'revision' === $post->post_type || 'attachment' === $post->post_type ) {
			return false;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return false;
		}
		// Cheap test first: every legacy marker contains `pbs`.
		if ( ! str_contains( (string) $post->post_content, 'pbs' ) ) {
			return false;
		}
		if ( '' !== (string) get_post_meta( $post_id, Batch::CONVERTED_META, true ) || '' !== (string) get_post_meta( $post_id, Batch::OPTOUT_META, true ) ) {
			return false;
		}
		if ( ! Legacy::post_is_legacy( $post_id ) ) {
			return false;
		}
		return 'converted' === Batch::convert_post( $post_id, 'auto' )['outcome'];
	}
}
