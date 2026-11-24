<?php
/**
 * The kit library: browse website kits from Zinn Digital® and import one (pbs-c2, pbs-r1, pbs-r2).
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Cloud;

use ZinnDigital\PBS\AdminKit\Engine;
use ZinnDigital\PBS\Rest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST routes the kit screen calls (`pbsw/v1/kits…`), each a server-side call to the Zinn API.
 *
 * ⛔ External service (WordPress.org guideline 7, declared in readme.txt "External services"): the
 * kit catalogue and each kit's pages come from `https://api.zinndigital.com/v1/pbs-library/kits`,
 * and ONLY after an administrator has switched the library on (off by default, `OPT_IN`). Until
 * then nothing is contacted. What is sent once it is on: the request, its language, and the plugin
 * name in the User-Agent header, never the site address or version; a Pro site also sends its
 * library session token, which unlocks Pro kits. The browser never calls the API directly.
 *
 * The list holds only kits this site can import: a kit the API marks `locked` is left out, so
 * nothing is shown greyed out. The API decides from the session token, if any, that another
 * plugin supplies through the `pbsw_library_token` filter.
 */
final class Kits {

	/** Transient holding the catalogue (per token state), refreshed hourly. */
	public const CACHE = 'pbsw_kit_catalogue';

	/** Option holding an administrator's explicit opt-in to the kit library. Absent means off. */
	public const OPT_IN = 'pbsw_kit_library_opt_in';

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
	}

	/**
	 * The routes.
	 *
	 * @return void
	 */
	public static function routes(): void {
		register_rest_route(
			Rest::NAMESPACE,
			'/kits',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'list' ),
				'permission_callback' => static fn(): bool => current_user_can( 'edit_pages' ) && current_user_can( 'publish_pages' ),
				'args'                => array(
					'refresh' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			)
		);
		register_rest_route(
			Rest::NAMESPACE,
			'/kits/import',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'import' ),
				'permission_callback' => array( self::class, 'import_permission' ),
				'args'                => array(
					'slug'    => array(
						'type'     => 'string',
						'required' => true,
						'pattern'  => '^[a-z0-9][a-z0-9-]{1,60}$',
					),
					'pages'   => array(
						'type'    => 'array',
						'items'   => array( 'type' => 'string' ),
						'default' => array(),
					),
					'publish' => array(
						'type'    => 'boolean',
						'default' => false,
					),
					'front'   => array(
						'type'    => 'boolean',
						'default' => false,
					),
					// No default: an omitted `menu` means "a menu if this user may create one".
					'menu'    => array(
						'type' => 'boolean',
					),
				),
			)
		);
		register_rest_route(
			Rest::NAMESPACE,
			'/kits/opt-in',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'set_opt_in' ),
				'permission_callback' => static fn(): bool => current_user_can( 'manage_options' ),
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
	 * Has an administrator switched the kit library on? Nothing is contacted until one has.
	 *
	 * @return bool
	 */
	public static function opted_in(): bool {
		return '1' === (string) get_option( self::OPT_IN, '' );
	}

	/**
	 * POST /kits/opt-in: switch the library on, or off again (also dropping this user's cached list).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function set_opt_in( \WP_REST_Request $request ): \WP_REST_Response {
		if ( rest_sanitize_boolean( $request->get_param( 'enabled' ) ) ) {
			update_option( self::OPT_IN, '1', false );
		} else {
			delete_option( self::OPT_IN );
			delete_transient( self::CACHE . '_' . substr( md5( get_user_locale() . '|' . self::token() ), 0, 16 ) );
		}

		return new \WP_REST_Response( array( 'optIn' => self::opted_in() ), 200 );
	}

	/**
	 * The refusal for any path that would contact the library before the opt-in.
	 *
	 * @return array<string, mixed> An Engine::call()-shaped failure.
	 */
	private static function not_opted_in(): array {
		return array(
			'ok'          => false,
			'status'      => 403,
			'body'        => array(),
			'code'        => 'kit_library_off',
			'message'     => __( 'The kit library is off. An administrator can switch it on under Page Builder Sandwich, Templates, Kits.', 'page-builder-sandwich' ),
			'retry_after' => 0,
		);
	}

	/**
	 * Who may set the site's front page from a kit: the person who may change site settings.
	 * `show_on_front` / `page_on_front` are site-wide options, so this is `manage_options`
	 * (the capability WordPress itself requires on Settings → Reading).
	 *
	 * @return bool
	 */
	public static function can_set_front(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Who may create a site navigation menu from a kit: `edit_theme_options`, the capability
	 * WordPress requires for Appearance → Menus and for `wp_navigation` posts.
	 *
	 * @return bool
	 */
	public static function can_add_menu(): bool {
		return current_user_can( 'edit_theme_options' );
	}

	/**
	 * Permission for POST /kits/import. Importing pages needs `edit_pages` + `publish_pages`;
	 * the site-wide side effects need their own capability on top — `front` needs
	 * `manage_options` and `menu` needs `edit_theme_options`. An editor can still import the
	 * pages; asking for a site-wide change they may not make is refused with a 403, never
	 * silently applied or silently dropped.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool|\WP_Error
	 */
	public static function import_permission( \WP_REST_Request $request ) {
		if ( ! self::can_import() ) {
			return false;
		}
		if ( $request->get_param( 'front' ) && ! self::can_set_front() ) {
			return new \WP_Error(
				'pbsw_kit_front_forbidden',
				__( 'Only an administrator can change the site’s front page. Import the pages without that option, or ask an administrator.', 'page-builder-sandwich' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}
		if ( $request->get_param( 'menu' ) && ! self::can_add_menu() ) {
			return new \WP_Error(
				'pbsw_kit_menu_forbidden',
				__( 'Only a user who can edit menus can create a site menu. Import the pages without that option, or ask an administrator.', 'page-builder-sandwich' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Who may import a kit: a person who can create and publish pages.
	 *
	 * @return bool
	 */
	public static function can_import(): bool {
		return current_user_can( 'edit_pages' ) && current_user_can( 'publish_pages' );
	}

	/**
	 * The library session token the premium code holds, '' on a free site.
	 *
	 * @return string
	 */
	public static function token(): string {
		/**
		 * Filters the bearer token sent with kit requests (the premium cloud library supplies it).
		 *
		 * @param string $token '' when this site has no library session.
		 */
		return (string) apply_filters( 'pbsw_library_token', '' );
	}

	/**
	 * GET /kits — the catalogue.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function list( \WP_REST_Request $request ): \WP_REST_Response {
		if ( ! self::opted_in() ) {
			// The screen explains the service and offers the switch; no request has been made.
			return new \WP_REST_Response(
				array(
					'optIn'    => false,
					'canOptIn' => current_user_can( 'manage_options' ),
					'kits'     => array(),
				),
				200
			);
		}
		$data = self::catalogue( (bool) $request->get_param( 'refresh' ) );
		if ( isset( $data['failure'] ) ) {
			return self::failure( (array) $data['failure'] );
		}
		// Which site-wide import options this user may choose; the screen hides the others.
		$data['optIn']    = true;
		$data['canOptIn'] = current_user_can( 'manage_options' );
		$data['can']      = array(
			'front' => self::can_set_front(),
			'menu'  => self::can_add_menu(),
		);

		return new \WP_REST_Response( $data, 200 );
	}

	/**
	 * The catalogue in the admin's language, cached for an hour. The REST route and the
	 * `pbsw/list-kits` ability both read it here.
	 *
	 * @param bool $refresh Skip the cache.
	 * @return array<string, mixed> The catalogue, or `failure` => the Engine::call() result.
	 */
	public static function catalogue( bool $refresh = false ): array {
		if ( ! self::opted_in() ) {
			return array( 'failure' => self::not_opted_in() );
		}
		$token  = self::token();
		$locale = get_user_locale();
		$key    = self::CACHE . '_' . substr( md5( $locale . '|' . $token ), 0, 16 );
		$data   = $refresh ? false : get_transient( $key );
		if ( ! is_array( $data ) ) {
			// The catalogue in the admin's language; the engine falls back to English per string.
			$result = Engine::call( 'GET', '/v1/pbs-library/kits?locale=' . rawurlencode( $locale ), null, $token );
			if ( ! $result['ok'] ) {
				return array( 'failure' => $result );
			}
			$data = (array) $result['body'];
			set_transient( $key, $data, HOUR_IN_SECONDS );
		}
		if ( isset( $data['kits'] ) && is_array( $data['kits'] ) ) {
			$data['kits'] = array_values(
				array_filter(
					$data['kits'],
					static fn( $kit ): bool => is_array( $kit ) && empty( $kit['locked'] )
				)
			);
		}

		return $data;
	}

	/**
	 * POST /kits/import — fetch the kit and import it.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function import( \WP_REST_Request $request ): \WP_REST_Response {
		$done = self::apply(
			(string) $request->get_param( 'slug' ),
			array(
				'pages'   => (array) $request->get_param( 'pages' ),
				'publish' => (bool) $request->get_param( 'publish' ),
				// Checked in import_permission(); re-checked here so no other caller of this
				// callback can reach a site-wide change without the capability.
				'front'   => (bool) $request->get_param( 'front' ) && self::can_set_front(),
				'menu'    => ( null === $request->get_param( 'menu' ) || (bool) $request->get_param( 'menu' ) ) && self::can_add_menu(),
			)
		);
		if ( isset( $done['failure'] ) ) {
			return self::failure( (array) $done['failure'] );
		}
		if ( isset( $done['bad_kit'] ) ) {
			return new \WP_REST_Response(
				array(
					'code'    => 'bad_kit',
					'message' => __( 'The kit could not be read. Try again later.', 'page-builder-sandwich' ),
				),
				502
			);
		}

		return new \WP_REST_Response( $done, 200 );
	}

	/**
	 * Fetch a kit and import it. The REST route and the `pbsw/apply-kit` ability both run it here;
	 * the library refuses a Pro kit to a site without a Pro session (`pro_required`).
	 *
	 * @param string               $slug    Kit slug.
	 * @param array<string, mixed> $options Importer::import() options.
	 * @return array<string, mixed> The import result, `failure` => the Engine::call() result, or
	 *                              `bad_kit` => true.
	 */
	public static function apply( string $slug, array $options ): array {
		if ( ! self::opted_in() ) {
			return array( 'failure' => self::not_opted_in() );
		}
		// The pages in the SITE's language (what visitors read), not the admin's.
		$result = Engine::call( 'GET', '/v1/pbs-library/kits/' . rawurlencode( $slug ) . '?locale=' . rawurlencode( get_locale() ), null, self::token() );
		if ( ! $result['ok'] ) {
			return array( 'failure' => $result );
		}
		$kit = (array) $result['body'];
		if ( 'pbs-kit/1' !== ( $kit['format'] ?? '' ) || ( $kit['slug'] ?? '' ) !== $slug ) {
			return array( 'bad_kit' => true );
		}

		return Importer::import( $kit, $options );
	}

	/**
	 * An engine failure as a REST response the screen can explain.
	 *
	 * @param array<string, mixed> $result Engine::call() result.
	 * @return \WP_REST_Response
	 */
	public static function failure( array $result ): \WP_REST_Response {
		$status = (int) ( $result['status'] ?? 0 );
		$code   = (string) ( $result['code'] ?? 'unreachable' );
		if ( 'kit_library_off' === $code ) {
			$message = (string) $result['message'];
		} elseif ( 'pro_required' === $code ) {
			$message = __( 'This kit is not available on this site.', 'page-builder-sandwich' );
		} elseif ( 0 === $status || $status >= 500 ) {
			$message = __( 'The kit library could not be reached. Check this site can make outgoing connections, then try again.', 'page-builder-sandwich' );
		} else {
			$message = (string) ( $result['message'] ?? __( 'The kit library refused the request.', 'page-builder-sandwich' ) );
		}

		return new \WP_REST_Response(
			array(
				'code'    => $code,
				'message' => $message,
			),
			$status >= 400 && $status < 600 ? $status : 502
		);
	}
}
