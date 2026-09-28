<?php
/**
 * Generated front-end files under the neutral uploads folder.
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
 * Writes and deletes the files the perf layer generates: per-page compiled stylesheets
 * (`uploads/<prefix>-assets/<hash>.css`) and local web fonts (`…/fonts/`).
 *
 * ⛔ Same rules as \ZinnDigital\PBS\Assets (docs/adr/0033): the folder and file names carry only
 * the neutral prefix and a content hash, never the plugin's name; a write that needs filesystem
 * credentials is not attempted (the caller falls back to inline CSS); and nothing is deleted
 * unless its real path is a generated file inside that folder.
 */
final class Files {

	/**
	 * The generated-files folder: `[ dir, url ]`, or null when uploads is unusable.
	 *
	 * @param string $sub Optional sub-folder (`fonts`).
	 * @return array{0: string, 1: string}|null
	 */
	public static function folder( string $sub = '' ): ?array {
		$uploads = wp_upload_dir( null, false );
		if ( ! empty( $uploads['error'] ) ) {
			return null;
		}
		$name = Settings::prefix() . '-assets' . ( '' === $sub ? '' : '/' . $sub );

		return array(
			trailingslashit( (string) $uploads['basedir'] ) . $name,
			trailingslashit( (string) $uploads['baseurl'] ) . $name,
		);
	}

	/**
	 * A STORED URL of a generated file, re-based on today's uploads URL.
	 *
	 * ⛔ A stored URL carries the site address of the moment the file was written. A site that
	 * moved afterwards (new domain, http → https, a staging copy) kept pointing at the old host,
	 * so its block stylesheets, page sheets and Interactivity modules silently stopped loading —
	 * found by the L09 block harness, whose site address changes after activation. Everything
	 * from the `<prefix>-assets/` segment on is kept; the part before it is today's uploads URL.
	 * A URL outside the generated folder is returned unchanged.
	 *
	 * @param string $stored The URL as stored.
	 * @return string
	 */
	public static function current_url( string $stored ): string {
		$uploads = wp_upload_dir( null, false );
		$marker  = '/' . Settings::prefix() . '-assets/';
		$at      = strrpos( $stored, $marker );
		if ( ! empty( $uploads['error'] ) || false === $at ) {
			return $stored;
		}

		return untrailingslashit( (string) $uploads['baseurl'] ) . substr( $stored, $at );
	}

	/**
	 * Write a file named after its content hash. Existing identical files are reused.
	 *
	 * @param string $body      File contents.
	 * @param string $extension `css`, `js` or `woff2`.
	 * @param string $sub       Optional sub-folder.
	 * @param string $name      Optional file-name prefix: `g-` for the site sheet (pbs-d5), so it
	 *                          can be told apart from the per-page sheets in the same folder.
	 * @return array{url: string, path: string, hash: string}|null Null when it could not be written.
	 */
	public static function write( string $body, string $extension, string $sub = '', string $name = '' ): ?array {
		$folder     = self::folder( $sub );
		$filesystem = self::filesystem();
		if ( null === $folder || null === $filesystem || ! in_array( $name, array( '', 'g-' ), true ) || ! wp_mkdir_p( $folder[0] ) ) {
			return null;
		}
		$hash = substr( sha1( $body ), 0, 12 );
		$file = $name . $hash . '.' . $extension;
		$path = $folder[0] . '/' . $file;
		if ( ! $filesystem->exists( $path ) ) {
			// Write to a temporary name and rename, so a concurrent reader never sees half a file.
			$tmp = $path . '.' . wp_generate_password( 6, false ) . '.tmp';
			if ( ! $filesystem->put_contents( $tmp, $body, FS_CHMOD_FILE ) || ! $filesystem->move( $tmp, $path, true ) ) {
				$filesystem->delete( $tmp );
				return null;
			}
		}

		return array(
			'url'  => $folder[1] . '/' . $file,
			'path' => $path,
			'hash' => $hash,
		);
	}

	/**
	 * Delete a generated file. A path read back from post meta or an option is data, so it is
	 * deleted only when it resolves to a hashed file inside a `<prefix>-assets` folder in uploads.
	 *
	 * @param string $path Path.
	 * @return bool Whether a file was deleted.
	 */
	public static function delete( string $path ): bool {
		if ( ! self::is_generated( $path ) ) {
			return false;
		}
		wp_delete_file( $path );

		return ! file_exists( $path );
	}

	/**
	 * Is this a file this class could have written?
	 *
	 * @param string $path Path.
	 * @return bool
	 */
	public static function is_generated( string $path ): bool {
		$uploads = wp_upload_dir( null, false );
		$base    = realpath( (string) ( $uploads['basedir'] ?? '' ) );
		$real    = realpath( $path );
		if ( false === $base || false === $real || ! is_file( $real ) ) {
			return false;
		}

		return str_starts_with( $real, trailingslashit( $base ) )
			&& 1 === preg_match( '#/[a-z][a-z0-9]{0,7}-assets/(?:(?:fonts|lottie)/)?(?:g-)?[0-9a-f]{12}\.(?:css|js|woff2|json)$#', $real );
	}

	/**
	 * Delete every generated file (uninstall). Only names this class writes are touched.
	 *
	 * @return int Files deleted.
	 */
	public static function delete_all(): int {
		$deleted = 0;
		// `lottie`: the Pro animation files (uploaded JSON, re-encoded and written by this class).
		foreach ( array( '', 'fonts', 'lottie' ) as $sub ) {
			$folder = self::folder( $sub );
			if ( null === $folder ) {
				continue;
			}
			foreach ( (array) glob( $folder[0] . '/*.{css,woff2,json}', GLOB_BRACE ) as $file ) {
				if ( is_string( $file ) && self::delete( $file ) ) {
					++$deleted;
				}
			}
		}

		return $deleted;
	}

	/**
	 * A direct-access filesystem, or null when writing would need credentials.
	 *
	 * @return \WP_Filesystem_Base|null
	 */
	private static function filesystem(): ?\WP_Filesystem_Base {
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( 'direct' !== get_filesystem_method() || ! WP_Filesystem() ) {
			return null;
		}
		global $wp_filesystem;

		return $wp_filesystem instanceof \WP_Filesystem_Base ? $wp_filesystem : null;
	}
}
