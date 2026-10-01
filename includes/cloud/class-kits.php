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
 * REST routes the kit screen calls (`pbs/v1/kits…`), each a server-side call to the Zinn API.
 *
 * ⛔ External service (WordPress.org guideline 7, declared in readme.txt "External services"): the
 * kit catalogue and each kit's pages come from `https://api.zinndigital.com/v1/pbs-library/kits`,
 * and ONLY when an administrator opens the Kits screen or presses Import. What is sent: the
 * request, and the plugin name, version and site address in the User-Agent header; a Pro site also
 * sends its library session token, which unlocks Pro kits. The browser never calls the API directly.
 *
 * ⭐ Free code, no locked code: the free plugin lists every kit, shows Pro kits greyed out with a
 * Go Pro link (the API marks them `locked`), and imports free kits. Whether a Pro kit unlocks is
 * decided by the API from the session token the premium code supplies through the
 * `pbsw_library_token` filter — the free plugin contains no Pro gate of its own.
 */
final class Kits {

	/** Transient holding the catalogue (per token state), refreshed hourly. */
	public const CACHE = 'pbsw_kit_catalogue';

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
				'permission_callback' => static fn(): bool => current_user_can( 'edit_pages' ) && current_user_can( 'publish_pages' ),
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
					'menu'    => array(
						'type'    => 'boolean',
						'default' => true,
					),
				),
			)
		);
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
		$data = self::catalogue( (bool) $request->get_param( 'refresh' ) );
		if ( isset( $data['failure'] ) ) {
			return self::failure( (array) $data['failure'] );
		}

		return new \WP_REST_Response( $data, 200 );
	}

	/**
	 * The catalogue in the admin's language, cached for an hour. The REST route and the
	 * `pbs/list-kits` ability both read it here.
	 *
	 * @param bool $refresh Skip the cache.
	 * @return array<string, mixed> The catalogue, or `failure` => the Engine::call() result.
	 */
	public static function catalogue( bool $refresh = false ): array {
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
		$data['upgradeUrl'] = (string) \ZinnDigital\PBS\Licensing::upgrade_url();

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
				'front'   => (bool) $request->get_param( 'front' ),
				'menu'    => (bool) $request->get_param( 'menu' ),
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
	 * Fetch a kit and import it. The REST route and the `pbs/apply-kit` ability both run it here;
	 * the library refuses a Pro kit to a site without a Pro session (`pro_required`).
	 *
	 * @param string               $slug    Kit slug.
	 * @param array<string, mixed> $options Importer::import() options.
	 * @return array<string, mixed> The import result, `failure` => the Engine::call() result, or
	 *                              `bad_kit` => true.
	 */
	public static function apply( string $slug, array $options ): array {
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
		if ( 'pro_required' === $code ) {
			$message = __( 'This kit comes with Page Builder Sandwich Pro.', 'page-builder-sandwich' );
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
