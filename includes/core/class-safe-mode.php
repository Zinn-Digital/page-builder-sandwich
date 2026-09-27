<?php
/**
 * Studio safe mode (feature pbs-r17).
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns every other plugin off for ONE administrator's Studio requests, to find a conflict
 * without touching the live site.
 *
 * ⭐ HOW. A plugin cannot un-load plugins that loaded before it, so the work is done by a tiny
 * must-use plugin (includes/core/mu/pbsw-safe-mode.php, copied into wp-content/mu-plugins/ the
 * first time safe mode is turned on and removed on deactivation). It narrows the active plugin
 * list only for a request that carries this user's signed cookie AND is a Studio request — the
 * Studio screen, or a REST call Studio makes (header `X-PBSW-Studio: 1`). The front end, the
 * normal editor and every other user load every plugin exactly as before.
 *
 * ⛔⛔ WHO. Only a user who may `activate_plugins`. Switching plugins off is an administrator's act
 * (a plugin that RESTRICTS capabilities — a membership or role plugin — would otherwise be
 * switched off by the very user it restricts). The cookie is HMAC-signed with a per-site key and
 * bound to the browser's logged-in session, and verify() re-checks the real user on `init`: a
 * mismatch clears the cookie and stops the request before anything runs.
 */
final class Safe_Mode {

	/** Cookie name (read by the mu-plugin too). */
	public const COOKIE = 'pbsw_safe_mode';

	/** Option: {key, basename} (read by the mu-plugin too). */
	public const OPTION = 'pbsw_safe_mode';

	/** File name inside the mu-plugins directory. */
	public const MU_FILE = 'pbsw-safe-mode.php';

	/** How long one "on" lasts. */
	public const LIFETIME = 4 * HOUR_IN_SECONDS;

