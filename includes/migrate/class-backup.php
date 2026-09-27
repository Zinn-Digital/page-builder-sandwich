<?php
/**
 * The pre-conversion backup every conversion writes first, and the exact undo.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Migrate;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores what a post held before conversion in `_pbsw_legacy_backup` (JSON) and restores it
 * byte-for-byte.
 *
 * ⛔ Content is written with the kses content filters lifted for that one write. They are
 * active whenever the acting user lacks `unfiltered_html` — including a cron or WP-CLI run with
 * no user — and `safecss_filter_attr()` rewrites style attributes: that would make the undo
 * inexact, and would change a converted block's saved HTML so the editor reported it invalid.
 * Nothing new enters the post by this path: a restore writes back what the post held, and a
 * conversion writes the post's own markup restructured, with every style it moved sanitized.
 */
final class Backup {

	/** Post meta holding the backup JSON. */
	public const META = '_pbsw_legacy_backup';

	/** Backup format version. */
	private const FORMAT = 1;

	/**
	 * Saves the post's current content and legacy meta (overwrites an older backup).
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function save( int $post_id ): void {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}
		$content = (string) $post->post_content;
		$backup  = array(
			'format'         => self::FORMAT,
			'post_content'   => $content,
			'pbs_style'      => self::meta_or_null( $post_id, 'pbs_style' ),
			'pbs_icons'      => self::meta_or_null( $post_id, 'pbs_icons' ),
			// Which legacy storage generation the post was written in (legacy stored no version).
			'legacy_version' => 1 === preg_match( '/pbsandwich_column|\[pbs_button\b/', $content ) ? 'pre-4.0' : '4.0-5.1.0',
			'converter'      => defined( 'PBSW_VERSION' ) ? (string) PBSW_VERSION : '',
			'time'           => time(),
		);
		update_post_meta( $post_id, self::META, wp_slash( (string) wp_json_encode( $backup ) ) );
	}

	/**
	 * Whether a post has a backup.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function exists( int $post_id ): bool {
		return null !== self::read( $post_id );
	}

	/**
	 * The stored backup, or null when there is none or it is unreadable.
	 *
	 * @param int $post_id Post ID.
	 * @return array|null
	 */
	public static function read( int $post_id ): ?array {
		$raw = get_post_meta( $post_id, self::META, true );
		if ( ! is_string( $raw ) || '' === $raw ) {
			return null;
		}
		$data = json_decode( $raw, true );

		return is_array( $data ) && isset( $data['post_content'] ) && is_string( $data['post_content'] ) ? $data : null;
	}

	/**
	 * Restores the post exactly as it was before conversion and clears the converted marker.
	 * The backup is kept, so a later conversion can run again from the same starting point.
	 *
	 * @param int $post_id Post ID.
	 * @return bool True when the post now holds the backed-up content and meta.
	 */
	public static function restore( int $post_id ): bool {
		$backup = self::read( $post_id );
		if ( null === $backup || ! get_post( $post_id ) ) {
			return false;
		}
		if ( ! self::write_content( $post_id, $backup['post_content'] ) ) {
			return false;
		}
		foreach ( Legacy::LEGACY_META as $key ) {
			$value = $backup[ $key ] ?? null;
			if ( is_string( $value ) ) {
				update_post_meta( $post_id, $key, wp_slash( $value ) );
			} else {
				delete_post_meta( $post_id, $key );
			}
		}
		delete_post_meta( $post_id, Legacy::CONVERTED_META );

		$post = get_post( $post_id );
		if ( ! $post || (string) $post->post_content !== $backup['post_content'] ) {
			return false;
		}
		foreach ( Legacy::LEGACY_META as $key ) {
			if ( self::meta_or_null( $post_id, $key ) !== ( $backup[ $key ] ?? null ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Writes post content exactly (no kses rewrite; see the class note).
	 *
	 * @param int    $post_id Post ID.
	 * @param string $content Content.
	 * @return bool
	 */
	public static function write_content( int $post_id, string $content ): bool {
		$filtered = false !== has_filter( 'content_save_pre', 'wp_filter_post_kses' );
		if ( $filtered ) {
			kses_remove_filters();
		}
		$result = wp_update_post(
			wp_slash(
				array(
					'ID'            => $post_id,
					'post_content'  => $content,
					// ⛔ Empty on purpose: without it wp_update_post() re-validates the post's
					// stored template and REFUSES the write ("Invalid page template.") when
					// that is legacy's blank template, which only legacy registered — true of
					// every page built on the legacy blank canvas. Empty leaves the meta as is.
					'page_template' => '',
				)
			),
			true
		);
		if ( $filtered ) {
			kses_init_filters();
		}

		return ! is_wp_error( $result ) && 0 !== $result;
	}

	/**
	 * A single meta value, or null when the post has none.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @return string|null
	 */
	private static function meta_or_null( int $post_id, string $key ): ?string {
		if ( ! metadata_exists( 'post', $post_id, $key ) ) {
			return null;
		}
		$value = get_post_meta( $post_id, $key, true );

		return is_string( $value ) ? $value : null;
	}
}
