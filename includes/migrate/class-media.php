<?php
/**
 * Legacy sample images that lived INSIDE the legacy plugin folder.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Migrate;

use ZinnDigital\PBS\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Legacy 5.x's design templates inserted images bundled with the plugin
 * (`…/plugins/page-builder-sandwich/page_builder_sandwich/images/sample-team-1.jpg`), and the editor
 * saved those ABSOLUTE URLs into the customer's post. Updating the plugin replaces its folder, so
 * every one of them 404s the moment the new version is installed (measured on the legacy fixture:
 * five pages lost their pictures).
 *
 * The images the templates used ship with this plugin (assets/legacy-media/). A legacy URL whose
 * file is one of them is pointed at a copy in uploads under the neutral folder `<prefix>-media`
 * — never at this plugin's own URL, which would print the product's name on the page (ADR 0033).
 * The copy is made once, the first time a post needs it: at conversion (the rewritten URL is
 * saved; the backup keeps the original for undo) and, for a post not converted yet, at render.
 * A legacy URL whose file is not bundled is left exactly as it was.
 */
final class Media {

	/** A legacy plugin-folder URL: any host (or none), either edition's folder, any file. */
	private const URL_RE = '#(?:(?:https?:)?//[^\s"\'()<>]+?)?/wp-content/plugins/[A-Za-z0-9_-]+/page_builder_sandwich/(?:[A-Za-z0-9_-]+/)*([A-Za-z0-9_.-]+\.(?:jpe?g|png|gif|webp|svg))#i';

	/**
	 * Resolved URLs for this request (basename → URL or '' when not bundled / not copyable).
	 *
	 * @var array<string, string>
	 */
	private static array $resolved = array();

	/**
	 * Whether HTML holds any legacy plugin-folder URL (cheap test first).
	 *
	 * @param string $html HTML.
	 * @return bool
	 */
	public static function has_legacy_urls( string $html ): bool {
		return str_contains( $html, '/page_builder_sandwich/' ) && 1 === preg_match( self::URL_RE, $html );
	}

	/**
	 * Rewrite legacy plugin-folder URLs with a resolver (the pure half; unit-tested).
	 *
	 * @param string                  $html     HTML (or CSS, or block attributes' JSON).
	 * @param callable(string):string $resolver Basename → new URL, or '' to leave the URL alone.
	 * @return string
	 */
	public static function rewrite( string $html, callable $resolver ): string {
		if ( ! str_contains( $html, '/page_builder_sandwich/' ) ) {
			return $html;
		}

		return (string) preg_replace_callback(
			self::URL_RE,
			static function ( array $m ) use ( $resolver ): string {
				$url = (string) $resolver( $m[1] );
				return '' === $url ? $m[0] : $url;
			},
			$html
		);
	}

	/**
	 * Rewrite legacy URLs to uploads copies, copying the bundled files that are needed.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function localize( string $html ): string {
		return self::has_legacy_urls( $html ) ? self::rewrite( $html, array( self::class, 'url_for' ) ) : $html;
	}

	/**
	 * The uploads URL of a bundled legacy image, copying it there on first use; '' when the file
	 * is not bundled or cannot be copied (the legacy URL then stays).
	 *
	 * @param string $basename File name.
	 * @return string
	 */
	public static function url_for( string $basename ): string {
		if ( isset( self::$resolved[ $basename ] ) ) {
			return self::$resolved[ $basename ];
		}
		self::$resolved[ $basename ] = '';

		$source = PBSW_DIR . 'assets/legacy-media/' . $basename;
		if ( 1 !== preg_match( '/^[A-Za-z0-9_.-]+$/', $basename ) || ! is_readable( $source ) ) {
			return '';
		}
		$uploads = wp_upload_dir( null, false );
		if ( ! empty( $uploads['error'] ) ) {
			return '';
		}
		$folder = Settings::prefix() . '-media';
		$dir    = trailingslashit( (string) $uploads['basedir'] ) . $folder;
		$target = $dir . '/' . $basename;
		if ( ! is_readable( $target ) ) {
			$filesystem = self::filesystem();
			if ( null === $filesystem || ! wp_mkdir_p( $dir ) || ! $filesystem->copy( $source, $target, true, FS_CHMOD_FILE ) ) {
				return '';
			}
		}
		self::$resolved[ $basename ] = trailingslashit( (string) $uploads['baseurl'] ) . $folder . '/' . $basename;

		return self::$resolved[ $basename ];
	}

	/**
	 * The direct filesystem, or null when WordPress would need credentials.
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
