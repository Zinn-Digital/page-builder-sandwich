<?php
/**
 * REST routes of the design system (pbs-p4).
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Design;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * `pbs/v1/design/breakpoints` — read and save the breakpoints (edit_theme_options).
 */
final class Rest {

	/** Namespace. */
	public const NAMESPACE = 'pbs/v1';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
	}

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public static function routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/design/breakpoints',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( self::class, 'get_breakpoints' ),
					'permission_callback' => array( self::class, 'can_manage' ),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( self::class, 'save_breakpoints' ),
					'permission_callback' => array( self::class, 'can_manage' ),
					'args'                => array(
						'tablet' => array( 'type' => 'integer' ),
						'mobile' => array( 'type' => 'integer' ),
						'custom' => array( 'type' => 'array' ),
					),
				),
			)
		);
	}

	/**
	 * Who may change the site design.
	 *
	 * @return bool
	 */
	public static function can_manage(): bool {
		return current_user_can( 'edit_theme_options' );
	}

	/**
	 * GET.
	 *
	 * @return \WP_REST_Response
	 */
	public static function get_breakpoints(): \WP_REST_Response {
		return new \WP_REST_Response( self::payload() );
	}

	/**
	 * POST/PUT/PATCH.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function save_breakpoints( \WP_REST_Request $request ) {
		$current = Breakpoints::get();
		$input   = array(
			'tablet' => $request->get_param( 'tablet' ) ?? $current['tablet'],
			'mobile' => $request->get_param( 'mobile' ) ?? $current['mobile'],
			'custom' => $request->get_param( 'custom' ) ?? $current['custom'],
		);
		$saved   = Breakpoints::save( $input );
		if ( $saved instanceof \WP_Error ) {
			return $saved;
		}

		return new \WP_REST_Response( self::payload() );
	}

	/**
	 * The response body.
	 *
	 * @return array<string, mixed>
	 */
	private static function payload(): array {
		return array_merge(
			Breakpoints::get(),
			array(
				'customAllowed' => Breakpoints::custom_allowed(),
				'defaults'      => Breakpoints::DEFAULTS,
			)
		);
	}
}
