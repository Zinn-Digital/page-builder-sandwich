<?php
/**
 * AI inside the builder (P12, free): REST routes, MCP abilities and the editor bundle.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Ai;

use ZinnDigital\PBS\Mcp\Abilities;
use ZinnDigital\PBS\McpKit\Ability;
use ZinnDigital\PBS\Rest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every free AI feature is one function (Writer, Seo_Writer) reached three ways with the same
 * permission check: the editor (REST), an AI agent (MCP ability) and any REST client.
 */
final class Ai_Routes {

	/** Editor script handle. */
	public const HANDLE = 'pbsw-ai';

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
		add_action( 'pbsw_mcp_abilities', array( self::class, 'abilities' ) );
		add_action( 'enqueue_block_editor_assets', array( self::class, 'enqueue' ) );
	}

	/**
	 * May the current user edit this post (or posts at all, when none is named)?
	 *
	 * @param int $post_id Post, 0 for none.
	 * @return bool
	 */
	public static function can_edit( int $post_id ): bool {
		return $post_id > 0 ? current_user_can( 'edit_post', $post_id ) : current_user_can( 'edit_posts' );
	}

	/**
	 * A callable's answer as a REST response.
	 *
	 * @param array<string, mixed>|\WP_Error $out Answer.
	 * @return \WP_REST_Response|\WP_Error
	 */
	private static function respond( $out ) {
		return is_wp_error( $out ) ? $out : new \WP_REST_Response( $out );
	}

	/**
	 * REST routes.
	 *
	 * @return void
	 */
	public static function routes(): void {
		$post_arg = array(
			'type'    => 'integer',
			'minimum' => 0,
			'default' => 0,
		);
		register_rest_route(
			Rest::NAMESPACE,
			'/ai/status',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => static fn() => new \WP_REST_Response( self::status() ),
				'permission_callback' => static fn(): bool => current_user_can( 'edit_posts' ),
			)
		);
		register_rest_route(
			Rest::NAMESPACE,
			'/ai/text',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => static fn( \WP_REST_Request $request ) => self::respond( Writer::text( $request->get_params() ) ),
				'permission_callback' => static fn( \WP_REST_Request $request ): bool => (int) $request['post_id'] > 0 ? current_user_can( 'edit_post', (int) $request['post_id'] ) : current_user_can( 'edit_posts' ),
				'args'                => array(
					'post_id'     => $post_arg,
					'text'        => array(
						'type'    => 'string',
						'default' => '',
					),
					'action'      => array(
						'type'     => 'string',
						'enum'     => Writer::ACTIONS,
						'required' => true,
					),
					'tone'        => array(
						'type' => 'string',
						'enum' => Writer::TONES,
					),
					'instruction' => array(
						'type'    => 'string',
						'default' => '',
					),
					'context'     => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);
		register_rest_route(
			Rest::NAMESPACE,
			'/ai/section',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => static fn( \WP_REST_Request $request ) => self::respond( Writer::section( $request->get_params() ) ),
				'permission_callback' => static fn( \WP_REST_Request $request ): bool => (int) $request['post_id'] > 0 ? current_user_can( 'edit_post', (int) $request['post_id'] ) : current_user_can( 'edit_posts' ),
				'args'                => array(
					'description' => array(
						'type'     => 'string',
						'required' => true,
					),
					'section'     => array(
						'type'    => 'string',
						'default' => '',
					),
					'post_id'     => $post_arg,
					'position'    => array(
						'type'    => 'string',
						'enum'    => array( 'start', 'end' ),
						'default' => 'end',
					),
				),
			)
		);
		register_rest_route(
			Rest::NAMESPACE,
			'/ai/seo-meta',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => static fn( \WP_REST_Request $request ) => self::respond( Seo_Writer::suggest( (int) $request['post_id'], (string) $request['keyword'] ) ),
					'permission_callback' => static fn( \WP_REST_Request $request ): bool => current_user_can( 'edit_post', (int) $request['post_id'] ),
					'args'                => array(
						'post_id' => array(
							'type'     => 'integer',
							'required' => true,
						),
						'keyword' => array(
							'type'    => 'string',
							'default' => '',
						),
					),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => static fn( \WP_REST_Request $request ) => self::respond( Seo_Writer::apply( (int) $request['post_id'], (string) $request['title'], (string) $request['description'] ) ),
					'permission_callback' => static fn( \WP_REST_Request $request ): bool => current_user_can( 'edit_post', (int) $request['post_id'] ),
					'args'                => array(
						'post_id'     => array(
							'type'     => 'integer',
							'required' => true,
						),
						'title'       => array(
							'type'     => 'string',
							'required' => true,
						),
						'description' => array(
							'type'     => 'string',
							'required' => true,
						),
					),
				),
			)
		);
		register_rest_route(
			Rest::NAMESPACE,
			'/ai/alt-text',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => static fn( \WP_REST_Request $request ) => self::respond( Seo_Writer::alt_text( $request->get_params() ) ),
				'permission_callback' => static fn( \WP_REST_Request $request ): bool => current_user_can( 'upload_files' ) && ( (int) $request['attachment_id'] > 0 ? current_user_can( 'edit_post', (int) $request['attachment_id'] ) : current_user_can( 'edit_post', (int) $request['post_id'] ) ),
				'args'                => array(
					'attachment_id' => $post_arg,
					'post_id'       => $post_arg,
					'only_missing'  => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'language'      => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);
		register_rest_route(
			Rest::NAMESPACE,
			'/ai/undo',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => static fn( \WP_REST_Request $request ) => self::respond( Ai::undo( (int) $request['post_id'], (int) $request['revision'] ) ),
				'permission_callback' => static fn( \WP_REST_Request $request ): bool => current_user_can( 'edit_post', (int) $request['post_id'] ),
				'args'                => array(
					'post_id'  => array(
						'type'     => 'integer',
						'required' => true,
					),
					'revision' => array(
						'type'     => 'integer',
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * What the editor needs to know before it offers AI.
	 *
	 * @return array<string, mixed>
	 */
	public static function status(): array {
		// The Pro features add themselves (their names) when the premium code is loaded.
		/**
		 * Filters the Pro AI features the editor offers (the premium layer adds its names:
		 * agent, site, block, design, image, css, a11y).
		 *
		 * @param array<int, string> $features Feature names.
		 */
		$pro = array_values( array_map( 'strval', (array) apply_filters( 'pbsw_ai_pro_features', array() ) ) );

		return array(
			'available'    => Ai::available(),
			'ready'        => Ai::ready(),
			'settings_url' => Ai::settings_url(),
			'seo_plugins'  => Seo_Writer::plugins(),
			'actions'      => Writer::ACTIONS,
			'tones'        => Writer::TONES,
			'pro_features' => $pro,
		);
	}

	/**
	 * The free AI abilities (MCP and `/wp-abilities/v1`).
	 *
	 * @return void
	 */
	public static function abilities(): void {
		$post_id = array(
			'type'        => 'integer',
			'minimum'     => 1,
			'description' => __( 'The post or page ID.', 'page-builder-sandwich' ),
		);
		Ability::register(
			'pbs/ai-write-text',
			array(
				'edition'             => 'free',
				'capability'          => 'edit_posts (edit_post when post_id is given); the AI settings\' role and spending rules',
				'label'               => __( 'Write or rewrite text with AI', 'page-builder-sandwich' ),
				'description'         => __( 'Writes new text, or improves, shortens, expands, changes the tone of or fixes the grammar of a piece of text, with the site\'s own AI provider. Returns the new text; nothing on the site is changed.', 'page-builder-sandwich' ),
				'category'            => Abilities::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'action'      => array(
							'type' => 'string',
							'enum' => Writer::ACTIONS,
						),
						'text'        => array(
							'type'      => 'string',
							'maxLength' => Writer::MAX_TEXT,
						),
						'tone'        => array(
							'type' => 'string',
							'enum' => Writer::TONES,
						),
						'instruction' => array(
							'type'      => 'string',
							'maxLength' => 2000,
						),
						'context'     => array(
							'type'      => 'string',
							'maxLength' => 4000,
						),
					),
					'required'             => array( 'action' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array( 'type' => 'object' ),
				'annotations'         => array( 'readonly' => true ),
				'execute_callback'    => array( Writer::class, 'text' ),
				'permission_callback' => static fn(): bool => current_user_can( 'edit_posts' ),
			)
		);
		Ability::register(
			'pbs/ai-generate-section',
			array(
				'edition'             => 'free',
				'capability'          => 'edit_posts; edit_post to add it to a page; the AI settings\' role and spending rules',
				'label'               => __( 'Generate a section with AI', 'page-builder-sandwich' ),
				'description'         => __( 'Turns a one-sentence description into a ready-designed section (real, editable blocks in the site\'s brand style) with text written for it. Returns the block markup, and adds it to the start or end of a page when a post_id is given (a revision keeps what was there before).', 'page-builder-sandwich' ),
				'category'            => Abilities::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'description' => array(
							'type'      => 'string',
							'minLength' => 5,
							'maxLength' => 2000,
						),
						'section'     => array( 'type' => 'string' ),
						'post_id'     => $post_id,
						'position'    => array(
							'type' => 'string',
							'enum' => array( 'start', 'end' ),
						),
					),
					'required'             => array( 'description' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array( 'type' => 'object' ),
				'annotations'         => array( 'destructive' => false ),
				'execute_callback'    => array( Writer::class, 'section' ),
				'permission_callback' => static fn( array $input ): bool => self::can_edit( (int) ( $input['post_id'] ?? 0 ) ),
			)
		);
		Ability::register(
			'pbs/ai-suggest-seo-meta',
			array(
				'edition'             => 'free',
				'capability'          => 'edit_post on the page; the AI settings\' role and spending rules',
				'label'               => __( 'Suggest an SEO title and description with AI', 'page-builder-sandwich' ),
				'description'         => __( 'Reads a page and suggests a meta title (up to 60 characters) and meta description (up to 155), optionally around a focus keyword. Nothing is saved.', 'page-builder-sandwich' ),
				'category'            => Abilities::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'post_id' => $post_id,
						'keyword' => array(
							'type'      => 'string',
							'maxLength' => 100,
						),
					),
					'required'             => array( 'post_id' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array( 'type' => 'object' ),
				'annotations'         => array( 'readonly' => true ),
				'execute_callback'    => static fn( array $input ) => Seo_Writer::suggest( (int) $input['post_id'], (string) ( $input['keyword'] ?? '' ) ),
				'permission_callback' => static fn( array $input ): bool => current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ),
			)
		);
		Ability::register(
			'pbs/ai-save-seo-meta',
			array(
				'edition'             => 'free',
				'capability'          => 'edit_post on the page',
				'label'               => __( 'Save an SEO title and description', 'page-builder-sandwich' ),
				'description'         => __( 'Saves a meta title and description into the SEO plugin the site uses (Yoast SEO, Rank Math, SEOPress or All in One SEO) and says which ones were written.', 'page-builder-sandwich' ),
				'category'            => Abilities::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'post_id'     => $post_id,
						'title'       => array(
							'type'      => 'string',
							'maxLength' => 200,
						),
						'description' => array(
							'type'      => 'string',
							'maxLength' => 500,
						),
					),
					'required'             => array( 'post_id', 'title', 'description' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array( 'type' => 'object' ),
				'annotations'         => array(
					'destructive' => false,
					'idempotent'  => true,
				),
				'execute_callback'    => static fn( array $input ) => Seo_Writer::apply( (int) $input['post_id'], (string) $input['title'], (string) $input['description'] ),
				'permission_callback' => static fn( array $input ): bool => current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ),
			)
		);
		Ability::register(
			'pbs/ai-write-alt-text',
			array(
				'edition'             => 'free',
				'capability'          => 'upload_files and edit_post on the image or page; the AI settings\' role and spending rules',
				'label'               => __( 'Write image alt text with AI', 'page-builder-sandwich' ),
				'description'         => __( 'Looks at images and saves alt text for them: one image (attachment_id), or every image on a page that has none (post_id). At most 20 images per call; the answer says how many remain.', 'page-builder-sandwich' ),
				'category'            => Abilities::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'attachment_id' => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'post_id'       => $post_id,
						'only_missing'  => array( 'type' => 'boolean' ),
						'language'      => array( 'type' => 'string' ),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array( 'type' => 'object' ),
				'annotations'         => array( 'destructive' => false ),
				'execute_callback'    => array( Seo_Writer::class, 'alt_text' ),
				'permission_callback' => static fn( array $input ): bool => current_user_can( 'upload_files' ) && ( ! empty( $input['attachment_id'] ) ? current_user_can( 'edit_post', (int) $input['attachment_id'] ) : ( ! empty( $input['post_id'] ) && current_user_can( 'edit_post', (int) $input['post_id'] ) ) ),
			)
		);
		Ability::register(
			'pbs/ai-undo',
			array(
				'edition'             => 'free',
				'capability'          => 'edit_post on the page',
				'label'               => __( 'Undo an AI change', 'page-builder-sandwich' ),
				'description'         => __( 'Puts a page back the way it was before an AI change, from the undo_revision that change returned.', 'page-builder-sandwich' ),
				'category'            => Abilities::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'post_id'  => $post_id,
						'revision' => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
					),
					'required'             => array( 'post_id', 'revision' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array( 'type' => 'object' ),
				'annotations'         => array( 'destructive' => false ),
				'execute_callback'    => static fn( array $input ) => Ai::undo( (int) $input['post_id'], (int) $input['revision'] ),
				'permission_callback' => static fn( array $input ): bool => current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ),
			)
		);
	}

	/**
	 * The editor bundle: the AI menu on text blocks and the AI sidebar (block editor and Studio).
	 *
	 * @return void
	 */
	public static function enqueue(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		$asset_file = PBSW_DIR . 'build/ai.asset.php';
		if ( ! is_readable( $asset_file ) ) {
			return;
		}
		$asset = require $asset_file;
		wp_enqueue_script(
			self::HANDLE,
			PBSW_URL . 'build/ai.js',
			(array) ( $asset['dependencies'] ?? array() ),
			(string) ( $asset['version'] ?? PBSW_VERSION ),
			true
		);
		wp_set_script_translations( self::HANDLE, 'page-builder-sandwich', PBSW_DIR . 'languages' );
		wp_add_inline_script( self::HANDLE, 'window.pbswAi = ' . wp_json_encode( self::status() ) . ';', 'before' );
		/**
		 * Fires after the AI editor bundle is enqueued, so an add-on (and the Pro layer) can enqueue
		 * its own AI panels after it.
		 *
		 * @param string $handle The AI bundle's script handle.
		 */
		do_action( 'pbsw_ai_enqueue', self::HANDLE );
	}
}