	/** REST namespace. */
	public const REST_NS = 'pbsw/v1';

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
		add_action( 'init', array( self::class, 'verify' ), 0 );
		if ( defined( 'PBSW_FILE' ) ) {
			register_deactivation_hook( PBSW_FILE, array( self::class, 'uninstall' ) );
		}
	}

	/**
	 * Whether safe mode is narrowing THIS request.
	 *
	 * @return bool
	 */
	public static function active(): bool {
		return defined( 'PBSW_SAFE_MODE_USER' ) && defined( 'PBSW_SAFE_MODE_DISABLED' );
	}

	/**
	 * Who may use it.
	 *
	 * @return bool
	 */
	public static function allowed(): bool {
		return current_user_can( 'activate_plugins' );
	}

	/**
	 * Once WordPress knows the user: a safe-mode request by anyone but the user the cookie was
	 * signed for, or by someone no longer allowed, is refused and the cookie cleared.
	 *
	 * @return void
	 */
	public static function verify(): void {
		if ( ! self::active() ) {
			return;
		}
		if ( get_current_user_id() === (int) PBSW_SAFE_MODE_USER && self::allowed() ) {
			return;
		}
		self::clear_cookie();
		wp_die( esc_html__( 'Safe mode was turned off because it no longer matches your login. Reload the page.', 'page-builder-sandwich' ), 403 );
	}

	/**
	 * State for the Studio app.
	 *
	 * @return array{allowed: bool, active: bool, disabled: list<string>, mustUse: bool}
	 */
	public static function state(): array {
		$disabled = array();
		if ( self::active() ) {
			$list = json_decode( (string) PBSW_SAFE_MODE_DISABLED, true );
			if ( is_array( $list ) ) {
				if ( ! function_exists( 'get_plugins' ) ) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}
				$all = get_plugins();
				foreach ( $list as $file ) {
					if ( is_string( $file ) ) {
						$disabled[] = isset( $all[ $file ]['Name'] ) ? (string) $all[ $file ]['Name'] : $file;
					}
				}
			}
		}
		return array(
			'allowed'  => self::allowed(),
			'active'   => self::active(),
			'disabled' => $disabled,
			'mustUse'  => is_readable( self::mu_path() ),
		);
	}

	/**
	 * REST routes.
	 *
	 * @return void
	 */
	public static function routes(): void {
		register_rest_route(
			self::REST_NS,
			'/studio/safe-mode',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'rest_set' ),
				'permission_callback' => static function () {
					return self::allowed()
						? true
						: new \WP_Error( 'rest_forbidden', __( 'Only an administrator can use safe mode.', 'page-builder-sandwich' ), array( 'status' => rest_authorization_required_code() ) );
				},
				'args'                => array(
					'enabled' => array(
						'type'     => 'boolean',
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * Turn safe mode on or off for the current user.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function rest_set( \WP_REST_Request $request ) {
		if ( ! $request->get_param( 'enabled' ) ) {
			self::clear_cookie();
			return new \WP_REST_Response( array( 'enabled' => false ) );
		}
		$installed = self::install();
		if ( is_wp_error( $installed ) ) {
			return $installed;
		}
		$session = isset( $_COOKIE[ LOGGED_IN_COOKIE ] ) && is_string( $_COOKIE[ LOGGED_IN_COOKIE ] ) ? wp_unslash( $_COOKIE[ LOGGED_IN_COOKIE ] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only hashed.
		if ( '' === $session ) {
			return new \WP_Error( 'pbsw_safe_mode_session', __( 'Safe mode needs a normal browser login.', 'page-builder-sandwich' ), array( 'status' => 400 ) );
		}
		$value = self::sign( get_current_user_id(), time() + self::LIFETIME, $session );
		if ( null === $value ) {
			return new \WP_Error( 'pbsw_safe_mode_key', __( 'Safe mode could not be set up.', 'page-builder-sandwich' ), array( 'status' => 500 ) );
		}
		self::set_cookie( $value, time() + self::LIFETIME );
		return new \WP_REST_Response( array( 'enabled' => true ) );
	}

	/**
	 * The signed cookie value, or null without a key.
	 *
	 * @param int    $user_id User.
	 * @param int    $expires Unix time.
	 * @param string $session The logged-in cookie value it is bound to.
	 * @return string|null
	 */
	public static function sign( int $user_id, int $expires, string $session ): ?string {
		$conf = get_option( self::OPTION );
		if ( ! is_array( $conf ) || empty( $conf['key'] ) || ! is_string( $conf['key'] ) ) {
			return null;
		}
		$data = $user_id . '|' . $expires;
		return $data . '|' . hash_hmac( 'sha256', $data . '|' . hash( 'sha256', $session ), $conf['key'] );
	}

	/**
	 * Path of the installed must-use file.
	 *
	 * @return string
	 */
	public static function mu_path(): string {
		return trailingslashit( WPMU_PLUGIN_DIR ) . self::MU_FILE;
	}

	/**
	 * Write the must-use file (when missing or different) and the key/basename option.
	 *
	 * @return true|\WP_Error
	 */
	public static function install() {
		$conf = get_option( self::OPTION );
		if ( ! is_array( $conf ) || empty( $conf['key'] ) ) {
			$conf = array( 'key' => bin2hex( random_bytes( 32 ) ) );
		}
		$conf['basename'] = plugin_basename( PBSW_FILE );
		update_option( self::OPTION, $conf, true );

		$source = (string) file_get_contents( __DIR__ . '/mu/' . self::MU_FILE ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local file shipped with the plugin.
		$target = self::mu_path();
		if ( is_readable( $target ) && hash_file( 'sha256', $target ) === hash( 'sha256', $source ) ) {
			return true;
		}
		$filesystem = self::filesystem();
		if ( null === $filesystem ) {
			return new \WP_Error( 'pbsw_safe_mode_fs', __( 'Safe mode needs write access to the wp-content/mu-plugins folder.', 'page-builder-sandwich' ), array( 'status' => 500 ) );
		}
		if ( ! wp_mkdir_p( WPMU_PLUGIN_DIR ) || ! $filesystem->put_contents( $target, $source, FS_CHMOD_FILE ) ) {
			return new \WP_Error( 'pbsw_safe_mode_write', __( 'Safe mode could not write to the wp-content/mu-plugins folder.', 'page-builder-sandwich' ), array( 'status' => 500 ) );
		}
		// ⛔ The very next request (the Studio reload) must run the NEW file. OPcache revalidates
		// by mtime only every few seconds, so without this a changed file is served stale — found
		// by the E2E mutation audit, where a mutated must-use file kept narrowing for ~2 s.
		if ( function_exists( 'opcache_invalidate' ) ) {
			opcache_invalidate( $target, true );
		}
		return true;
	}

	/**
	 * Remove the must-use file (only if it is ours) and the option. Runs on deactivation, which
	 * also precedes every uninstall.
	 *
	 * @return void
	 */
	public static function uninstall(): void {
		$target = self::mu_path();
		if ( is_readable( $target ) ) {
			$head = (string) file_get_contents( $target, false, null, 0, 400 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local file.
			if ( false !== strpos( $head, 'Studio safe mode' ) ) {
				wp_delete_file( $target );
			}
		}
		delete_option( self::OPTION );
	}

	/**
	 * Direct filesystem only: safe mode must never stop to ask for FTP credentials.
	 *
	 * @return \WP_Filesystem_Base|null
	 */
	private static function filesystem(): ?\WP_Filesystem_Base {
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( 'direct' !== get_filesystem_method( array(), WP_CONTENT_DIR ) || ! WP_Filesystem( false, WP_CONTENT_DIR ) ) {
			return null;
		}
		global $wp_filesystem;
		return $wp_filesystem instanceof \WP_Filesystem_Base ? $wp_filesystem : null;
	}

	/**
	 * Set the cookie for the whole site path (the Studio screen and REST both need it).
	 *
	 * @param string $value   Signed value.
	 * @param int    $expires Unix time.
	 * @return void
	 */
	private static function set_cookie( string $value, int $expires ): void {
		if ( headers_sent() ) {
			return;
		}
		setcookie(
			self::COOKIE,
			$value,
			array(
				'expires'  => $expires,
				'path'     => '/',
				'domain'   => (string) COOKIE_DOMAIN,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
	}

	/**
	 * Expire the cookie.
	 *
	 * @return void
	 */
	private static function clear_cookie(): void {
		self::set_cookie( '', time() - YEAR_IN_SECONDS );
		unset( $_COOKIE[ self::COOKIE ] );
	}
}
