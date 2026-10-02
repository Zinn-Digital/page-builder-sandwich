<?php
/**
 * Page Builder Sandwich abilities: pages, sections and kits for AI agents (MCP) and REST.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Mcp;

use ZinnDigital\PBS\Cloud\Kits;
use ZinnDigital\PBS\Cloud\Patterns;
use ZinnDigital\PBS\McpKit\Ability;
use ZinnDigital\PBS\McpKit\Rest_Bridge;
use ZinnDigital\PBS\McpKit\Server;
use ZinnDigital\PBS\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The free abilities (owner, 2026-09-30: basic abilities free, bulk and AI in Pro).
 *
 * Abilities: pbs/list-pages · pbs/get-page · pbs/create-page · pbs/update-page
 * pbs/list-sections · pbs/add-section · pbs/list-kits · pbs/apply-kit
 *
 * Each checks the SAME capability WordPress checks for the same action in wp-admin, per post:
 * `edit_pages` to see the list, `edit_post` for the page it reads or changes, `publish_pages` to
 * publish. Content goes through wp_insert_post(), so a user without `unfiltered_html` has it
 * filtered exactly as in the editor. Pro kits are refused by the kit library itself.
 */
final class Abilities {

	/** The ability category. */
	public const CATEGORY = 'page-builder-sandwich';

	/** Statuses a page may be given. */
	private const STATUSES = array( 'draft', 'pending', 'publish', 'private' );

	/** Most items a list returns in one page. */
	private const MAX_PER_PAGE = 100;

	/**
	 * Boot the MCP kit for this plugin.
	 *
	 * @return void
	 */
	public static function boot(): void {
		Server::boot(
			array(
				'id'             => 'page-builder-sandwich',
				'rest_namespace' => \ZinnDigital\PBS\Rest::NAMESPACE,
				'name'           => 'Page Builder Sandwich',
				'description'    => static fn(): string => __( 'Build and edit this site\'s pages from Page Builder Sandwich sections and website kits.', 'page-builder-sandwich' ),
				'version'        => PBSW_VERSION,
				'capability'     => 'edit_pages',
				'category'       => array(
					'slug'        => self::CATEGORY,
					'label'       => static fn(): string => __( 'Page Builder Sandwich', 'page-builder-sandwich' ),
					'description' => static fn(): string => __( 'Pages, sections and website kits.', 'page-builder-sandwich' ),
				),
				'enabled'        => static fn(): bool => Settings::get()['mcp'],
				'abilities'      => array( self::class, 'register' ),
				'vendor_dir'     => PBSW_DIR . 'vendor/wordpress',
				'docs'           => array(
					'guide'      => 'https://zinndigital.com/wordpress-plugins/page-builder-sandwich/mcp',
					'developers' => 'https://zinndigital.com/wordpress-plugins/page-builder-sandwich/mcp-api',
				),
			)
		);
	}

	/**
	 * Register the abilities (on `wp_abilities_api_init`), then the Pro ones when licensed.
	 *
	 * @return void
	 */
	public static function register(): void {
		$page_out = array(
			'type'       => 'object',
			'properties' => array(
				'id'        => array( 'type' => 'integer' ),
				'title'     => array( 'type' => 'string' ),
				'status'    => array( 'type' => 'string' ),
				'link'      => array( 'type' => 'string' ),
				'edit_link' => array( 'type' => 'string' ),
			),
		);

		Ability::register(
			'pbs/list-pages',
			array(
				'edition'             => 'free',
				'capability'          => 'edit_pages; only pages the user can edit are listed',
				'label'               => __( 'List pages', 'page-builder-sandwich' ),
				'description'         => __( 'Lists the pages you can edit, newest first, with an optional search.', 'page-builder-sandwich' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'search'   => array(
							'type'        => 'string',
							'description' => __( 'Words to search for in the title and content.', 'page-builder-sandwich' ),
						),
						'status'   => array(
							'type' => 'string',
							'enum' => array_merge( array( 'any' ), self::STATUSES ),
						),
						'page'     => array(
							'type'    => 'integer',
							'minimum' => 1,
							'default' => 1,
						),
						'per_page' => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => self::MAX_PER_PAGE,
							'default' => 20,
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'pages' => array(
							'type'  => 'array',
							'items' => $page_out,
						),
						'total' => array( 'type' => 'integer' ),
					),
				),
				'execute_callback'    => array( self::class, 'list_pages' ),
				'permission_callback' => static fn(): bool => current_user_can( 'edit_pages' ),
				'annotations'         => array(
					'readonly'   => true,
					'idempotent' => true,
				),
			)
		);

		Ability::register(
			'pbs/get-page',
			array(
				'edition'             => 'free',
				'capability'          => 'edit_post on that page',
				'label'               => __( 'Read a page', 'page-builder-sandwich' ),
				'description'         => __( 'Returns a page\'s title, status, links and block content, and the sections (top-level blocks) it is made of.', 'page-builder-sandwich' ),
				'category'            => self::CATEGORY,
				'input_schema'        => self::id_schema(),
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => array( self::class, 'get_page' ),
				'permission_callback' => static fn( array $input ): bool => self::can_edit_page( Ability::int( $input, 'id' ) ),
				'annotations'         => array(
					'readonly'   => true,
					'idempotent' => true,
				),
			)
		);

