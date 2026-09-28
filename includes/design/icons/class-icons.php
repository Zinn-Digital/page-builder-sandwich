<?php
/**
 * Icon library (pbs-b2, pbs-r12): the editor data for the IconPicker and the custom SVG uploads.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Design\Icons;

use ZinnDigital\PBS\Core\Blocks;
use ZinnDigital\PBS\Core\Svg;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Where the picker finds the bundled sets, and the author's own uploaded SVG icons.
 *
 * ⭐ The bundled sets are editor-only JSON files (assets/icons/<set>.json, built by
 * wp/bin/pbs-icons-build.php) that the picker fetches when a tab is opened. The front end loads
 * none of them and no icon font: a picked icon is stored in the block as sanitised inline SVG.
 *
 * ⭐⭐ Custom uploads are NOT files. An uploaded SVG is read by the picker, sent as text to
 * `POST pbs/v1/icons/uploads`, sanitised HERE by Svg::sanitize() and kept as post meta on a
 * private `pbsw_icon` post. So:
 * - the allowed upload file types are never widened, not even for one request — there is no
 *   file upload to allow;
 * - nothing an author sends is ever written to a web-served path, so a sanitiser bypass could
 *   not become a stored-XSS file on the site's own origin (legacy finding §3 "SVG upload");
 * - the bytes kept are the sanitised ones, so a reader of the list (any user who can edit posts)
 *   gets clean markup even from an author without `unfiltered_html`.
 */
final class Icons {

	/** The private post type that keeps uploaded icons. */
	public const POST_TYPE = 'pbsw_icon';

	/** Post meta holding the sanitised SVG. */
	public const META = '_pbsw_svg';

	/** REST namespace. */
	public const REST_NS = 'pbs/v1';

	/** Largest SVG accepted (bytes), the sanitiser's own ceiling. */
	public const MAX_BYTES = 512000;

