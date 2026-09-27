<?php
/**
 * Sandwich Studio: the full-screen visual editor (features pbs-f2, pbs-g1, pbs-d7, pbs-r15,
 * pbs-r16, pbs-r17).
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Studio admin page, and the three doors into it.
 *
 * ⭐ STUDIO EDITS THE SAME BLOCKS AS THE NORMAL EDITOR, THROUGH THE SAME DOORS. The React app
 * (src/studio/) edits the post as a core-data entity, so every save is a core REST request with
 * core's own permission checks, revisions and autosaves; nothing here stores content anywhere
 * else. That is what lets a user switch between Studio and the block editor at any moment.
 *
 * ⭐ "THEME-ACCURATE" IS WordPress's OWN SETTINGS, NOT A COPY OF THEM. The canvas is an iframe
 * fed by get_block_editor_settings() for this post — the same call the block editor makes — so
 * theme.json styles, the theme's editor styles and every block's canvas assets
 * (`__unstableResolvedAssets`) reach it exactly as they reach the normal editor.
 *
 * The page has no menu entry: it is reached from a row action on post lists, an admin-bar button
 * on a singular front-end view, and a button in the block editor (src/studio/editor-button/).
 */
final class Studio {

	/** Admin page slug (admin.php?page=pbs-studio&post=<id>). */
	public const PAGE = 'pbs-studio';

	/** The Studio app's script/style handle (wp-admin only, so it may carry the name). */
	public const HANDLE = 'pbsw-studio';

	/** The block editor's "Edit in Sandwich Studio" button. */
	public const BUTTON_HANDLE = 'pbsw-studio-button';

	/** Seconds between server autosaves while there are unsaved changes. */
	public const AUTOSAVE_SECONDS = 20;

	/** Seconds between local crash-recovery snapshots while there are unsaved changes. */
	public const SNAPSHOT_SECONDS = 2;