		Ability::register(
			'pbs/create-page',
			array(
				'edition'             => 'free',
				'capability'          => 'edit_pages; publish_pages to publish or make private',
				'label'               => __( 'Create a page', 'page-builder-sandwich' ),
				'description'         => __( 'Creates a page from library sections and/or block content. A new page is a draft unless another status is asked for.', 'page-builder-sandwich' ),
				'category'            => self::CATEGORY,
				'input_schema'        => self::page_schema( true ),
				'output_schema'       => $page_out,
				'annotations'         => array( 'destructive' => false ),
				'execute_callback'    => array( self::class, 'create_page' ),
				'permission_callback' => static fn( array $input ): bool => current_user_can( 'edit_pages' ) && self::can_set_status( (string) ( $input['status'] ?? 'draft' ) ),
			)
		);

		Ability::register(
			'pbs/update-page',
			array(
				'edition'             => 'free',
				'capability'          => 'edit_post on that page; publish_pages to publish or make private',
				'label'               => __( 'Update a page', 'page-builder-sandwich' ),
				'description'         => __( 'Changes a page\'s title, status or block content. Content you send replaces the page\'s content.', 'page-builder-sandwich' ),
				'category'            => self::CATEGORY,
				'input_schema'        => self::page_schema( false ),
				'output_schema'       => $page_out,
				'execute_callback'    => array( self::class, 'update_page' ),
				'permission_callback' => static fn( array $input ): bool => self::can_edit_page( Ability::int( $input, 'id' ) ) && ( ! isset( $input['status'] ) || self::can_set_status( (string) $input['status'] ) ),
				'annotations'         => array(
					'destructive' => true,
					'idempotent'  => true,
				),
			)
		);

