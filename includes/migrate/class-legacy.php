<?php
/**
 * Detects posts that still hold legacy Page Builder Sandwich content.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Migrate;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The one definition of "this post needs converting", used by the editor trigger, the background
 * batch and the admin tools alike.
 */
final class Legacy {

	/** Post meta: the plugin version that converted the post (absent ⇒ not converted). */
	public const CONVERTED_META = '_pbsw_converted';

	/** Legacy post meta that on its own marks a post as built with legacy PBS. */
	public const LEGACY_META = array( 'pbs_style', 'pbs_icons' );

	/**
	 * Whether a post needs converting: legacy markers in its content (outside any block) or
	 * legacy PBS meta, and not already converted.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function post_is_legacy( int $post_id ): bool {
		if ( '' !== (string) get_post_meta( $post_id, self::CONVERTED_META, true ) ) {
			return false;
		}
		$post = get_post( $post_id );
		if ( ! $post ) {
			return false;
		}
		if ( Converter::is_legacy( (string) $post->post_content ) ) {
			return true;
		}
		foreach ( self::LEGACY_META as $key ) {
			if ( '' !== trim( (string) get_post_meta( $post_id, $key, true ) ) ) {
				return true;
			}
		}

		return false;
	}
}
