<?php
/**
 * Front-end assets served from a neutral path.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Publishes front-end CSS/JS out of the plugin directory into `uploads/<prefix>-assets/`.
 *
 * ⭐ WHY A COPY, AND WHY IT IS DONE IN wp-admin RATHER THAN ON A PAGE VIEW (docs/adr/0033).
 * A stylesheet enqueued from the plugin directory prints `/wp-content/plugins/page-builder-sandwich/`
 * into every page, which is the single loudest footprint a plugin leaves (CONTRACT §7). The
 * alternatives were a rewrite rule (needs a flush, breaks on nginx without extra config, and
 * serves every asset through PHP) and a REST/query endpoint (PHP on every asset request, no
 * static caching). A content-hashed static copy is served by the web server directly, is
 * cacheable for ever because its name changes when its content does, and needs no server
 * configuration.
 *
 * The copy is written on activation, on a version change and on a prefix change — all of them
 * admin-side events — so a front-end request never writes to disk. When a copy is missing or
 * the filesystem is not directly writable, the asset is printed inline instead: the page is
 * never unstyled and never falls back to the plugin path.
 */
final class Assets {

	/** Option holding what was published, for which version and prefix. */
	public const MANIFEST_OPTION = 'pbsw_published_assets';

	/** Front-end sources, keyed by the name callers use. */
	private const SOURCES = array(
		'front.css'  => 'assets/front.css',
		// Legacy (5.x) compatibility rules, enqueued only for posts that still need them (Assets\Legacy).
		'legacy.css' => 'assets/legacy.css',
		// Legacy (5.x) front-end behaviour, enqueued only for posts that need it (Assets\Legacy_Script).
		'legacy.js'  => 'assets/legacy.js',
	);

	/*
	 * ── P2 performance engine (lane L08 worker W7) ─────────────────────────────────────────
	 * Sources registered at load time by the perf layer (includes/assets/perf/): one stylesheet
	 * per block (pbs-p1) and the view script modules of interactive blocks (pbs-p3). Kept out of
	 * SOURCES so the two lists merge without touching each other.
	 */

	/**
	 * Sources added with add_source().
	 *
	 * @var array<string, string>
	 */
	private static array $added = array();

	/**
	 * Register a front-end source (a path inside this plugin) under a key.
	 *
	 * @param string $key      The key callers use, e.g. `block/row.css`.
	 * @param string $relative Path relative to the plugin directory.
	 * @return void
	 */
	public static function add_source( string $key, string $relative ): void {
		self::$added[ $key ] = $relative;
	}

	// ── end P2 ─────────────────────────────────────────────────────────────────────────────

	/** The token a source uses where the class prefix belongs. */
	private const PREFIX_TOKEN = '__PREFIX__';

	/**
	 * Hook the republish checks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_init', array( self::class, 'maybe_publish' ) );
	}

	/**
	 * Republish when the manifest was written for another version or prefix.
	 *
	 * @return void
	 */
	public static function maybe_publish(): void {
		if ( ! self::is_current( self::manifest() ) ) {
			self::publish();
		}
	}

	/**
	 * Copy every source into the neutral folder and record what was written.
	 *
	 * @return array<string, mixed> The new manifest.
	 */
	public static function publish(): array {
		$prefix   = Settings::prefix();
		$manifest = array(
			'version' => PBSW_VERSION,
			'prefix'  => $prefix,
			'keys'    => self::keys_signature(),
			'files'   => array(),
		);

		$filesystem = self::filesystem();
		$uploads    = wp_upload_dir( null, false );

		if ( null !== $filesystem && empty( $uploads['error'] ) ) {
			$folder = $prefix . '-assets';
			$dir    = trailingslashit( (string) $uploads['basedir'] ) . $folder;
			$url    = trailingslashit( (string) $uploads['baseurl'] ) . $folder;

			if ( wp_mkdir_p( $dir ) ) {
				foreach ( array_keys( self::sources() ) as $key ) {
					$body = self::source( $key, $prefix );
					if ( null === $body ) {
						continue;
					}
					$name = substr( sha1( $body ), 0, 12 ) . '.' . pathinfo( $key, PATHINFO_EXTENSION );
					$path = $dir . '/' . $name;
					if ( $filesystem->exists( $path ) || $filesystem->put_contents( $path, $body, FS_CHMOD_FILE ) ) {
						$manifest['files'][ $key ] = array(
							'url'  => $url . '/' . $name,
							'path' => $path,
						);
					}
				}
			}
		}

		self::remove_stale( self::manifest(), $manifest );
		update_option( self::MANIFEST_OPTION, $manifest, true );

		return $manifest;
	}