		Ability::register(
			'pbs/list-sections',
			array(
				'edition'             => 'free',
				'capability'          => 'edit_pages',
				'label'               => __( 'List sections', 'page-builder-sandwich' ),
				'description'         => __( 'Lists the ready-made sections (hero, features, FAQ, testimonials, call to action and more) that can be added to a page.', 'page-builder-sandwich' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'search' => array(
							'type'        => 'string',
							'description' => __( 'Words to match in a section\'s name or keywords.', 'page-builder-sandwich' ),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'sections' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'name'     => array( 'type' => 'string' ),
									'title'    => array( 'type' => 'string' ),
									'keywords' => array(
										'type'  => 'array',
										'items' => array( 'type' => 'string' ),
									),
								),
							),
						),
					),
				),
				'execute_callback'    => array( self::class, 'list_sections' ),
				'permission_callback' => static fn(): bool => current_user_can( 'edit_pages' ),
				'annotations'         => array(
					'readonly'   => true,
					'idempotent' => true,
				),
			)
		);

		Ability::register(
			'pbs/add-section',
			array(
				'edition'             => 'free',
				'capability'          => 'edit_post on that page',
				'label'               => __( 'Add a section to a page', 'page-builder-sandwich' ),
				'description'         => __( 'Adds a ready-made section to a page: at the start, at the end, or after the section at a given position.', 'page-builder-sandwich' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id'       => array( 'type' => 'integer' ),
						'section'  => array(
							'type'        => 'string',
							'description' => __( 'The name of a section in this site\'s section library.', 'page-builder-sandwich' ),
						),
						'position' => array(
							'type'    => 'string',
							'enum'    => array( 'start', 'end', 'after' ),
							'default' => 'end',
						),
						'after'    => array(
							'type'        => 'integer',
							'minimum'     => 0,
							'description' => __( 'With position "after": the 0-based position of the section to insert after.', 'page-builder-sandwich' ),
						),
					),
					'required'             => array( 'id', 'section' ),
					'additionalProperties' => false,
				),
				'output_schema'       => $page_out,
				'annotations'         => array( 'destructive' => false ),
				'execute_callback'    => array( self::class, 'add_section' ),
				'permission_callback' => static fn( array $input ): bool => self::can_edit_page( Ability::int( $input, 'id' ) ),
			)
		);

		Ability::register(
			'pbs/list-kits',
			array(
				'edition'             => 'free',
				'capability'          => 'edit_pages and publish_pages',
				'label'               => __( 'List website kits', 'page-builder-sandwich' ),
				'description'         => __( 'Lists the website kits (a set of ready-made pages for one kind of business) this site can import.', 'page-builder-sandwich' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'refresh' => array(
							'type'    => 'boolean',
							'default' => false,
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => array( self::class, 'list_kits' ),
				'permission_callback' => array( Kits::class, 'can_import' ),
				'annotations'         => array(
					'readonly'   => true,
					'idempotent' => true,
				),
			)
		);

		Ability::register(
			'pbs/apply-kit',
			array(
				'edition'             => 'free',
				'capability'          => 'edit_pages and publish_pages; manage_options to set the home page',
				'label'               => __( 'Import a website kit', 'page-builder-sandwich' ),
				'description'         => __( 'Imports a website kit\'s pages (all of them, or the ones named), optionally publishing them, setting the home page and adding them to a menu. Importing again updates the same pages.', 'page-builder-sandwich' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'slug'    => array(
							'type'    => 'string',
							'pattern' => '^[a-z0-9][a-z0-9-]{1,60}$',
						),
						'pages'   => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
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
					'required'             => array( 'slug' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => array( self::class, 'apply_kit' ),
				'permission_callback' => static fn( array $input ): bool => Kits::can_import() && ( empty( $input['front'] ) || current_user_can( 'manage_options' ) ),
				'annotations'         => array(
					'destructive' => false,
					'idempotent'  => true,
				),
			)
		);

		Ability::register(
			'pbs/list-blocks',
			array(
				'edition'             => 'free',
				'capability'          => 'edit_posts',
				'label'               => __( 'List the builder blocks', 'page-builder-sandwich' ),
				'description'         => __( 'Lists every Page Builder Sandwich block this site can use (Pro blocks too when Pro is active): name, title, category and description.', 'page-builder-sandwich' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'search' => array( 'type' => 'string' ),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => array( self::class, 'list_blocks' ),
				'permission_callback' => static fn(): bool => current_user_can( 'edit_posts' ),
				'annotations'         => array(
					'readonly'   => true,
					'idempotent' => true,
				),
			)
		);

		Ability::register(
			'pbs/get-block',
			array(
				'edition'             => 'free',
				'capability'          => 'edit_posts',
				'label'               => __( 'Describe a builder block', 'page-builder-sandwich' ),
				'description'         => __( 'Returns one block\'s settings (attributes with their types, choices and defaults), what it supports, which blocks it may contain, and a ready-to-use example in block markup.', 'page-builder-sandwich' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'name' => array(
							'type'    => 'string',
							'pattern' => '^pbs/[a-z0-9-]+$',
						),
					),
					'required'             => array( 'name' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => array( self::class, 'get_block' ),
				'permission_callback' => static fn(): bool => current_user_can( 'edit_posts' ),
				'annotations'         => array(
					'readonly'   => true,
					'idempotent' => true,
				),
			)
		);

		$path_schema = array(
			'type'        => 'array',
			'items'       => array(
				'type'    => 'integer',
				'minimum' => 0,
			),
			'minItems'    => 1,
			'description' => __( 'Where the block is: its position among the page\'s sections, then among that block\'s inner blocks, and so on (0-based), in the order the page lists them.', 'page-builder-sandwich' ),
		);

		Ability::register(
			'pbs/update-block',
			array(
				'edition'             => 'free',
				'capability'          => 'edit_post on that page',
				'label'               => __( 'Change one block\'s settings', 'page-builder-sandwich' ),
				'description'         => __( 'Changes the settings (attributes) of one block inside a page, found by its path; attributes you send are merged over the current ones. Works on blocks the site renders from their settings; a block whose HTML is saved in the page is changed by replacing the page\'s markup.', 'page-builder-sandwich' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id'         => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'path'       => $path_schema,
						'attributes' => array( 'type' => 'object' ),
					),
					'required'             => array( 'id', 'path', 'attributes' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => array( self::class, 'update_block' ),
				'permission_callback' => static fn( array $input ): bool => self::can_edit_page( Ability::int( $input, 'id' ) ),
				'annotations'         => array(
					'destructive' => true,
					'idempotent'  => true,
				),
			)
		);

		Ability::register(
			'pbs/remove-block',
			array(
				'edition'             => 'free',
				'capability'          => 'edit_post on that page',
				'label'               => __( 'Remove a block from a page', 'page-builder-sandwich' ),
				'description'         => __( 'Removes one block (a whole section, or a block inside one) from a page, found by its path. The page\'s previous version stays in its revisions.', 'page-builder-sandwich' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id'   => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'path' => $path_schema,
					),
					'required'             => array( 'id', 'path' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => array( self::class, 'remove_block' ),
				'permission_callback' => static fn( array $input ): bool => self::can_edit_page( Ability::int( $input, 'id' ) ),
				'annotations'         => array( 'destructive' => true ),
			)
		);

		Ability::register(
			'pbs/move-section',
			array(
				'edition'             => 'free',
				'capability'          => 'edit_post on that page',
				'label'               => __( 'Move a section', 'page-builder-sandwich' ),
				'description'         => __( 'Moves a section of a page from one position to another (0-based, in the order the page lists them).', 'page-builder-sandwich' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id'   => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'from' => array(
							'type'    => 'integer',
							'minimum' => 0,
						),
						'to'   => array(
							'type'    => 'integer',
							'minimum' => 0,
						),
					),
					'required'             => array( 'id', 'from', 'to' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array( 'type' => 'object' ),
				'annotations'         => array( 'destructive' => false ),
				'execute_callback'    => array( self::class, 'move_section' ),
				'permission_callback' => static fn( array $input ): bool => self::can_edit_page( Ability::int( $input, 'id' ) ),
			)
		);

		Ability::register(
			'pbs/duplicate-page',
			array(
				'edition'             => 'free',
				'capability'          => 'edit_post on the page and edit_pages',
				'label'               => __( 'Duplicate a page', 'page-builder-sandwich' ),
				'description'         => __( 'Copies a page (its title, content and template) into a new draft.', 'page-builder-sandwich' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id'    => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'title' => array( 'type' => 'string' ),
					),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array( 'type' => 'object' ),
				'annotations'         => array( 'destructive' => false ),
				'execute_callback'    => array( self::class, 'duplicate_page' ),
				'permission_callback' => static fn( array $input ): bool => current_user_can( 'edit_pages' ) && self::can_edit_page( Ability::int( $input, 'id' ) ),
			)
		);

		Ability::register(
			'pbs/delete-page',
			array(
				'edition'             => 'free',
				'capability'          => 'delete_post on that page',
				'label'               => __( 'Move a page to the trash', 'page-builder-sandwich' ),
				'description'         => __( 'Moves a page to the trash. It can be restored from Pages, Trash for 30 days.', 'page-builder-sandwich' ),
				'category'            => self::CATEGORY,
				'input_schema'        => self::id_schema(),
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => array( self::class, 'delete_page' ),
				'permission_callback' => static fn( array $input ): bool => null !== self::page( Ability::int( $input, 'id' ) ) && current_user_can( 'delete_post', Ability::int( $input, 'id' ) ),
				'annotations'         => array( 'destructive' => true ),
			)
		);

		Ability::register(
			'pbs/build-landing-page',
			array(
				'edition'             => 'free',
				'capability'          => 'edit_pages',
				'mcp_type'            => 'prompt',
				'label'               => __( 'Build a landing page', 'page-builder-sandwich' ),
				'description'         => __( 'A guided workflow: the agent picks sections that fit your business, builds the page as a draft and writes its text.', 'page-builder-sandwich' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'business' => array(
							'type'        => 'string',
							'description' => __( 'The business, its offer and its audience.', 'page-builder-sandwich' ),
						),
					),
					'required'             => array( 'business' ),
					'additionalProperties' => false,
				),
				'annotations'         => array( 'destructive' => false ),
				'execute_callback'    => array( self::class, 'landing_page_prompt' ),
				'permission_callback' => static fn(): bool => current_user_can( 'edit_pages' ),
			)
		);

		// Every other REST route of the plugin, as an ability of its own (includes/mcp/class-rest-map.php).
		require_once __DIR__ . '/class-rest-map.php';
		Rest_Bridge::register( Rest_Map::entries(), self::CATEGORY );

		/**
		 * Fires after the free abilities are registered: Page Builder Sandwich Pro registers its
		 * own here (its boot hooks this only when the licence allows the premium code).
		 */
		do_action( 'pbsw_mcp_abilities' );
	}

	/**
	 * `pbs/list-pages`.
	 *
	 * @param array<string, mixed> $input Input.
	 * @return array<string, mixed>
	 */
	public static function list_pages( array $input ): array {
		$status = (string) ( $input['status'] ?? 'any' );
		$query  = new \WP_Query(
			array(
				'post_type'      => 'page',
				'post_status'    => in_array( $status, self::STATUSES, true ) ? $status : self::STATUSES,
				's'              => (string) ( $input['search'] ?? '' ),
				'paged'          => max( 1, Ability::int( $input, 'page' ) ),
				'posts_per_page' => min( self::MAX_PER_PAGE, max( 1, 0 === Ability::int( $input, 'per_page' ) ? 20 : Ability::int( $input, 'per_page' ) ) ),
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'perm'           => 'editable',
				'no_found_rows'  => false,
			)
		);
		$pages  = array();
		foreach ( $query->posts as $post ) {
			if ( $post instanceof \WP_Post && current_user_can( 'edit_post', $post->ID ) ) {
				$pages[] = self::page_summary( $post );
			}
		}

		return array(
			'pages' => $pages,
			'total' => (int) $query->found_posts,
		);
	}

	/**
	 * `pbs/get-page`.
	 *
	 * @param array<string, mixed> $input Input.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function get_page( array $input ) {
		$post = self::page( Ability::int( $input, 'id' ) );
		if ( null === $post ) {
			return self::not_found();
		}
		$sections = array();
		foreach ( parse_blocks( $post->post_content ) as $block ) {
			if ( null !== $block['blockName'] ) {
				$sections[] = (string) $block['blockName'];
			}
		}

		return self::page_summary( $post ) + array(
			'content'  => $post->post_content,
			'sections' => $sections,
			// The block tree with positions, for pbs/update-block, pbs/remove-block (paths).
			'outline'  => self::outline( self::real( parse_blocks( $post->post_content ) ) ),
			'modified' => mysql_to_rfc3339( $post->post_modified_gmt ),
		);
	}

	/**
	 * `pbs/create-page`. Public so the Pro bulk ability builds each page the same way.
	 *
	 * @param array<string, mixed> $input Input.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function create_page( array $input ) {
		$status = (string) ( $input['status'] ?? 'draft' );
		if ( ! in_array( $status, self::STATUSES, true ) ) {
			return new \WP_Error( 'pbsw_bad_status', __( 'A page can be a draft, pending review, published or private.', 'page-builder-sandwich' ), array( 'status' => 400 ) );
		}
		if ( ! self::can_set_status( $status ) ) {
			return self::forbidden();
		}
		$content = self::sections_markup( Ability::strings( $input, 'sections' ) );
		if ( is_wp_error( $content ) ) {
			return $content;
		}
		$content .= (string) ( $input['content'] ?? '' );
		$title    = trim( (string) ( $input['title'] ?? '' ) );
		if ( '' === $title ) {
			return new \WP_Error( 'pbsw_no_title', __( 'Give the page a title.', 'page-builder-sandwich' ), array( 'status' => 400 ) );
		}
		$id = wp_insert_post(
			wp_slash(
				array(
					'post_type'    => 'page',
					'post_title'   => sanitize_text_field( $title ),
					'post_status'  => $status,
					'post_content' => $content,
				)
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}

		return self::page_summary( get_post( $id ) );
	}

	/**
	 * `pbs/update-page`.
	 *
	 * @param array<string, mixed> $input Input.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function update_page( array $input ) {
		$post = self::page( Ability::int( $input, 'id' ) );
		if ( null === $post ) {
			return self::not_found();
		}
		$postarr = array( 'ID' => $post->ID );
		if ( isset( $input['title'] ) ) {
			$postarr['post_title'] = sanitize_text_field( (string) $input['title'] );
		}
		if ( isset( $input['status'] ) ) {
			$status = (string) $input['status'];
			if ( ! in_array( $status, self::STATUSES, true ) || ! self::can_set_status( $status ) ) {
				return self::forbidden();
			}
			$postarr['post_status'] = $status;
		}
		if ( isset( $input['content'] ) || isset( $input['sections'] ) ) {
			$sections = self::sections_markup( Ability::strings( $input, 'sections' ) );
			if ( is_wp_error( $sections ) ) {
				return $sections;
			}
			$postarr['post_content'] = $sections . (string) ( $input['content'] ?? '' );
		}
		$id = wp_update_post( wp_slash( $postarr ), true );
		if ( is_wp_error( $id ) ) {
			return $id;
		}

		return self::page_summary( get_post( $id ) );
	}

	/**
	 * `pbs/list-sections`.
	 *
	 * @param array<string, mixed> $input Input.
	 * @return array<string, mixed>
	 */
	public static function list_sections( array $input ): array {
		$search = strtolower( trim( (string) ( $input['search'] ?? '' ) ) );
		$out    = array();
		foreach ( self::sections() as $name => $pattern ) {
			$keywords = array_values( array_map( 'strval', (array) ( $pattern['keywords'] ?? array() ) ) );
			$haystack = strtolower( $name . ' ' . (string) $pattern['title'] . ' ' . implode( ' ', $keywords ) );
			if ( '' !== $search && ! str_contains( $haystack, $search ) ) {
				continue;
			}
			$out[] = array(
				'name'     => $name,
				'title'    => (string) $pattern['title'],
				'keywords' => $keywords,
			);
		}

		return array( 'sections' => $out );
	}

	/**
	 * Blocks with the whitespace-only entries dropped: the positions a path counts.
	 *
	 * @param array<int, array<string, mixed>> $blocks Parsed blocks.
	 * @return array<int, array<string, mixed>>
	 */
	private static function real( array $blocks ): array {
		return array_values( array_filter( $blocks, static fn( array $b ): bool => null !== $b['blockName'] ) );
	}

	/**
	 * Apply $change to the block at $path inside $blocks (real positions), return the new list.
	 *
	 * @param array<int, array<string, mixed>> $blocks Blocks (whitespace dropped).
	 * @param array<int, int>                  $path   Path.
	 * @param callable                         $change fn( array $parent_list, int $index ): array — returns the new list.
	 * @return array<int, array<string, mixed>>|null Null when the path does not exist.
	 */
	private static function at( array $blocks, array $path, callable $change ): ?array {
		$index = (int) array_shift( $path );
		if ( ! isset( $blocks[ $index ] ) ) {
			return null;
		}
		if ( array() === $path ) {
			return $change( $blocks, $index );
		}
		$inner = self::at( self::real( (array) $blocks[ $index ]['innerBlocks'] ), $path, $change );
		if ( null === $inner ) {
			return null;
		}
		$blocks[ $index ] = self::with_inner( $blocks[ $index ], $inner );

		return $blocks;
	}

	/**
	 * A block with new inner blocks, its innerContent rebuilt around them (the HTML between the
	 * inner blocks is kept in order; one placeholder per inner block).
	 *
	 * @param array<string, mixed>             $block Block.
	 * @param array<int, array<string, mixed>> $inner New inner blocks.
	 * @return array<string, mixed>
	 */
	private static function with_inner( array $block, array $inner ): array {
		$strings = array_values( array_filter( (array) $block['innerContent'], 'is_string' ) );
		$open    = $strings[0] ?? '';
		$close   = count( $strings ) > 1 ? $strings[ count( $strings ) - 1 ] : '';
		$content = array( $open );
		foreach ( $inner as $i => $unused ) {
			unset( $unused );
			$content[] = null;
			if ( $i < count( $inner ) - 1 ) {
				$content[] = "\n\n";
			}
		}
		$content[]             = $close;
		$block['innerBlocks']  = $inner;
		$block['innerContent'] = $content;

		return $block;
	}

	/**
	 * Save new blocks to a page.
	 *
	 * @param \WP_Post                         $post   Page.
	 * @param array<int, array<string, mixed>> $blocks Blocks.
	 * @return array<string, mixed>|\WP_Error
	 */
	private static function save_blocks( \WP_Post $post, array $blocks ) {
		$id = wp_update_post(
			wp_slash(
				array(
					'ID'           => $post->ID,
					'post_content' => implode( "\n\n", array_map( 'serialize_block', $blocks ) ),
				)
			),
			true
		);

		return is_wp_error( $id ) ? $id : self::get_page( array( 'id' => $id ) );
	}

	/**
	 * The block tree: name, the attribute names set, and inner blocks, each at its position.
	 *
	 * @param array<int, array<string, mixed>> $blocks Blocks (whitespace dropped).
	 * @return array<int, array<string, mixed>>
	 */
	private static function outline( array $blocks ): array {
		$out = array();
		foreach ( $blocks as $i => $block ) {
			$row   = array(
				'position' => $i,
				'name'     => (string) $block['blockName'],
				'set'      => array_keys( (array) $block['attrs'] ),
			);
			$inner = self::real( (array) $block['innerBlocks'] );
			if ( array() !== $inner ) {
				$row['inner'] = self::outline( $inner );
			}
			$out[] = $row;
		}

		return $out;
	}

	/**
	 * `pbs/update-block`.
	 *
	 * @param array<string, mixed> $input Input.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function update_block( array $input ) {
		$post = self::page( Ability::int( $input, 'id' ) );
		if ( null === $post ) {
			return self::not_found();
		}
		$attrs   = (array) ( $input['attributes'] ?? array() );
		$refused = null;
		$blocks  = self::at(
			self::real( parse_blocks( $post->post_content ) ),
			array_map( 'intval', (array) ( $input['path'] ?? array() ) ),
			static function ( array $siblings, int $i ) use ( $attrs, &$refused ): array {
				$type = \WP_Block_Type_Registry::get_instance()->get_registered( (string) $siblings[ $i ]['blockName'] );
				if ( ! $type instanceof \WP_Block_Type || ! $type->is_dynamic() ) {
					$refused = (string) $siblings[ $i ]['blockName'];
					return $siblings;
				}
				$siblings[ $i ]['attrs'] = array_merge( (array) $siblings[ $i ]['attrs'], $attrs );
				return $siblings;
			}
		);
		if ( null === $blocks ) {
			return self::bad_path();
		}
		if ( null !== $refused ) {
			/* translators: %s: a block name. */
			return new \WP_Error( 'pbsw_static_block', sprintf( __( '%s saves its HTML in the page, so its settings cannot be changed on their own. Send the page\'s new markup with pbs/update-page.', 'page-builder-sandwich' ), $refused ), array( 'status' => 409 ) );
		}

		return self::save_blocks( $post, $blocks );
	}

	/**
	 * `pbs/remove-block`.
	 *
	 * @param array<string, mixed> $input Input.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function remove_block( array $input ) {
		$post = self::page( Ability::int( $input, 'id' ) );
		if ( null === $post ) {
			return self::not_found();
		}
		$blocks = self::at(
			self::real( parse_blocks( $post->post_content ) ),
			array_map( 'intval', (array) ( $input['path'] ?? array() ) ),
			static function ( array $siblings, int $i ): array {
				array_splice( $siblings, $i, 1 );
				return $siblings;
			}
		);

		return null === $blocks ? self::bad_path() : self::save_blocks( $post, $blocks );
	}

	/**
	 * `pbs/move-section`.
	 *
	 * @param array<string, mixed> $input Input.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function move_section( array $input ) {
		$post = self::page( Ability::int( $input, 'id' ) );
		if ( null === $post ) {
			return self::not_found();
		}
		$blocks = self::real( parse_blocks( $post->post_content ) );
		$from   = Ability::int( $input, 'from' );
		$to     = Ability::int( $input, 'to' );
		if ( ! isset( $blocks[ $from ] ) || $to >= count( $blocks ) ) {
			return self::bad_path();
		}
		$moved = array_splice( $blocks, $from, 1 );
		array_splice( $blocks, $to, 0, $moved );

		return self::save_blocks( $post, $blocks );
	}

	/**
	 * `pbs/duplicate-page`.
	 *
	 * @param array<string, mixed> $input Input.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function duplicate_page( array $input ) {
		$post = self::page( Ability::int( $input, 'id' ) );
		if ( null === $post ) {
			return self::not_found();
		}
		$title = trim( (string) ( $input['title'] ?? '' ) );
		/* translators: %s: the original page's title. */
		$title = '' === $title ? sprintf( __( '%s (copy)', 'page-builder-sandwich' ), $post->post_title ) : $title;
		$id    = wp_insert_post(
			wp_slash(
				array(
					'post_type'    => 'page',
					'post_status'  => 'draft',
					'post_title'   => sanitize_text_field( $title ),
					'post_content' => $post->post_content,
					'post_parent'  => $post->post_parent,
				)
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		$template = get_page_template_slug( $post );
		if ( '' !== $template ) {
			update_post_meta( $id, '_wp_page_template', $template );
		}

		return self::page_summary( get_post( $id ) );
	}

	/**
	 * `pbs/delete-page`.
	 *
	 * @param array<string, mixed> $input Input.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function delete_page( array $input ) {
		$post = self::page( Ability::int( $input, 'id' ) );
		if ( null === $post ) {
			return self::not_found();
		}
		$trashed = wp_trash_post( $post->ID );

		return $trashed instanceof \WP_Post
			? array(
				'id'      => $post->ID,
				'trashed' => true,
			)
			: new \WP_Error( 'pbsw_trash_failed', __( 'The page could not be moved to the trash.', 'page-builder-sandwich' ), array( 'status' => 500 ) );
	}

	/**
	 * The `pbs/build-landing-page` prompt.
	 *
	 * @param array<string, mixed> $input Input.
	 * @return array<string, mixed>
	 */
	public static function landing_page_prompt( array $input ): array {
		$business = sanitize_textarea_field( (string) ( $input['business'] ?? '' ) );

		return array(
			'messages' => array(
				array(
					'role'    => 'user',
					'content' => array(
						'type' => 'text',
						'text' => sprintf(
							/* translators: %s: the business the page is for. */
							__( 'Build a landing page on this WordPress site for: %s. Steps: 1) call pbs-list-sections and choose 4 to 6 sections in a sensible order (hero first, a call to action last); 2) call pbs-create-page with a title and those sections, as a draft; 3) call pbs-get-page and rewrite every piece of placeholder text for this business with pbs-update-page (keep every block comment and tag as it is); 4) if pbs-generate-page is available, you may use it instead of steps 1 to 3; 5) report the page\'s edit link and do not publish it without asking.', 'page-builder-sandwich' ),
							$business
						),
					),
				),
			),
		);
	}

	/**
	 * The not-a-path error.
	 *
	 * @return \WP_Error
	 */
	private static function bad_path(): \WP_Error {
		return new \WP_Error( 'pbsw_bad_path', __( 'There is no block at that position. Read the page with pbs/get-page first.', 'page-builder-sandwich' ), array( 'status' => 400 ) );
	}

	/**
	 * `pbs/list-blocks`.
	 *
	 * @param array<string, mixed> $input Input.
	 * @return array<string, mixed>
	 */
	public static function list_blocks( array $input ): array {
		$search = strtolower( trim( (string) ( $input['search'] ?? '' ) ) );
		$out    = array();
		foreach ( \WP_Block_Type_Registry::get_instance()->get_all_registered() as $name => $type ) {
			if ( ! str_starts_with( (string) $name, 'pbs/' ) ) {
				continue;
			}
			$row = array(
				'name'        => (string) $name,
				'title'       => (string) $type->title,
				'category'    => (string) $type->category,
				'description' => (string) $type->description,
				'child_of'    => array_values( (array) ( $type->parent ?? array() ) ),
			);
			if ( '' !== $search && ! str_contains( strtolower( implode( ' ', array( $row['name'], $row['title'], $row['description'], implode( ' ', (array) $type->keywords ) ) ) ), $search ) ) {
				continue;
			}
			$out[] = $row;
		}
		usort( $out, static fn( array $a, array $b ): int => strcmp( $a['name'], $b['name'] ) );

		return array( 'blocks' => $out );
	}

	/**
	 * `pbs/get-block`.
	 *
	 * @param array<string, mixed> $input Input.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function get_block( array $input ) {
		$name = (string) ( $input['name'] ?? '' );
		$type = str_starts_with( $name, 'pbs/' ) ? \WP_Block_Type_Registry::get_instance()->get_registered( $name ) : null;
		if ( ! $type instanceof \WP_Block_Type ) {
			/* translators: %s: a block name. */
			return new \WP_Error( 'pbsw_unknown_block', sprintf( __( 'There is no block called "%s". See pbs/list-blocks.', 'page-builder-sandwich' ), $name ), array( 'status' => 404 ) );
		}
		$attributes = array();
		foreach ( (array) $type->attributes as $attr => $schema ) {
			if ( in_array( $attr, array( 'lock', 'metadata', 'className' ), true ) ) {
				continue;
			}
			$attributes[ $attr ] = array_intersect_key( (array) $schema, array_flip( array( 'type', 'enum', 'default', 'items' ) ) );
		}
		$example = is_array( $type->example ) ? (array) ( $type->example['attributes'] ?? array() ) : array();

		return array(
			'name'          => $name,
			'title'         => (string) $type->title,
			'description'   => (string) $type->description,
			'category'      => (string) $type->category,
			'attributes'    => $attributes,
			'supports'      => (array) $type->supports,
			'child_of'      => array_values( (array) ( $type->parent ?? array() ) ),
			'allowed_inner' => array_values( (array) ( $type->allowed_blocks ?? array() ) ),
			'dynamic'       => $type->is_dynamic(),
			'example'       => serialize_block(
				array(
					'blockName'    => $name,
					'attrs'        => $example,
					'innerBlocks'  => array(),
					'innerHTML'    => '',
					'innerContent' => array(),
				)
			),
		);
	}

	/**
	 * `pbs/add-section`.
	 *
	 * @param array<string, mixed> $input Input.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function add_section( array $input ) {
		$post = self::page( Ability::int( $input, 'id' ) );
		if ( null === $post ) {
			return self::not_found();
		}
		$markup = self::sections_markup( array( (string) ( $input['section'] ?? '' ) ) );
		if ( is_wp_error( $markup ) ) {
			return $markup;
		}
		$position = (string) ( $input['position'] ?? 'end' );
		if ( 'start' === $position ) {
			$content = $markup . $post->post_content;
		} elseif ( 'after' === $position ) {
			$blocks = array_values( array_filter( parse_blocks( $post->post_content ), static fn( array $b ): bool => null !== $b['blockName'] ) );
			$after  = Ability::int( $input, 'after' );
			if ( $after >= count( $blocks ) ) {
				return new \WP_Error( 'pbsw_bad_position', __( 'The page has no section at that position.', 'page-builder-sandwich' ), array( 'status' => 400 ) );
			}
			array_splice( $blocks, $after + 1, 0, parse_blocks( $markup ) );
			$content = serialize_blocks( $blocks );
		} else {
			$content = $post->post_content . $markup;
		}
		$id = wp_update_post(
			wp_slash(
				array(
					'ID'           => $post->ID,
					'post_content' => $content,
				)
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}

		return self::page_summary( get_post( $id ) );
	}

	/**
	 * `pbs/list-kits`.
	 *
	 * @param array<string, mixed> $input Input.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function list_kits( array $input ) {
		$data = Kits::catalogue( ! empty( $input['refresh'] ) );

		return isset( $data['failure'] ) ? self::kit_error( (array) $data['failure'] ) : $data;
	}

	/**
	 * `pbs/apply-kit`.
	 *
	 * @param array<string, mixed> $input Input.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function apply_kit( array $input ) {
		$slug = (string) ( $input['slug'] ?? '' );
		if ( 1 !== preg_match( '/^[a-z0-9][a-z0-9-]{1,60}$/', $slug ) ) {
			return new \WP_Error( 'pbsw_bad_kit', __( 'That is not a kit name. See pbs/list-kits.', 'page-builder-sandwich' ), array( 'status' => 400 ) );
		}
		$done = Kits::apply(
			$slug,
			array(
				'pages'   => Ability::strings( $input, 'pages' ),
				'publish' => ! empty( $input['publish'] ) && current_user_can( 'publish_pages' ),
				'front'   => ! empty( $input['front'] ) && current_user_can( 'manage_options' ),
				'menu'    => ! isset( $input['menu'] ) || ! empty( $input['menu'] ),
			)
		);
		if ( isset( $done['failure'] ) ) {
			return self::kit_error( (array) $done['failure'] );
		}
		if ( isset( $done['bad_kit'] ) ) {
			return new \WP_Error( 'bad_kit', __( 'The kit could not be read. Try again later.', 'page-builder-sandwich' ), array( 'status' => 502 ) );
		}

		return $done;
	}

	/**
	 * The block markup of some sections, in order.
	 *
	 * @param array<int, string> $names Section names.
	 * @return string|\WP_Error
	 */
	public static function sections_markup( array $names ) {
		$all = self::sections();
		$out = '';
		foreach ( $names as $name ) {
			if ( ! isset( $all[ $name ] ) ) {
				return new \WP_Error(
					'pbsw_unknown_section',
					/* translators: %s: a section name. */
					sprintf( __( 'There is no section called "%s". See pbs/list-sections.', 'page-builder-sandwich' ), $name ),
					array( 'status' => 400 )
				);
			}
			$out .= (string) $all[ $name ]['content'] . "\n\n";
		}

		return $out;
	}

	/**
	 * Every registered Page Builder Sandwich section (the free set, and the Pro library's when it
	 * is loaded), by name.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function sections(): array {
		$out = array();
		foreach ( \WP_Block_Patterns_Registry::get_instance()->get_all_registered() as $pattern ) {
			if ( in_array( Patterns::CATEGORY, (array) ( $pattern['categories'] ?? array() ), true ) ) {
				$out[ (string) $pattern['name'] ] = $pattern;
			}
		}
		ksort( $out );

		return $out;
	}

	/**
	 * May the current user edit this page?
	 *
	 * @param int $id Post ID.
	 * @return bool
	 */
	public static function can_edit_page( int $id ): bool {
		return null !== self::page( $id ) && current_user_can( 'edit_post', $id );
	}

	/**
	 * May the current user give a page this status?
	 *
	 * @param string $status Status.
	 * @return bool
	 */
	public static function can_set_status( string $status ): bool {
		return in_array( $status, array( 'publish', 'private' ), true ) ? current_user_can( 'publish_pages' ) : current_user_can( 'edit_pages' );
	}

	/**
	 * A page by ID, null when it is not a page.
	 *
	 * @param int $id Post ID.
	 * @return \WP_Post|null
	 */
	private static function page( int $id ): ?\WP_Post {
		$post = $id > 0 ? get_post( $id ) : null;

		return $post instanceof \WP_Post && 'page' === $post->post_type && 'trash' !== $post->post_status ? $post : null;
	}

	/**
	 * The fields every page answer carries.
	 *
	 * @param \WP_Post|null $post Page.
	 * @return array<string, mixed>
	 */
	private static function page_summary( $post ): array {
		if ( ! $post instanceof \WP_Post ) {
			return array();
		}

		return array(
			'id'        => $post->ID,
			'title'     => get_the_title( $post ),
			'status'    => $post->post_status,
			'link'      => (string) get_permalink( $post ),
			'edit_link' => (string) get_edit_post_link( $post->ID, 'raw' ),
		);
	}

	/**
	 * Input schema: one page ID.
	 *
	 * @return array<string, mixed>
	 */
	private static function id_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'id' => array(
					'type'    => 'integer',
					'minimum' => 1,
				),
			),
			'required'             => array( 'id' ),
			'additionalProperties' => false,
		);
	}

	/**
	 * Input schema: a page to create or update.
	 *
	 * @param bool $create Creating (title required, no id).
	 * @return array<string, mixed>
	 */
	public static function page_schema( bool $create ): array {
		$properties = array(
			'title'    => array( 'type' => 'string' ),
			'status'   => array(
				'type' => 'string',
				'enum' => self::STATUSES,
			),
			'sections' => array(
				'type'        => 'array',
				'items'       => array( 'type' => 'string' ),
				'description' => __( 'Names of sections in this site\'s section library, in page order. They come before any content given.', 'page-builder-sandwich' ),
			),
			'content'  => array(
				'type'        => 'string',
				'description' => __( 'Block markup (the format the block editor saves).', 'page-builder-sandwich' ),
			),
		);
		if ( ! $create ) {
			$properties = array(
				'id' => array(
					'type'    => 'integer',
					'minimum' => 1,
				),
			) + $properties;
		}

		return array(
			'type'                 => 'object',
			'properties'           => $properties,
			'required'             => $create ? array( 'title' ) : array( 'id' ),
			'additionalProperties' => false,
		);
	}

	/**
	 * A kit-library failure as an error the agent can explain.
	 *
	 * @param array<string, mixed> $failure Engine::call() result.
	 * @return \WP_Error
	 */
	private static function kit_error( array $failure ): \WP_Error {
		$response = Kits::failure( $failure );
		$data     = (array) $response->get_data();

		return new \WP_Error( (string) ( $data['code'] ?? 'unreachable' ), (string) ( $data['message'] ?? '' ), array( 'status' => $response->get_status() ) );
	}

	/**
	 * The not-found error.
	 *
	 * @return \WP_Error
	 */
	private static function not_found(): \WP_Error {
		return new \WP_Error( 'pbsw_not_found', __( 'There is no page with that ID.', 'page-builder-sandwich' ), array( 'status' => 404 ) );
	}

	/**
	 * The not-allowed error.
	 *
	 * @return \WP_Error
	 */
	private static function forbidden(): \WP_Error {
		return new \WP_Error( 'pbsw_forbidden', __( 'You are not allowed to do that to this page.', 'page-builder-sandwich' ), array( 'status' => 403 ) );
	}
}
