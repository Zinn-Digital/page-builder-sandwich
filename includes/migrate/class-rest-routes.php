<?php
/**
 * REST routes for the legacy-content migration.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Migrate;

use ZinnDigital\PBS\Core\Access;
use ZinnDigital\PBS\Rest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * `pbs/v1/migration`, `pbs/v1/migration/undo`, `pbs/v1/posts/<id>/convert|undo`.
 *
 * ⛔ pbs-f7: every route has a real permission_callback. The site-wide routes need
 * `manage_options`; the per-post routes need `edit_post` on THAT post id — a person who may edit
 * some posts must not be able to convert or roll back someone else's.
 */
final class Rest_Routes {

	/**
	 * Hook route registration.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
		add_filter( 'pbsw_admin_data', array( self::class, 'admin_data' ) );
	}

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public static function register_routes(): void {
		register_rest_route(
			Rest::NAMESPACE,
			'/migration',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( self::class, 'get_status' ),
					'permission_callback' => array( self::class, 'can_manage' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( self::class, 'start' ),
					'permission_callback' => array( self::class, 'can_manage' ),
				),
			)
		);
		register_rest_route(
			Rest::NAMESPACE,
			'/migration/undo',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'undo_all' ),
				'permission_callback' => array( self::class, 'can_manage' ),
			)
		);
		foreach ( array( 'convert', 'undo' ) as $verb ) {
			register_rest_route(
				Rest::NAMESPACE,
				'/posts/(?P<id>\d+)/' . $verb,
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( self::class, 'post_' . $verb ),
					'permission_callback' => array( Access::class, 'rest_edit_post_permission' ),
					'args'                => array(
						'id' => array(
							'type'     => 'integer',
							'required' => true,
							'minimum'  => 1,
						),
					),
				)
			);
		}
	}

	/**
	 * Site-wide routes: administrators only (Core\Access, shared with every PBS route).
	 *
	 * @return bool|\WP_Error
	 */
	public static function can_manage() {
		return Access::rest_manage_permission();
	}

	/**
	 * GET /migration.
	 *
	 * @return \WP_REST_Response
	 */
	public static function get_status(): \WP_REST_Response {
		return new \WP_REST_Response( self::payload() );
	}

	/**
	 * POST /migration — start (or restart) a conversion run.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function start() {
		return null === Batch::start( Run::CONVERT, 'manual' ) ? self::busy() : new \WP_REST_Response( self::payload(), 202 );
	}

	/**
	 * POST /migration/undo — restore every post that has a backup, in the background.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function undo_all() {
		return null === Batch::undo_all() ? self::busy() : new \WP_REST_Response( self::payload(), 202 );
	}

	/**
	 * POST /posts/<id>/convert.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function post_convert( \WP_REST_Request $request ): \WP_REST_Response {
		$id = (int) $request->get_param( 'id' );
		return new \WP_REST_Response( array( 'id' => $id ) + Batch::convert_post( $id, 'manual' ) );
	}

	/**
	 * POST /posts/<id>/undo.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function post_undo( \WP_REST_Request $request ): \WP_REST_Response {
		$id = (int) $request->get_param( 'id' );
		return new \WP_REST_Response( array( 'id' => $id ) + Batch::undo_post( $id ) );
	}

	/**
	 * Add the migration route to the admin screen's boot data.
	 *
	 * @param array<string, mixed> $data Boot data.
	 * @return array<string, mixed>
	 */
	public static function admin_data( $data ): array {
		$data                  = (array) $data;
		$data['migrationPath'] = '/' . Rest::NAMESPACE . '/migration';
		return $data;
	}

	/**
	 * The status as the admin screen reads it; failures carry a title and an edit link.
	 *
	 * @return array<string, mixed>
	 */
	public static function payload(): array {
		$status   = Batch::status();
		$state    = $status['state'];
		$failures = array();
		foreach ( (array) ( $state['failures'] ?? array() ) as $failure ) {
			$id         = (int) ( $failure['id'] ?? 0 );
			$failures[] = array(
				'id'      => $id,
				'message' => (string) ( $failure['message'] ?? '' ),
				'title'   => $id > 0 ? get_the_title( $id ) : '',
				'editUrl' => $id > 0 ? (string) get_edit_post_link( $id, 'raw' ) : '',
			);
		}
		return array(
			'status'     => (string) ( $state['status'] ?? 'none' ),
			'mode'       => (string) ( $state['mode'] ?? '' ),
			'version'    => (string) ( $state['version'] ?? '' ),
			'total'      => (int) ( $state['total'] ?? 0 ),
			'processed'  => (int) ( $state['processed'] ?? 0 ),
			'converted'  => (int) ( $state['converted'] ?? 0 ),
			'skipped'    => (int) ( $state['skipped'] ?? 0 ),
			'restored'   => (int) ( $state['restored'] ?? 0 ),
			'failed'     => (int) ( $state['failed'] ?? 0 ),
			'startedAt'  => (int) ( $state['started_at'] ?? 0 ),
			'finishedAt' => (int) ( $state['finished_at'] ?? 0 ),
			'pending'    => (bool) $status['pending'],
			'remaining'  => (int) $status['remaining'],
			'backups'    => (int) $status['backups'],
			'failing'    => (int) $status['failing'],
			'failures'   => array_reverse( $failures ),
		);
	}

	/**
	 * The lock is held by a running step or start.
	 *
	 * @return \WP_Error
	 */
	private static function busy(): \WP_Error {
		return new \WP_Error( 'pbsw_migration_busy', __( 'A migration step is running right now. Try again in a minute.', 'page-builder-sandwich' ), array( 'status' => 409 ) );
	}
}