	/**
	 * Enqueue a front-end stylesheet under a neutral handle, from the published copy or inline.
	 *
	 * @param string $key A key of self::sources().
	 * @return void
	 */
	public static function enqueue_style( string $key ): void {
		$prefix = Settings::prefix();
		$body   = self::source( $key, $prefix );
		if ( null === $body ) {
			return;
		}

		// The handle becomes the element id WordPress prints (`<handle>-css`), so it carries the
		// neutral prefix and a content hash, never the plugin's name.
		$hash   = substr( sha1( $body ), 0, 8 );
		$handle = $prefix . '-' . $hash;
		if ( wp_style_is( $handle, 'enqueued' ) ) {
			return;
		}

		// ⛔ The published copy is used only when it IS this body (its name is the body's hash):
		// the manifest is keyed on version + prefix, so a build that changed a stylesheet without
		// a version bump went on serving the old file (measured in the visual-diff loop).
		$url = self::published_url( $key );
		if ( null !== $url && str_starts_with( basename( (string) wp_parse_url( $url, PHP_URL_PATH ) ), substr( sha1( $body ), 0, 12 ) . '.' ) ) {
			wp_enqueue_style( $handle, $url, array(), $hash );
			Assets\Perf\Files::allow_inline( $handle, $url );
			return;
		}

		wp_register_style( $handle, false, array(), $hash );
		wp_enqueue_style( $handle );
		wp_add_inline_style( $handle, $body );
	}

	/**
	 * Enqueue a front-end script under a neutral handle, deferred in the footer, from the
	 * published copy or inline.
	 *
	 * @param string $key    A key of self::sources().
	 * @param string $before JavaScript printed before it (configuration), or ''.
	 * @return string|null The handle, or null when the source is unknown.
	 */
	public static function enqueue_script( string $key, string $before = '' ): ?string {
		$prefix = Settings::prefix();
		$body   = self::source( $key, $prefix );
		if ( null === $body ) {
			return null;
		}

		// Printed as the element id `<handle>-js`: the neutral prefix and a content hash.
		$hash   = substr( sha1( $body ), 0, 8 );
		$handle = $prefix . '-' . $hash;
		if ( wp_script_is( $handle, 'enqueued' ) ) {
			return $handle;
		}

		$url = self::published_url( $key );
		if ( null !== $url ) {
			wp_enqueue_script(
				$handle,
				$url,
				array(),
				$hash,
				array(
					'in_footer' => true,
					'strategy'  => 'defer',
				)
			);
		} else {
			wp_register_script( $handle, false, array(), $hash, array( 'in_footer' => true ) );
			wp_enqueue_script( $handle );
			wp_add_inline_script( $handle, $body );
		}
		if ( '' !== $before ) {
			// An inline script placed BEFORE keeps the deferred loading strategy (only `after` drops it).
			wp_add_inline_script( $handle, $before, 'before' );
		}

		return $handle;
	}

	/**
	 * The published URL for a source, if a current copy exists.
	 *
	 * @param string $key A key of self::sources().
	 * @return string|null
	 */
	public static function published_url( string $key ): ?string {
		$manifest = self::manifest();
		// Version and prefix, not the key set (P2): a source registered after the last publish has
		// no copy yet and falls back alone; every other source keeps its static file.
		if ( PBSW_VERSION !== ( $manifest['version'] ?? null ) || Settings::prefix() !== ( $manifest['prefix'] ?? null ) ) {
			return null;
		}
		$file = $manifest['files'][ $key ] ?? null;
		if ( ! is_array( $file ) || ! is_string( $file['url'] ?? null ) ) {
			return null;
		}
		// ⛔ The copy must still be ON DISK: a cleanup plugin, a restore without uploads or a migration
		// deletes uploads/<prefix>-assets/, and the manifest went on naming the files, so every page
		// loaded its block stylesheets as 404s and showed unstyled until the next version (W3,
		// 2026-10-04). A missing copy is served inline now and the copies are published again.
		if ( null === Assets\Perf\Files::current_path( (string) $file['url'] ) ) {
			self::republish_later();
			return null;
		}
		// ⛔ Re-based on TODAY's uploads URL, never read back as stored: a site that moved after the
		// publish kept loading its stylesheets and view modules from the old host (Files::current_url()).
		return Assets\Perf\Files::current_url( $file['url'] );
	}

	/**
	 * Mark the published copies stale (once per request) so the next is_current() check publishes
	 * them again.
	 *
	 * @return void
	 */
	private static function republish_later(): void {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done             = true;
		$manifest         = self::manifest();
		$manifest['keys'] = '';
		update_option( self::MANIFEST_OPTION, $manifest, true );
	}

