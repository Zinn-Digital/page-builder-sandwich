<?php
/**
 * REST routes and WP-CLI commands for import and export (pbs-g5).
 *
 *   GET  pbs/v1/transfer/items   what this site can export
 *   POST pbs/v1/transfer/export  { ids[], settings } → the package
 *   POST pbs/v1/transfer/import  { package, mode, settings, media, dryRun } → the report
 *
 *   wp pbs export [--types=<a,b>] [--no-settings] [--file=<path>]
 *   wp pbs import <file> [--mode=skip|replace|duplicate] [--no-settings] [--media] [--dry-run]
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Workflow;

use ZinnDigital\PBS\Rest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Transfer routes and commands.
 */
final class Transfer_Routes {

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
		if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( '\WP_CLI' ) ) {
			\WP_CLI::add_command( 'pbs export', array( self::class, 'cli_export' ) );
			\WP_CLI::add_command( 'pbs import', array( self::class, 'cli_import' ) );
		}
	}

	/**
	 * Register the routes. Import and export move templates and settings, so they need an
	 * administrator (`manage_options`); each item is also checked on its own.
	 *
	 * @return void
	 */
	public static function routes(): void {
		$can = static fn(): bool => current_user_can( 'manage_options' );

		register_rest_route(
			Rest::NAMESPACE,
			'/transfer/items',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => static fn() => new \WP_REST_Response( Transfer::exportable() ),
				'permission_callback' => $can,
			)
		);
		register_rest_route(
			Rest::NAMESPACE,
			'/transfer/export',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'export' ),
				'permission_callback' => $can,
				'args'                => array(
					'ids'      => array(
						'type'     => 'array',
						'items'    => array( 'type' => 'integer' ),
						'default'  => array(),
						'required' => false,
					),
					'settings' => array(
						'type'    => 'boolean',
						'default' => true,
					),
				),
			)
		);
		register_rest_route(
			Rest::NAMESPACE,
			'/transfer/import',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'import' ),
				'permission_callback' => $can,
				'args'                => array(
					'package'  => array(
						'type'     => 'object',
						'required' => true,
					),
					'mode'     => array(
						'type'    => 'string',
						'enum'    => Transfer::MODES,
						'default' => 'skip',
					),
					'settings' => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'media'    => array(
						'type'    => 'boolean',
						'default' => false,
					),
					'dryRun'   => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			)
		);
	}

	/**
	 * Export.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function export( \WP_REST_Request $request ): \WP_REST_Response {
		return new \WP_REST_Response( Transfer::export( (array) $request['ids'], (bool) $request['settings'] ) );
	}

	/**
	 * Import.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function import( \WP_REST_Request $request ) {
		// The raw body, not the sanitised param: the checksum is over the file as written.
		$json    = $request->get_json_params();
		$package = is_array( $json['package'] ?? null ) ? $json['package'] : null;
		if ( null === $package ) {
			return new \WP_Error( 'pbsw_transfer_format', __( 'This is not an export file from this plugin.', 'page-builder-sandwich' ), array( 'status' => 400 ) );
		}
		$result = Transfer::import( $package, (string) $request['mode'], (bool) $request['settings'], (bool) $request['media'], (bool) $request['dryRun'] );

		return is_wp_error( $result ) ? $result : new \WP_REST_Response( $result );
	}

	/**
	 * Export templates, patterns and settings to a file.
	 *
	 * ## OPTIONS
	 *
	 * [--types=<types>]
	 * : Comma-separated post types to include (default: all that can be exported).
	 *
	 * [--no-settings]
	 * : Leave the plugin's settings out.
	 *
	 * [--file=<path>]
	 * : Write here instead of printing.
	 *
	 * @param array<int, string>    $args  Positional arguments.
	 * @param array<string, string> $assoc Named arguments.
	 * @return void
	 */
	public static function cli_export( array $args, array $assoc ): void {
		unset( $args );
		self::cli_admin();
		$types = isset( $assoc['types'] ) ? array_filter( array_map( 'trim', explode( ',', (string) $assoc['types'] ) ) ) : array();
		$ids   = array();
		foreach ( Transfer::exportable() as $item ) {
			if ( ! $types || in_array( $item['type'], $types, true ) ) {
				$ids[] = $item['id'];
			}
		}
		$settings = ! isset( $assoc['settings'] ) || 'false' !== (string) $assoc['settings'];
		$json     = (string) wp_json_encode( Transfer::export( $ids, $settings ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( isset( $assoc['file'] ) ) {
			if ( false === file_put_contents( (string) $assoc['file'], $json ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- WP-CLI writes where the operator asked.
				\WP_CLI::error( 'Could not write ' . $assoc['file'] );
			}
			\WP_CLI::success( sprintf( 'Exported %d items to %s', count( $ids ), $assoc['file'] ) );
			return;
		}
		\WP_CLI::line( $json );
	}

	/**
	 * Import an export file.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : The export file.
	 *
	 * [--mode=<mode>]
	 * : What to do with an item that already exists here: skip, replace or duplicate.
	 * ---
	 * default: skip
	 * ---
	 *
	 * [--no-settings]
	 * : Do not apply the file's settings.
	 *
	 * [--media]
	 * : Copy images from the source site into this site's media library.
	 *
	 * [--dry-run]
	 * : Report what would happen without changing anything.
	 *
	 * @param array<int, string>    $args  Positional arguments.
	 * @param array<string, string> $assoc Named arguments.
	 * @return void
	 */
	public static function cli_import( array $args, array $assoc ): void {
		self::cli_admin();
		$raw = file_get_contents( (string) $args[0] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local file the operator named.
		if ( false === $raw ) {
			\WP_CLI::error( 'Could not read ' . $args[0] );
		}
		$package = json_decode( (string) $raw, true );
		if ( ! is_array( $package ) ) {
			\WP_CLI::error( 'The file is not valid JSON.' );
		}
		$result = Transfer::import(
			$package,
			(string) ( $assoc['mode'] ?? 'skip' ),
			! isset( $assoc['settings'] ) || 'false' !== (string) $assoc['settings'],
			isset( $assoc['media'] ),
			isset( $assoc['dry-run'] )
		);
		if ( is_wp_error( $result ) ) {
			\WP_CLI::error( $result->get_error_message() );
		}
		foreach ( $result['errors'] as $error ) {
			\WP_CLI::warning( $error );
		}
		\WP_CLI::success(
			sprintf(
				'%screated %d, replaced %d, skipped %d, settings %d, images %d',
				isset( $assoc['dry-run'] ) ? '(dry run) ' : '',
				count( $result['created'] ),
				count( $result['replaced'] ),
				count( $result['skipped'] ),
				count( $result['settings'] ),
				$result['media']
			)
		);
		if ( $result['errors'] ) {
			\WP_CLI::halt( 1 );
		}
	}

	/**
	 * WP-CLI runs as no user; import and export check capabilities, so act as the first
	 * administrator unless `--user` was given.
	 *
	 * @return void
	 */
	private static function cli_admin(): void {
		if ( get_current_user_id() ) {
			return;
		}
		$admins = get_users(
			array(
				'role'    => 'administrator',
				'number'  => 1,
				'orderby' => 'ID',
				'fields'  => 'ID',
			)
		);
		if ( $admins ) {
			wp_set_current_user( (int) $admins[0] );
		}
	}
}
