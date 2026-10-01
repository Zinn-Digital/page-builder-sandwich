<?php
/**
 * REST routes for the settings screen.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * `pbs/v1/settings` — read and save the plugin settings.
 *
 * ⛔ Every route carries a real `permission_callback` (`manage_options`). The REST cookie nonce
 * proves the request came from this site's admin screen; it does not prove the person may change
 * settings, and a nonce alone is not authorisation.
 */
final class Rest {

	/** REST namespace. */
	public const NAMESPACE = 'pbs/v1';

	/**
	 * Hook route registration.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
	}

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/settings',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( self::class, 'get_settings' ),
					'permission_callback' => array( self::class, 'can_manage' ),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( self::class, 'update_settings' ),
					'permission_callback' => array( self::class, 'can_manage' ),
					'args'                => array(
						'prefix' => array(
							'type'     => 'string',
							'required' => false,
						),
						'mcp'    => array(
							'type'     => 'boolean',
							'required' => false,
						),
					),
				),
			)
		);
	}

	/**
	 * Only administrators may read or change settings.
	 *
	 * @return bool
	 */
	public static function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * GET handler.
	 *
	 * @return \WP_REST_Response
	 */
	public static function get_settings(): \WP_REST_Response {
		return new \WP_REST_Response( self::payload() );
	}

	/**
	 * POST/PUT handler.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function update_settings( \WP_REST_Request $request ) {
		$input = array();
		if ( null !== $request->get_param( 'prefix' ) ) {
			$input['prefix'] = (string) $request->get_param( 'prefix' );
		}
		if ( null !== $request->get_param( 'mcp' ) ) {
			$input['mcp'] = (bool) $request->get_param( 'mcp' );
		}

		$saved = Settings::save( $input );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		Assets::publish();

		return new \WP_REST_Response( self::payload() );
	}

	/**
	 * The settings as the screen reads them.
	 *
	 * @return array<string, mixed>
	 */
	private static function payload(): array {
		return array(
			'prefix' => Settings::get()['prefix'],
			'beta'   => Licensing::beta(),
			// The switch as saved, and what is live on THIS request (the kit booted before it).
			'mcp'    => array( 'saved' => Settings::get()['mcp'] ) + \ZinnDigital\PBS\McpKit\Server::describe(),
		);
	}
}