	/**
	 * Delete every file this plugin published. Used on uninstall.
	 *
	 * @return void
	 */
	public static function remove_all(): void {
		self::remove_stale( self::manifest(), array( 'files' => array() ) );
		delete_option( self::MANIFEST_OPTION );
	}

	/**
	 * A source's contents with the prefix token replaced.
	 *
	 * @param string $key    A key of self::sources().
	 * @param string $prefix The class prefix.
	 * @return string|null Null when the key or file is unknown.
	 */
	public static function source( string $key, string $prefix ): ?string {
		$sources = self::sources();
		if ( ! isset( $sources[ $key ] ) ) {
			return null;
		}
		$path = PBSW_DIR . $sources[ $key ];
		if ( ! is_readable( $path ) ) {
			return null;
		}
		$body = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a file inside this plugin, never a URL.

		return false === $body ? null : str_replace( self::PREFIX_TOKEN, $prefix, $body );
	}

	/**
	 * Every front-end source: this file's, the ones the performance layer registers with
	 * add_source() (per-block CSS, view modules), and any the premium layer adds for itself through
	 * `pbsw_asset_sources` (its files live in its own directory, which only the premium package
	 * ships). Only a plain relative `.css`/`.js` path inside the plugin is accepted, under a key of
	 * lowercase segments (`legacy.css`, `block/row.css`, `module/tabs.js`).
	 *
	 * ⛔ One method, on purpose: two branches each added a `sources()` here and git merged both
	 * without a conflict, which is a fatal "cannot redeclare" on every page load.
	 *
	 * @return array<string, string>
	 */
	public static function sources(): array {
		$sources = apply_filters( 'pbsw_asset_sources', array_merge( self::$added, self::SOURCES ) );
		$out     = array();
		foreach ( is_array( $sources ) ? $sources : array() as $key => $path ) {
			if (
				is_string( $key ) && is_string( $path )
				&& 1 === preg_match( '#^[a-z0-9-]+(?:/[a-z0-9-]+)*\.(?:css|js)$#', $key )
				&& 1 === preg_match( '#^[A-Za-z0-9_/-]+\.(?:css|js)$#', $path )
				&& ! str_contains( $path, '..' )
			) {
				$out[ $key ] = $path;
			}
		}

		return $out;
	}

	/**
	 * The stored manifest.
	 *
	 * @return array<string, mixed>
	 */
	private static function manifest(): array {
		$manifest = get_option( self::MANIFEST_OPTION, array() );

		return is_array( $manifest ) ? $manifest : array();
	}

	/**
	 * Was this manifest written for the running version and prefix?
	 *
	 * @param array<string, mixed> $manifest A manifest.
	 * @return bool
	 */
	private static function is_current( array $manifest ): bool {
		return PBSW_VERSION === ( $manifest['version'] ?? null )
			&& Settings::prefix() === ( $manifest['prefix'] ?? null )
			&& self::keys_signature() === ( $manifest['keys'] ?? null );
	}

	/**
	 * A signature of the source list, so adding a source republishes (P2).
	 *
	 * @return string
	 */
	private static function keys_signature(): string {
		$keys = array_keys( self::sources() );
		sort( $keys );

		return substr( sha1( implode( '|', $keys ) ), 0, 12 );
	}

	/**
	 * Delete files the old manifest names and the new one does not.
	 *
	 * @param array<string, mixed> $old The previous manifest.
	 * @param array<string, mixed> $next The new manifest.
	 * @return void
	 */
	private static function remove_stale( array $old, array $next ): void {
		$keep = array();
		foreach ( (array) ( $next['files'] ?? array() ) as $file ) {
			$keep[] = (string) ( $file['path'] ?? '' );
		}
		foreach ( (array) ( $old['files'] ?? array() ) as $file ) {
			$path = (string) ( $file['path'] ?? '' );
			if ( '' !== $path && ! in_array( $path, $keep, true ) && self::is_ours( $path ) ) {
				wp_delete_file( $path );
			}
		}
	}

	/**
	 * Is this a file this class could have written? A path read back from an option is data, so
	 * nothing is deleted unless it is a hashed asset inside an `-assets` folder under uploads.
	 *
	 * @param string $path A path from a manifest.
	 * @return bool
	 */
	private static function is_ours( string $path ): bool {
		$uploads = wp_upload_dir( null, false );
		$base    = realpath( (string) ( $uploads['basedir'] ?? '' ) );
		$real    = realpath( $path );
		if ( false === $base || false === $real || ! is_file( $real ) ) {
			return false;
		}

		return str_starts_with( $real, trailingslashit( $base ) )
			&& 1 === preg_match( '#/[a-z][a-z0-9]{0,7}-assets/[0-9a-f]{12}\.(?:css|js)$#', $real );
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