	/** The bundled sets, in picker order. */
	public const SETS = array( 'fa', 'lucide', 'material', 'phosphor' );

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'init', array( self::class, 'register_post_type' ) );
		add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
		add_action( 'enqueue_block_editor_assets', array( self::class, 'editor_data' ) );
	}

	/**
	 * The storage for uploaded icons: private, no UI, no front-end URL.
	 *
	 * @return void
	 */
	public static function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'label'               => __( 'Uploaded icons', 'page-builder-sandwich' ),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => false,
				'show_in_rest'        => false,
				'rewrite'             => false,
				'query_var'           => false,
				'can_export'          => true,
				'map_meta_cap'        => true,
				'capability_type'     => 'post',
				'supports'            => array( 'title', 'author' ),
			)
		);
	}

	/**
	 * Print the picker's data (set file URL) where the core editor script is.
	 *
	 * @return void
	 */
	public static function editor_data(): void {
		// The picker's own styles (webpack emits the core bundle's SCSS as build/core.css).
		if ( is_readable( PBSW_DIR . 'build/core.css' ) ) {
			wp_enqueue_style( 'pbsw-core-editor', PBSW_URL . 'build/core.css', array( 'wp-components' ), PBSW_VERSION );
			wp_style_add_data( 'pbsw-core-editor', 'rtl', 'replace' );
		}
		wp_add_inline_script(
			Blocks::EDITOR_HANDLE,
			'window.pbswIcons = ' . wp_json_encode(
				array(
					'base'      => PBSW_URL . 'assets/icons/',
					'sets'      => self::SETS,
					'canUpload' => current_user_can( 'upload_files' ),
				)
			) . ';',
			'before'
		);
	}

	/**
	 * REST routes: list / add / delete uploaded icons.
	 *
	 * @return void
	 */
	public static function register_routes(): void {
		register_rest_route(
			self::REST_NS,
			'/icons/uploads',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( self::class, 'rest_list' ),
					'permission_callback' => static fn() => current_user_can( 'edit_posts' ),
					'args'                => array(
						'page'     => array(
							'type'    => 'integer',
							'minimum' => 1,
							'default' => 1,
						),
						'per_page' => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 100,
							'default' => 100,
						),
					),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( self::class, 'rest_create' ),
					'permission_callback' => static fn() => current_user_can( 'upload_files' ),
					'args'                => array(
						'name' => array(
							'type'      => 'string',
							'required'  => true,
							'maxLength' => 200,
						),
						'svg'  => array(
							'type'      => 'string',
							'required'  => true,
							'maxLength' => self::MAX_BYTES,
						),
					),
				),
			)
		);
		register_rest_route(
			self::REST_NS,
			'/icons/uploads/(?P<id>\d+)',
			array(
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => array( self::class, 'rest_delete' ),
				'permission_callback' => static fn( \WP_REST_Request $request ) => self::is_icon( (int) $request['id'] ) && current_user_can( 'delete_post', (int) $request['id'] ),
			)
		);
	}

	/**
	 * Whether a post id is an uploaded icon.
	 *
	 * @param int $id Post id.
	 * @return bool
	 */
	public static function is_icon( int $id ): bool {
		$post = get_post( $id );

		return $post instanceof \WP_Post && self::POST_TYPE === $post->post_type;
	}

	/**
	 * One uploaded icon as the picker sees it. The stored bytes are sanitised AGAIN on the way
	 * out, so a row written by anything other than rest_create() still leaves clean.
	 *
	 * @param \WP_Post $post Icon post.
	 * @return array{id:int,name:string,svg:string}
	 */
	public static function item( \WP_Post $post ): array {
		return array(
			'id'   => (int) $post->ID,
			'name' => (string) $post->post_title,
			'svg'  => Svg::sanitize( (string) get_post_meta( $post->ID, self::META, true ) ),
		);
	}

	/**
	 * GET: the uploaded icons, newest first.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function rest_list( \WP_REST_Request $request ): \WP_REST_Response {
		$query = new \WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => (int) $request['per_page'],
				'paged'          => (int) $request['page'],
				'orderby'        => 'date',
				'order'          => 'DESC',
				'no_found_rows'  => false,
			)
		);
		$items = array();
		foreach ( $query->posts as $post ) {
			if ( $post instanceof \WP_Post ) {
				$items[] = self::item( $post );
			}
		}
		$response = new \WP_REST_Response( $items );
		$response->header( 'X-WP-Total', (string) (int) $query->found_posts );
		$response->header( 'X-WP-TotalPages', (string) (int) $query->max_num_pages );

		return $response;
	}

	/**
	 * Clean an uploaded icon: sanitised, and it must still draw something.
	 *
	 * @param string $svg Untrusted SVG text.
	 * @return string Clean SVG, or '' when nothing drawable survives.
	 */
	public static function clean_upload( string $svg ): string {
		$clean = Svg::sanitize( $svg );
		if ( 1 !== preg_match( '/<(path|circle|ellipse|line|polyline|polygon|rect|use|text)\b/', $clean ) ) {
			return '';
		}

		return $clean;
	}

	/**
	 * A display name from the uploaded file name.
	 *
	 * @param string $name File name.
	 * @return string
	 */
	public static function clean_name( string $name ): string {
		$name = sanitize_text_field( (string) preg_replace( '/\.svgz?$/i', '', $name ) );

		return '' === $name ? __( 'Icon', 'page-builder-sandwich' ) : mb_substr( $name, 0, 100 );
	}

	/**
	 * POST: sanitise and keep an uploaded SVG.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function rest_create( \WP_REST_Request $request ) {
		$clean = self::clean_upload( (string) $request['svg'] );
		if ( '' === $clean ) {
			return new \WP_Error(
				'pbsw_icon_invalid',
				__( 'This file is not an SVG icon we can use. It may be damaged, or it only held content that is removed for safety (scripts, links, embedded images or fonts).', 'page-builder-sandwich' ),
				array( 'status' => 400 )
			);
		}
		$id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => self::clean_name( (string) $request['name'] ),
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		update_post_meta( (int) $id, self::META, wp_slash( $clean ) );
		$post = get_post( (int) $id );

		return new \WP_REST_Response( $post instanceof \WP_Post ? self::item( $post ) : array(), 201 );
	}

	/**
	 * DELETE: remove an uploaded icon (blocks that used it keep their own copy).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function rest_delete( \WP_REST_Request $request ) {
		$id = (int) $request['id'];
		if ( ! wp_delete_post( $id, true ) ) {
			return new \WP_Error( 'pbsw_icon_delete', __( 'The icon could not be deleted.', 'page-builder-sandwich' ), array( 'status' => 500 ) );
		}

		return new \WP_REST_Response( array( 'deleted' => $id ) );
	}
}
