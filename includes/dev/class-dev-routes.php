<?php
/**
 * The developer platform (P16): the extensions list over REST and MCP.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Dev;

use ZinnDigital\PBS\Mcp\Abilities;
use ZinnDigital\PBS\McpKit\Ability;
use ZinnDigital\PBS\Rest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * `GET pbs/v1/extensions` and the `pbs/list-extensions` ability: what add-ons registered.
 */
final class Dev_Routes {

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
		add_action( 'pbsw_mcp_abilities', array( self::class, 'abilities' ) );
	}

	/**
	 * REST route.
	 *
	 * @return void
	 */
	public static function routes(): void {
		register_rest_route(
			Rest::NAMESPACE,
			'/extensions',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => static fn() => new \WP_REST_Response( Registry::all() ),
				'permission_callback' => static fn(): bool => current_user_can( 'edit_posts' ),
			)
		);
	}

	/**
	 * The ability.
	 *
	 * @return void
	 */
	public static function abilities(): void {
		Ability::register(
			'pbs/list-extensions',
			array(
				'edition'             => 'free',
				'capability'          => 'edit_posts',
				'label'               => __( 'List the builder\'s add-on extensions', 'page-builder-sandwich' ),
				'description'         => __( 'Lists what other plugins have added to Page Builder Sandwich through its developer API: blocks, dynamic data sources, display conditions and form actions, each with its name, label and plugin.', 'page-builder-sandwich' ),
				'category'            => Abilities::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(),
					'additionalProperties' => false,
				),
				'output_schema'       => array( 'type' => 'object' ),
				'annotations'         => array( 'readonly' => true ),
				'execute_callback'    => static fn() => Registry::all(),
				'permission_callback' => static fn(): bool => current_user_can( 'edit_posts' ),
			)
		);
	}
}
