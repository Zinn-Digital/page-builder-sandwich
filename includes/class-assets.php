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
 * the filesystem is not directly writable, a stylesheet is printed inline (through inline_css(),
 * so the page is never unstyled) and a script is loaded from the plugin folder with the prefix in
 * its URL (plugin_url()). Plugin code is never printed into the page as an inline script
 * (WordPress.org review P0TDX382477HGN, 2026-10-13).
 */
final class Assets {

	/** Option holding what was published, for which version and prefix. */
	public const MANIFEST_OPTION = 'pbsw_published_assets';

	/** Front-end sources, keyed by the name callers use. */
	private const SOURCES = array(
		'front.css'       => 'assets/front.css',
		// Legacy (5.x) compatibility rules, enqueued only for posts that still need them (Assets\Legacy).
		'legacy.css'      => 'assets/legacy.css',
		// Its plain-content subset, for a post last saved under 4.x/5.x with no legacy markup (Assets\Legacy_Era).
		'legacy-base.css' => 'assets/legacy-base.css',
		// Legacy (5.x) front-end behaviour, enqueued only for posts that need it (Assets\Legacy_Script).
		'legacy.js'       => 'assets/legacy.js',
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
	 * A previous release's published sources (paths inside this plugin), written under the names
	 * that release gave them, so a page a cache saved while it was live still finds its scripts
	 * and stylesheets (NAV1: 6.36.22 renamed the navigation and header files and deleted the old
	 * ones). Added by the premium layer, which owned those blocks.
	 *
	 * @var array<int, string>
	 */
	private static array $legacy = array();

	/**
	 * Register a previous release's source for self::$legacy.
	 *
	 * @param string $relative Path relative to the plugin directory.
	 * @return void
	 */
	public static function add_legacy_source( string $relative ): void {
		self::$legacy[] = $relative;
	}

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
		// ⛔ NAV1, 2026-10-19: also on the front end. An update by WP-CLI or a fleet updater (V1's
		// own-plugin updater) never reaches admin_init, so until somebody opened wp-admin every
		// page served its view modules from the plugin folder (naming the plugin) and its block
		// CSS inline. One autoloaded option compared per request; the publish runs once.
		add_action( 'init', array( self::class, 'maybe_publish_front' ), 1 );
	}

