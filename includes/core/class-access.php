<?php
/**
 * Per-object capability helpers.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Authorisation, always against the SPECIFIC object a request names.
 *
 * ⛔ The legacy plugin treated a valid nonce as permission: ~20 AJAX handlers checked
 * `wp_verify_nonce( …, 'pbs' )` and nothing else, so a Subscriber could overwrite any post
 * (docs/plugins-overhaul/10-audit-pbs.md §3). A nonce proves where a request came from, never
 * who may do what. Every route this plugin registers asks one of these helpers about the post
 * id in the request, and REST's own cookie nonce handles the CSRF half.
 */
final class Access {

	/**
	 * Whether the current user may edit this post (maps `edit_post` to the post's own
	 * type, status and author through map_meta_cap).
	 *
	 * @param int $post_id Post id.
	 * @return bool
	 */
	public static function can_edit_post( int $post_id ): bool {
		if ( $post_id <= 0 || null === get_post( $post_id ) ) {
			return false;
		}

		return current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Whether the current user may read this post.
	 *
	 * @param int $post_id Post id.
	 * @return bool
	 */
	public static function can_read_post( int $post_id ): bool {
		if ( $post_id <= 0 || null === get_post( $post_id ) ) {
			return false;
		}

		return current_user_can( 'read_post', $post_id );
	}

	/**
	 * The post id a REST request names: the `post_id` param, else the `id` URL param.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return int
	 */
	public static function request_post_id( \WP_REST_Request $request ): int {
		$id = $request->get_param( 'post_id' );
		if ( null === $id || '' === $id ) {
			$id = $request->get_param( 'id' );
		}

		return is_numeric( $id ) ? (int) $id : 0;
	}

	/**
	 * REST permission callback: may the user edit the post this request names?
	 *
	 * Returns a WP_Error (401 when logged out, 403 otherwise) rather than `false`, so a client
	 * sees why it was refused.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool|\WP_Error
	 */
	public static function rest_edit_post_permission( \WP_REST_Request $request ) {
		$post_id = self::request_post_id( $request );
		if ( self::can_edit_post( $post_id ) ) {
			return true;
		}

		return new \WP_Error(
			'pbsw_forbidden',
			__( 'You are not allowed to edit this item.', 'page-builder-sandwich' ),
			array( 'status' => is_user_logged_in() ? 403 : 401 )
		);
	}

	/**
	 * REST permission callback for site-wide tools (migration batch, settings).
	 *
	 * @return bool|\WP_Error
	 */
	public static function rest_manage_permission() {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		return new \WP_Error(
			'pbsw_forbidden',
			__( 'You are not allowed to do this.', 'page-builder-sandwich' ),
			array( 'status' => is_user_logged_in() ? 403 : 401 )
		);
	}
}
