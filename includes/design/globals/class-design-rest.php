<?php
/**
 * REST routes for the Site design screen: `pbs/v1/design/*`.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Design\Globals;

use ZinnDigital\PBS\Rest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The free routes (colours and fonts) and the capability checks every design route shares.
 *
 * ⛔ Every route names its capability: `edit_theme_options`, which is what the Site Editor asks
 * for, because these routes change what every page of the site looks like. Routes that accept
 * raw CSS additionally require `unfiltered_html` (see the premium layer).
 */
final class Design_Rest {

	/** Route base under the plugin's namespace. */
	public const BASE = '/design';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
	}

	/**
	 * Register the free routes.
	 *
	 * @return void
	 */
	public static function register_routes(): void {
		$color = array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => array(
				'slug'  => array(
					'type'     => 'string',
					'pattern'  => '^[a-z0-9][a-z0-9-]{0,47}$',
					'required' => true,
				),
				'name'  => array(
					'type'      => 'string',
					'maxLength' => 200,
				),
				'color' => array(
					'type'      => 'string',
					'maxLength' => 100,
					'required'  => true,
				),
			),
		);
		$font  = array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => array(
				'slug'       => array(
					'type'     => 'string',
					'pattern'  => '^[a-z0-9][a-z0-9-]{0,47}$',
					'required' => true,
				),
				'name'       => array(
					'type'      => 'string',
					'maxLength' => 200,
				),
				'fontFamily' => array(
					'type'      => 'string',
					'maxLength' => 300,
					'required'  => true,
				),
				'hasFiles'   => array( 'type' => 'boolean' ),
			),
		);
		$lists = static fn( array $item ): array => array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => array(
				'theme'  => array(
					'type'  => 'array',
					'items' => $item,
				),
				'custom' => array(
					'type'  => 'array',
					'items' => $item,
				),
			),
		);

		register_rest_route(
			Rest::NAMESPACE,
			self::BASE . '/globals',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( self::class, 'get_globals' ),
					'permission_callback' => array( self::class, 'can_design' ),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( self::class, 'update_globals' ),
					'permission_callback' => array( self::class, 'can_design' ),
					'args'                => array(
						'colors'      => $lists( $color ),
						'fonts'       => $lists( $font ),
						'bodyFont'    => array(
							'type'    => 'string',
							'pattern' => '^(?:|[a-z0-9][a-z0-9-]{0,47})$',
						),
						'headingFont' => array(
							'type'    => 'string',
							'pattern' => '^(?:|[a-z0-9][a-z0-9-]{0,47})$',
						),
					),
				),
			)
		);
	}

	/**
	 * May the current user change the site's design? The Site Editor's own capability.
	 *
	 * @return bool
	 */
	public static function can_design(): bool {
		return current_user_can( 'edit_theme_options' );
	}

	/**
	 * May the current user write raw CSS? Design rights AND `unfiltered_html`.
	 *
	 * @return bool
	 */
	public static function can_write_css(): bool {
		return self::can_design() && current_user_can( 'unfiltered_html' );
	}

	/**
	 * GET /design/globals.
	 *
	 * @return \WP_REST_Response
	 */
	public static function get_globals(): \WP_REST_Response {
		return new \WP_REST_Response( Theme_Globals::read() );
	}

	/**
	 * POST /design/globals.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function update_globals( \WP_REST_Request $request ) {
		$changes = array();
		foreach ( array( 'colors', 'fonts', 'bodyFont', 'headingFont' ) as $key ) {
			if ( null !== $request->get_param( $key ) ) {
				$changes[ $key ] = $request->get_param( $key );
			}
		}
		$saved = Theme_Globals::save( $changes );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		return new \WP_REST_Response( Theme_Globals::read() );
	}
}