	/**
	 * `init`: publish now when the copies are for another version, one request at a time.
	 *
	 * @return void
	 */
	public static function maybe_publish_front(): void {
		if ( is_admin() || self::is_current( self::manifest() ) ) {
			return;
		}
		// One publisher: a burst of first requests after an update must not all write the files.
		if ( get_transient( 'pbsw_assets_publishing' ) ) {
			return;
		}
		set_transient( 'pbsw_assets_publishing', 1, MINUTE_IN_SECONDS );
		self::publish();
		delete_transient( 'pbsw_assets_publishing' );
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
		$changed    = false;

		if ( null !== $filesystem && empty( $uploads['error'] ) ) {
			$folder = $prefix . '-assets';
			$dir    = trailingslashit( (string) $uploads['basedir'] ) . $folder;
			$url    = trailingslashit( (string) $uploads['baseurl'] ) . $folder;

			if ( wp_mkdir_p( $dir ) ) {
				$names = self::names( self::manifest(), $prefix, array_keys( self::sources() ) );
				foreach ( array_keys( self::sources() ) as $key ) {
					$body = self::source( $key, $prefix );
					if ( null === $body ) {
						continue;
					}
					$name  = $names[ $key ];
					$path  = $dir . '/' . $name;
					$wrote = self::write_file( $filesystem, $path, $body );
					if ( null !== $wrote ) {
						$changed                   = $changed || $wrote;
						$manifest['files'][ $key ] = array(
							'url'  => $url . '/' . $name . '?v=' . self::content_version( $body ),
							'path' => $path,
						);
					}
				}
				// A previous release's files under the names it gave them (its content hash), for the
				// pages a cache saved while that release was live (self::$legacy).
				foreach ( self::$legacy as $relative ) {
					$body = is_readable( PBSW_DIR . $relative ) ? (string) file_get_contents( PBSW_DIR . $relative ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a file inside this plugin.
					if ( '' === $body ) {
						continue;
					}
					$body = str_replace( self::PREFIX_TOKEN, $prefix, $body );
					$path = $dir . '/' . self::content_version( $body ) . '.' . pathinfo( $relative, PATHINFO_EXTENSION );
					// ⛔ A current asset that KEPT that name (self::names()) owns the file: the current
					// pages ask for it with today's ?v=, so it must hold today's body.
					if ( in_array( $path, array_column( $manifest['files'], 'path' ), true ) ) {
						continue;
					}
					$wrote = self::write_file( $filesystem, $path, $body );
					if ( null !== $wrote ) {
						$changed              = $changed || $wrote;
						$manifest['legacy'][] = $path;
					}
				}
			}
		}

		$old                  = self::manifest();
		$plan                 = self::stale_plan( $old, $manifest );
		$manifest['previous'] = $plan['previous'];
		self::delete_paths( $plan['delete'] );
		update_option( self::MANIFEST_OPTION, $manifest, true );
		// New or changed files: a cached page may name ones that changed (6.36.22 deleted the
		// navigation scripts cached pages still named), so every reachable page cache is emptied.
		if ( $changed || array() !== $plan['delete'] ) {
			Assets\Perf\Cache_Purge::assets_changed();
		}

		return $manifest;
	}

	/**
	 * Write a file unless it already holds this body; to a temporary name, then moved, so a
	 * visitor never reads half a file.
	 *
	 * @param \WP_Filesystem_Base $filesystem Filesystem.
	 * @param string              $path       Path.
	 * @param string              $body       Contents.
	 * @return bool|null True when written, false when it already held the body, null on failure.
	 */
	private static function write_file( $filesystem, string $path, string $body ): ?bool {
		if ( $filesystem->exists( $path ) && self::content_version( (string) $filesystem->get_contents( $path ) ) === self::content_version( $body ) ) {
			return false;
		}
		$tmp = $path . '.' . wp_generate_password( 6, false ) . '.tmp';
		if ( $filesystem->put_contents( $tmp, $body, FS_CHMOD_FILE ) && $filesystem->move( $tmp, $path, true ) ) {
			return true;
		}
		$filesystem->delete( $tmp );

		return null;
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
		if ( null !== $url && self::url_version( $url ) === self::content_version( $body ) ) {
			wp_enqueue_style( $handle, $url, array(), $hash );
			Assets\Perf\Files::allow_inline( $handle, $url );
			return;
		}

		wp_register_style( $handle, false, array(), $hash );
		wp_enqueue_style( $handle );
		wp_add_inline_style( $handle, self::inline_css( $body ) );
	}

	/**
	 * CSS made safe to print inside a `<style>` element: comments removed, then every tag
	 * stripped, so nothing in it (a stored value, a filter's output) can close the element and
	 * start markup. Every inline stylesheet the plugin prints goes through this, at the point it
	 * is printed (escaping late). Comments go first so an unclosed `<` in one cannot swallow the
	 * rules after it.
	 *
	 * @param string $css CSS.
	 * @return string
	 */
	public static function inline_css( string $css ): string {
		return wp_strip_all_tags( (string) preg_replace( '#/\*.*?\*/#s', '', $css ) );
	}

	/**
	 * A source's URL in the plugin folder, with the class prefix as the `pfx` query argument: the
	 * fallback when no published copy exists. The scripts read their prefix from their own URL when
	 * the token in them was not replaced (`PFX` at the top of each script).
	 *
	 * @param string $key A key of self::sources().
	 * @return string|null Null when the key is unknown.
	 */
	public static function plugin_url( string $key ): ?string {
		$sources = self::sources();
		if ( ! isset( $sources[ $key ] ) ) {
			return null;
		}

		return add_query_arg( 'pfx', Settings::prefix(), PBSW_URL . $sources[ $key ] );
	}

	/**
	 * Enqueue a front-end script under a neutral handle, deferred in the footer, from the
	 * published copy or inline.
	 *
	 * @param string               $key         A key of self::sources().
	 * @param string               $config_name Global the script reads its configuration from
	 *                                          (`window.<name>`), or '' for none.
	 * @param array<string, mixed> $config      That configuration, printed as JSON before the script.
	 * @return string|null The handle, or null when the source is unknown.
	 */
	public static function enqueue_script( string $key, string $config_name = '', array $config = array() ): ?string {
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

		// No published copy: the file in the plugin folder, never the body printed inline.
		$url = self::published_url( $key ) ?? self::plugin_url( $key );
		if ( null === $url ) {
			return null;
		}
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
		if ( 1 === preg_match( '/^[A-Za-z_][A-Za-z0-9_]*$/', $config_name ) ) {
			// Data only, encoded at the call (escaping late). An inline script placed BEFORE keeps the
			// deferred loading strategy (only `after` drops it).
			wp_add_inline_script( $handle, 'window.' . $config_name . ' = ' . wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP ) . ';', 'before' );
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
		$old = self::manifest();
		$all = array();
		foreach ( (array) ( $old['files'] ?? array() ) as $file ) {
			$all[] = (string) ( $file['path'] ?? '' );
		}
		self::delete_paths( array_merge( $all, array_map( 'strval', (array) ( $old['previous'] ?? array() ) ), array_map( 'strval', (array) ( $old['legacy'] ?? array() ) ) ) );
		delete_option( self::MANIFEST_OPTION );
	}

	/**
	 * A published file's name: STABLE across releases, neutral and different on every site —
	 * sha1( prefix | key ), 12 hex. Its content is versioned by `?v=` (self::content_version()).
	 *
	 * ⛔ NAV1, 2026-10-19: the name used to be the CONTENT hash, so every release that changed a
	 * view module gave it a new URL and deleted the old file at once. V1's own-plugin canary saw
	 * "2 stylesheet/script(s) gone" on tranzly.io and stopped PBS 6.36.22 fleet-wide, and any page
	 * a cache still served pointed at a deleted script (the phone Menu button did nothing).
	 *
	 * @param string $key    A key of self::sources().
	 * @param string $prefix The class prefix.
	 * @return string
	 */
	public static function file_name( string $key, string $prefix ): string {
		return substr( sha1( $prefix . '|' . $key ), 0, 12 ) . '.' . pathinfo( $key, PATHINFO_EXTENSION );
	}

	/**
	 * Each key's file name for this publish. Pure. A key KEEPS the name it had in the previous
	 * manifest (same prefix), even a 6.36.21/22 content-hash name, so updating never changes a
	 * URL's path: the first stable-name release changed nothing the canary compares. A new key, a
	 * name two keys shared, or a changed prefix gets self::file_name().
	 *
	 * @param array<string, mixed> $old    The previous manifest.
	 * @param string               $prefix The class prefix.
	 * @param array<int, string>   $keys   Keys to publish.
	 * @return array<string, string> Key => file name.
	 */
	public static function names( array $old, string $prefix, array $keys ): array {
		$had = array();
		if ( ( $old['prefix'] ?? null ) === $prefix ) {
			foreach ( (array) ( $old['files'] ?? array() ) as $key => $file ) {
				$name = basename( (string) wp_parse_url( (string) ( $file['url'] ?? '' ), PHP_URL_PATH ) );
				if ( 1 === preg_match( '/^[0-9a-f]{12}\.(?:css|js)$/', $name ) && pathinfo( $name, PATHINFO_EXTENSION ) === pathinfo( (string) $key, PATHINFO_EXTENSION ) ) {
					$had[ (string) $key ] = $name;
				}
			}
		}
		$out   = array();
		$taken = array();
		foreach ( $keys as $key ) {
			$name = $had[ $key ] ?? '';
			if ( '' === $name || isset( $taken[ $name ] ) ) {
				$name = self::file_name( $key, $prefix );
			}
			$taken[ $name ] = true;
			$out[ $key ]    = $name;
		}

		return $out;
	}

	/**
	 * A body's version: 12 hex of its sha1, the `?v=` of its URL.
	 *
	 * @param string $body Contents.
	 * @return string
	 */
	public static function content_version( string $body ): string {
		return substr( sha1( $body ), 0, 12 );
	}

	/**
	 * The `?v=` of a published URL ('' when it has none).
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public static function url_version( string $url ): string {
		parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );

		return is_string( $query['v'] ?? null ) && 1 === preg_match( '/^[0-9a-f]{12}$/', $query['v'] ) ? $query['v'] : '';
	}

	/**
	 * Which files of the previous publish to keep for ONE more release, and which to delete. Pure.
	 *
	 * A file the new publish no longer names is KEPT (listed as `previous`) until the next
	 * publish, so a page a cache saved before an update still finds every file it names — the
	 * 6.36.21/22 content-hash names included. Only the generation before that is deleted.
	 *
	 * @param array<string, mixed> $old  The previous manifest.
	 * @param array<string, mixed> $next The new manifest (its `files`).
	 * @return array{previous: array<int, string>, delete: array<int, string>}
	 */
	public static function stale_plan( array $old, array $next ): array {
		$keep = array();
		foreach ( (array) ( $next['files'] ?? array() ) as $file ) {
			$keep[ (string) ( $file['path'] ?? '' ) ] = true;
		}
		foreach ( (array) ( $next['legacy'] ?? array() ) as $path ) {
			$keep[ (string) $path ] = true;
		}
		$previous = array();
		$was      = array_merge( array_column( (array) ( $old['files'] ?? array() ), 'path' ), (array) ( $old['legacy'] ?? array() ) );
		foreach ( $was as $path ) {
			$path = (string) $path;
			if ( '' !== $path && ! isset( $keep[ $path ] ) ) {
				$previous[ $path ] = true;
			}
		}
		$delete = array();
		foreach ( (array) ( $old['previous'] ?? array() ) as $path ) {
			$path = (string) $path;
			if ( '' !== $path && ! isset( $keep[ $path ] ) && ! isset( $previous[ $path ] ) ) {
				$delete[] = $path;
			}
		}

		return array(
			'previous' => array_keys( $previous ),
			'delete'   => $delete,
		);
	}

	/**
	 * Delete published files (only ones this class could have written).
	 *
	 * @param array<int, string> $paths Paths from a manifest.
	 * @return void
	 */
	private static function delete_paths( array $paths ): void {
		foreach ( $paths as $path ) {
			if ( '' !== $path && self::is_ours( $path ) ) {
				wp_delete_file( $path );
			}
		}
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