	/**
	 * The page hook suffix.
	 *
	 * @var string
	 */
	private static string $hook = '';

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_filter( 'post_row_actions', array( self::class, 'row_action' ), 10, 2 );
		add_filter( 'page_row_actions', array( self::class, 'row_action' ), 10, 2 );
		add_action( 'admin_bar_menu', array( self::class, 'admin_bar' ), 81 );
		add_action( 'init', array( self::class, 'register_scripts' ) );
		add_action( 'enqueue_block_editor_assets', array( self::class, 'enqueue_editor_button' ) );
	}

	/**
	 * Studio URL for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function url( int $post_id ): string {
		return add_query_arg(
			array(
				'page' => self::PAGE,
				'post' => $post_id,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Whether Studio can open this post for the current user: a post type the block editor edits
	 * over REST, and edit permission on THIS post (never a type-wide check).
	 *
	 * @param \WP_Post|null $post Post.
	 * @return bool
	 */
	public static function can_open( ?\WP_Post $post ): bool {
		if ( ! $post instanceof \WP_Post ) {
			return false;
		}
		$type = get_post_type_object( $post->post_type );
		if ( ! $type || empty( $type->show_in_rest ) || 'attachment' === $post->post_type ) {
			return false;
		}
		if ( ! use_block_editor_for_post( $post ) ) {
			return false;
		}
		return current_user_can( 'edit_post', $post->ID );
	}

	/**
	 * Register the hidden page.
	 *
	 * An empty parent keeps it out of every menu. The capability is the loosest one Studio could
	 * ever need; the real check is per post, in load().
	 *
	 * @return void
	 */
	public static function menu(): void {
		self::$hook = (string) add_submenu_page(
			'',
			__( 'Sandwich Studio', 'page-builder-sandwich' ),
			__( 'Sandwich Studio', 'page-builder-sandwich' ),
			'edit_posts',
			self::PAGE,
			array( self::class, 'render' )
		);
		if ( '' !== self::$hook ) {
			add_action( 'load-' . self::$hook, array( self::class, 'load' ) );
		}
	}

	/**
	 * The post the request asks for.
	 *
	 * @return \WP_Post|null
	 */
	private static function requested_post(): ?\WP_Post {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a read-only screen parameter; every write goes through REST with its own nonce.
		$id   = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0;
		$post = $id ? get_post( $id ) : null;
		return $post instanceof \WP_Post ? $post : null;
	}

	/**
	 * Prepare the screen: permission, full-screen chrome, block editor assets and the app's data.
	 *
	 * @return void
	 */
	public static function load(): void {
		global $title;

		$post = self::requested_post();
		if ( ! $post ) {
			wp_die( esc_html__( 'That page does not exist, or it has been deleted.', 'page-builder-sandwich' ), 404 );
		}
		if ( ! self::can_open( $post ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to edit this item in Sandwich Studio.', 'page-builder-sandwich' ), 403 );
		}

		// A hidden page has no menu title for admin-header.php to print.
		$title = __( 'Sandwich Studio', 'page-builder-sandwich' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- admin-header.php reads it; a hidden page never sets it.

		$screen = get_current_screen();
		if ( $screen ) {
			$screen->is_block_editor( true );
		}

		add_filter( 'admin_body_class', array( self::class, 'body_class' ) );
		add_filter( 'screen_options_show_screen', '__return_false' );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		// Studio renders its own command palette (with Studio's commands); core's admin-wide one
		// (WordPress 6.9+) would open a second palette on the same Ctrl+K.
		remove_action( 'admin_enqueue_scripts', 'wp_enqueue_command_palette_assets' );

		self::enqueue( $post );
	}

	/**
	 * Body classes for the full-screen layout.
	 *
	 * @param string $classes Existing classes.
	 * @return string
	 */
	public static function body_class( string $classes ): string {
		return $classes . ' is-fullscreen-mode pbsw-studio-screen';
	}

	/**
	 * Register the Studio and editor-button bundles.
	 *
	 * @return void
	 */
	public static function register_scripts(): void {
		foreach ( array(
			self::HANDLE        => 'studio',
			self::BUTTON_HANDLE => 'studio-button',
		) as $handle => $name ) {
			$asset_file = PBSW_DIR . 'build/' . $name . '.asset.php';
			if ( ! is_readable( $asset_file ) ) {
				continue;
			}
			$asset = require $asset_file;
			$deps  = (array) ( $asset['dependencies'] ?? array() );
			if ( self::HANDLE === $handle ) {
				// Read as window.wp.blockLibrary (src/studio/index.js), so the build cannot list it.
				$deps[] = 'wp-block-library';
				$deps[] = 'wp-format-library';
			}
			wp_register_script(
				$handle,
				PBSW_URL . 'build/' . $name . '.js',
				array_values( array_unique( $deps ) ),
				(string) ( $asset['version'] ?? PBSW_VERSION ),
				true
			);
			wp_set_script_translations( $handle, 'page-builder-sandwich', PBSW_DIR . 'languages' );
			// wp-scripts names a stylesheet imported as style.scss "style-<entry>.css".
			$css = 'build/style-' . $name . '.css';
			if ( ! is_readable( PBSW_DIR . $css ) ) {
				$css = 'build/' . $name . '.css';
			}
			if ( is_readable( PBSW_DIR . $css ) ) {
				$deps = self::HANDLE === $handle
					? array( 'wp-components', 'wp-block-editor', 'wp-edit-blocks', 'wp-format-library', 'wp-commands' )
					: array();
				wp_register_style( $handle, PBSW_URL . $css, $deps, (string) ( $asset['version'] ?? PBSW_VERSION ) );
				wp_style_add_data( $handle, 'rtl', 'replace' );
			}
		}
	}

	/**
	 * Enqueue everything the Studio screen needs, mirroring wp-admin/edit-form-blocks.php.
	 *
	 * @param \WP_Post $post Post being edited.
	 * @return void
	 */
	private static function enqueue( \WP_Post $post ): void {
		if ( ! wp_script_is( self::HANDLE, 'registered' ) ) {
			wp_die( esc_html__( 'Sandwich Studio is not built. Reinstall the plugin.', 'page-builder-sandwich' ), 500 );
		}

		$context   = new \WP_Block_Editor_Context( array( 'post' => $post ) );
		$type      = get_post_type_object( $post->post_type );
		$rest_base = ! empty( $type->rest_base ) ? $type->rest_base : $post->post_type;
		$rest_path = rest_get_route_for_post( $post );

		block_editor_rest_api_preload(
			array(
				'/wp/v2/types?context=view',
				'/wp/v2/taxonomies?context=view',
				add_query_arg( 'context', 'edit', $rest_path ),
				sprintf( '/wp/v2/types/%s?context=edit', $post->post_type ),
				'/wp/v2/users/me',
				array( rest_get_route_for_post_type_items( 'attachment' ), 'OPTIONS' ),
				array( rest_get_route_for_post_type_items( 'wp_block' ), 'OPTIONS' ),
				sprintf( '%s/autosaves?context=edit', $rest_path ),
				'/wp/v2/block-patterns/categories',
			),
			$context
		);

		wp_add_inline_script(
			'wp-blocks',
			sprintf( 'wp.blocks.setCategories( %s );', wp_json_encode( get_block_categories( $post ), JSON_HEX_TAG | JSON_UNESCAPED_SLASHES ) ),
			'after'
		);
		wp_add_inline_script(
			'wp-blocks',
			'wp.blocks.unstable__bootstrapServerSideBlockDefinitions(' . wp_json_encode( get_block_editor_server_block_settings(), JSON_HEX_TAG | JSON_UNESCAPED_SLASHES ) . ');'
		);
		if ( function_exists( 'get_all_registered_block_bindings_sources' ) ) {
			$sources = array();
			foreach ( get_all_registered_block_bindings_sources() as $source ) {
				$sources[] = array(
					'name'        => $source->name,
					'label'       => $source->label,
					'usesContext' => $source->uses_context,
				);
			}
			if ( $sources ) {
				wp_add_inline_script(
					'wp-blocks',
					sprintf( 'for ( const source of %s ) { wp.blocks.registerBlockBindingsSource( source ); }', wp_json_encode( $sources, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES ) )
				);
			}
		}

		wp_enqueue_media( array( 'post' => $post->ID ) );
		wp_enqueue_editor();
		wp_enqueue_script( self::HANDLE );
		wp_enqueue_style( self::HANDLE );

		/** This action is documented in wp-admin/edit-form-blocks.php */
		do_action( 'enqueue_block_editor_assets' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core's own hook, fired as the block editor fires it.

		$settings = get_block_editor_settings(
			array(
				'titlePlaceholder'                      => apply_filters( 'enter_title_here', __( 'Add title', 'page-builder-sandwich' ), $post ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core filter.
				'bodyPlaceholder'                       => apply_filters( 'write_your_story', __( 'Type / to choose a block', 'page-builder-sandwich' ), $post ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core filter.
				'richEditingEnabled'                    => user_can_richedit(),
				'supportsLayout'                        => wp_theme_has_theme_json(),
				'supportsTemplateMode'                  => false,
				'__experimentalAdditionalBlockPatterns' => \WP_Block_Patterns_Registry::get_instance()->get_all_registered( true ),
				'__experimentalAdditionalBlockPatternCategories' => \WP_Block_Pattern_Categories_Registry::get_instance()->get_all_registered( true ),
			),
			$context
		);
		if ( ! empty( $type->template ) ) {
			$settings['template']     = $type->template;
			$settings['templateLock'] = ! empty( $type->template_lock ) ? $type->template_lock : false;
		}

		$lock_user = wp_check_post_lock( $post->ID );
		if ( ! $lock_user ) {
			wp_set_post_lock( $post->ID );
		}
		$lock_name = $lock_user ? get_the_author_meta( 'display_name', (int) $lock_user ) : '';

		$boot = array(
			'postId'            => $post->ID,
			'postType'          => $post->post_type,
			'userId'            => get_current_user_id(),
			'restBase'          => $rest_base,
			'typeLabel'         => $type ? $type->labels->singular_name : $post->post_type,
			'canPublish'        => $type ? current_user_can( $type->cap->publish_posts ) : false,
			'supportsRevisions' => post_type_supports( $post->post_type, 'revisions' ),
			'editUrl'           => (string) get_edit_post_link( $post->ID, 'raw' ),
			'viewUrl'           => (string) get_permalink( $post ),
			'studioUrl'         => admin_url( 'admin.php?page=' . self::PAGE ),
			'listUrl'           => admin_url( 'edit.php?post_type=' . $post->post_type ),
			'site'              => home_url( '/' ),
			'autosaveInterval'  => self::AUTOSAVE_SECONDS,
			'snapshotInterval'  => self::SNAPSHOT_SECONDS,
			'lockedBy'          => $lock_name,
			'safeMode'          => Safe_Mode::state(),
			'settings'          => $settings,
		);

		wp_add_inline_script(
			self::HANDLE,
			'window.pbswStudio = ' . wp_json_encode( $boot, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR ) . ';',
			'before'
		);
	}

	/**
	 * Print the mount point. load() already refused anyone who may not edit this post.
	 *
	 * @return void
	 */
	public static function render(): void {
		$post = self::requested_post();
		if ( ! self::can_open( $post ) ) {
			return;
		}
		echo '<div id="pbsw-studio-root" class="pbsw-studio-root"><p class="pbsw-studio-loading">'
			. esc_html__( 'Loading Sandwich Studio…', 'page-builder-sandwich' )
			. '</p></div><noscript><p>'
			. esc_html__( 'Sandwich Studio needs JavaScript. Use the normal editor instead.', 'page-builder-sandwich' )
			. '</p></noscript>';
	}

	/**
	 * "Edit with Sandwich Studio" on post and page lists.
	 *
	 * @param array<string, string> $actions Row actions.
	 * @param \WP_Post              $post    Row post.
	 * @return array<string, string>
	 */
	public static function row_action( $actions, $post ) {
		if ( ! is_array( $actions ) || ! $post instanceof \WP_Post || 'trash' === $post->post_status || ! self::can_open( $post ) ) {
			return $actions;
		}
		$link = sprintf(
			'<a href="%1$s" aria-label="%2$s">%3$s</a>',
			esc_url( self::url( $post->ID ) ),
			/* translators: %s: post title. */
			esc_attr( sprintf( __( 'Edit “%s” with Sandwich Studio', 'page-builder-sandwich' ), get_the_title( $post ) ) ),
			esc_html__( 'Edit with Sandwich Studio', 'page-builder-sandwich' )
		);
		$out = array();
		foreach ( $actions as $key => $html ) {
			$out[ $key ] = $html;
			if ( 'edit' === $key ) {
				$out['pbsw_studio'] = $link;
			}
		}
		if ( ! isset( $out['pbsw_studio'] ) ) {
			$out['pbsw_studio'] = $link;
		}
		return $out;
	}

	/**
	 * Admin-bar button on a singular front-end view. Shown to a logged-in editor only — a visitor
	 * never gets an admin bar, so nothing of this reaches the public page (ADR 0033).
	 *
	 * @param \WP_Admin_Bar $bar Admin bar.
	 * @return void
	 */
	public static function admin_bar( $bar ): void {
		if ( is_admin() || ! is_singular() || ! $bar instanceof \WP_Admin_Bar ) {
			return;
		}
		$post = get_queried_object();
		if ( ! $post instanceof \WP_Post || ! self::can_open( $post ) ) {
			return;
		}
		$bar->add_node(
			array(
				'id'    => 'pbsw-studio',
				'title' => esc_html__( 'Edit with Sandwich Studio', 'page-builder-sandwich' ),
				'href'  => self::url( $post->ID ),
			)
		);
	}

	/**
	 * The "Edit in Sandwich Studio" button, in the normal block editor for a post (not in Studio
	 * itself, the site editor or the widgets screen).
	 *
	 * @return void
	 */
	public static function enqueue_editor_button(): void {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || 'post' !== $screen->base || ! $screen->is_block_editor() ) {
			return;
		}
		$post = get_post();
		if ( ! self::can_open( $post instanceof \WP_Post ? $post : null ) || ! wp_script_is( self::BUTTON_HANDLE, 'registered' ) ) {
			return;
		}
		wp_enqueue_script( self::BUTTON_HANDLE );
		wp_add_inline_script(
			self::BUTTON_HANDLE,
			'window.pbswStudioButton = ' . wp_json_encode( array( 'studioUrl' => admin_url( 'admin.php?page=' . self::PAGE ) ), JSON_HEX_TAG | JSON_UNESCAPED_SLASHES ) . ';',
			'before'
		);
	}
}
