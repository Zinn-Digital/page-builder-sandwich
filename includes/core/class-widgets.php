<?php
/**
 * Widget and sidebar output for pbsw/widget, pbsw/sidebar and their legacy shortcodes.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders a registered widget or a registered sidebar.
 *
 * ⛔ Only a class registered with the widget factory can be rendered: the legacy shortcode
 * accepted any `class_exists()` name, so any autoloadable class could be instantiated from post
 * content. And the instance is author input, so it goes through the widget's OWN update() —
 * the same sanitising WordPress applies when the widget is saved in the Customizer — with
 * `unfiltered_html` refused for the duration, because the user viewing the page (perhaps an
 * administrator) is not the user who wrote it.
 */
final class Widgets {

	/**
	 * Counter for unique widget ids on a page.
	 *
	 * @var int
	 */
	private static int $seq = 0;

	/**
	 * The synced patterns being rendered, innermost last; null for one that could not be resolved.
	 *
	 * @var array<int, \WP_Post|null>
	 */
	private static array $patterns = array();

	/**
	 * Whether a post's last editor is trusted, by post id, for this request.
	 *
	 * @var array<int, bool>
	 */
	private static array $editor_trust = array();

	/**
	 * Track synced patterns: core renders them without changing the global post.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'render_block_data', array( self::class, 'enter_pattern' ), PHP_INT_MAX );
		add_filter( 'render_block', array( self::class, 'leave_pattern' ), PHP_INT_MIN, 2 );
		add_action( 'wp_after_insert_post', array( self::class, 'note_editor' ), 1, 2 );
		add_action( 'clean_post_cache', array( self::class, 'forget_editor' ) );
	}

	/**
	 * The registered widget object for a class name, or null.
	 *
	 * @param string $class_name Widget class.
	 * @return \WP_Widget|null
	 */
	public static function registered( string $class_name ): ?\WP_Widget {
		global $wp_widget_factory;
		if ( '' === $class_name || ! is_object( $wp_widget_factory ) || ! isset( $wp_widget_factory->widgets[ $class_name ] ) ) {
			return null;
		}
		$widget = $wp_widget_factory->widgets[ $class_name ];

		return $widget instanceof \WP_Widget ? $widget : null;
	}

	/**
	 * A widget's HTML (not wrapped).
	 *
	 * @param string               $class_name Widget class.
	 * @param array<string, mixed> $instance   Untrusted instance values.
	 * @return string
	 */
	public static function widget( string $class_name, array $instance ): string {
		$widget = self::registered( $class_name );
		if ( null === $widget ) {
			return '';
		}

		$deny = static function ( $caps, $cap ) {
			return 'unfiltered_html' === $cap ? array( 'do_not_allow' ) : $caps;
		};
		add_filter( 'map_meta_cap', $deny, 10, 2 );
		try {
			$clean = $widget->update( self::scalars( $instance ), array() );
		} finally {
			remove_filter( 'map_meta_cap', $deny, 10 );
		}
		if ( ! is_array( $clean ) ) {
			return '';
		}

		++self::$seq;
		$classname = (string) ( $widget->widget_options['classname'] ?? '' );
		ob_start();
		the_widget(
			$class_name,
			$clean,
			array(
				'widget_id'     => \ZinnDigital\PBS\Settings::prefix() . '-w-' . self::$seq,
				// The block's own class sits on the widget's wrapper: one element, no extra <div> (pbs-p9).
				'before_widget' => '<div class="' . esc_attr( trim( 'widget ' . $classname . ' ' . \ZinnDigital\PBS\Frontend::cls( 'widget' ) ) ) . '">',
				'after_widget'  => '</div>',
				'before_title'  => '<h2 class="widget-title">',
				'after_title'   => '</h2>',
			)
		);

		$html = (string) ob_get_clean();

		// The markup is produced and escaped by core and by the widget's own code. An author who may not set widgets or post raw HTML chose this instance, so their output stays filtered.
		return self::author_is_trusted( $class_name, $instance ) ? $html : Dev_Compat::filter_output( $html );
	}

	/**
	 * Whether the people behind the content that holds this block could already place this markup:
	 * they may manage widgets or post unfiltered HTML. Both the author and the last editor must
	 * qualify, because on multisite an Editor without `unfiltered_html` can change an admin's post.
	 * Inside a synced pattern that is the pattern's people, not the page's; a pattern that cannot
	 * be resolved, or whose author no longer exists, is not trusted. No post in context means a template or widget area, which
	 * only `edit_theme_options` can change, except on a REST request, where the block renderer
	 * lets any editor preview attributes they chose.
	 *
	 * A block in a block template or template part is judged by that template's people instead; see template_trust().
	 *
	 * @param string               $class_name Widget class being rendered.
	 * @param array<string, mixed> $instance   Instance from the block.
	 * @return bool
	 */
	private static function author_is_trusted( string $class_name, array $instance ): bool {
		$in_template = self::template_trust(
			'pbsw/widget',
			static fn( array $attrs ): bool => (string) ( $attrs['widget'] ?? '' ) === $class_name
				&& ( is_array( $attrs['instance'] ?? null ) ? $attrs['instance'] : array() ) === $instance
		);
		if ( null !== $in_template ) {
			return $in_template;
		}

		if ( array() !== self::$patterns ) {
			$post = end( self::$patterns );
			if ( ! $post instanceof \WP_Post ) {
				return false;
			}
		} else {
			$post = get_post();
			if ( ! $post instanceof \WP_Post ) {
				return ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) && ! self::rendering_template();
			}
		}

		if ( ! self::user_is_trusted( (int) $post->post_author ) ) {
			return false;
		}

		$autosave = array() === self::$patterns ? self::previewed_autosave( $post ) : null;

		return null !== $autosave
			? self::user_is_trusted( (int) $autosave->post_author )
			: self::last_editor_is_trusted( $post );
	}

	/**
	 * The autosave this request is rendering in place of the post, if any. Core's preview shows the
	 * newest autosave by anyone, so the same lookup is used here, and that autosave's author is the
	 * person whose content is on screen. Only the post core swapped counts: another post in a query
	 * loop on the preview page still renders its published content.
	 *
	 * @param \WP_Post $post Post in context.
	 * @return \WP_Post|null
	 */
	private static function previewed_autosave( \WP_Post $post ): ?\WP_Post {
		global $wp_the_query;

		if (
			! $wp_the_query instanceof \WP_Query
			|| ! $wp_the_query->is_preview()
			|| false === has_filter( 'the_preview', '_set_preview' )
			|| ! isset( $wp_the_query->posts[0]->ID )
			|| (int) $wp_the_query->posts[0]->ID !== (int) $post->ID
			|| ! current_user_can( 'edit_post', $post->ID )
		) {
			return null;
		}

		$autosave = wp_get_post_autosave( $post->ID );

		return $autosave instanceof \WP_Post ? $autosave : null;
	}

	/**
	 * Whether the block template itself is being rendered, rather than a post's content inside it.
	 *
	 * @return bool
	 */
	private static function rendering_template(): bool {
		global $_wp_current_template_id, $_wp_current_template_content;

		return array() === self::$patterns
			&& ! ( defined( 'REST_REQUEST' ) && REST_REQUEST )
			&& ! doing_filter( 'the_content' )
			&& ! doing_filter( 'widget_block_content' )
			&& is_string( $_wp_current_template_id ) && '' !== $_wp_current_template_id
			&& is_string( $_wp_current_template_content ) && '' !== $_wp_current_template_content;
	}

	/**
	 * Trust for a block that the current template's own parsed block tree holds: that template's
	 * author and last editor, and those of every template part on the way to the block. Null when
	 * the template is not what is rendering or does not hold this block, so the caller judges the
	 * post instead. A page cannot borrow this: its content renders inside the_content, and a part
	 * it references is reached from the page, never from this tree. If the same block sits in a
	 * trusted and an untrusted place, the answer is false.
	 *
	 * @param string   $name    Block name.
	 * @param callable $matches Receives a parsed block's attributes; true when it is this block.
	 * @return bool|null
	 */
	public static function template_trust( string $name, callable $matches ): ?bool {
		global $_wp_current_template_id, $_wp_current_template_content;

		if ( ! self::rendering_template() ) {
			return null;
		}
		$template = get_block_template( $_wp_current_template_id, 'wp_template' );

		return self::tree_trust( parse_blocks( $_wp_current_template_content ), $name, $matches, self::template_is_trusted( $template ), 0 );
	}

	/**
	 * Walk a parsed tree, following template parts and registered patterns.
	 *
	 * @param array<int, array<string, mixed>> $blocks  Parsed blocks.
	 * @param string                           $name    Block name.
	 * @param callable                         $matches Attribute matcher.
	 * @param bool                             $trusted Trust of the content holding $blocks.
	 * @param int                              $depth   Nesting guard.
	 * @return bool|null
	 */
	private static function tree_trust( array $blocks, string $name, callable $matches, bool $trusted, int $depth ): ?bool {
		if ( $depth > 16 ) {
			return null;
		}
		$found = null;
		foreach ( $blocks as $block ) {
			$attrs  = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
			$result = Block_Names::name( $name ) === Block_Names::name( (string) ( $block['blockName'] ?? '' ) ) && $matches( $attrs ) ? $trusted : null;
			$nested = array( self::tree_trust( (array) ( $block['innerBlocks'] ?? array() ), $name, $matches, $trusted, $depth + 1 ) );
			if ( 'core/template-part' === ( $block['blockName'] ?? '' ) && is_string( $attrs['slug'] ?? null ) ) {
				$theme = is_string( $attrs['theme'] ?? null ) ? $attrs['theme'] : get_stylesheet();
				$part  = get_block_template( $theme . '//' . $attrs['slug'], 'wp_template_part' );
				if ( $part instanceof \WP_Block_Template ) {
					$nested[] = self::tree_trust( parse_blocks( (string) $part->content ), $name, $matches, $trusted && self::template_is_trusted( $part ), $depth + 1 );
				}
			} elseif ( 'core/pattern' === ( $block['blockName'] ?? '' ) && is_string( $attrs['slug'] ?? null ) ) {
				$pattern = \WP_Block_Patterns_Registry::get_instance()->get_registered( $attrs['slug'] );
				if ( is_array( $pattern ) ) {
					$nested[] = self::tree_trust( parse_blocks( (string) ( $pattern['content'] ?? '' ) ), $name, $matches, $trusted, $depth + 1 );
				}
			}
			foreach ( array_merge( array( $result ), $nested ) as $answer ) {
				if ( false === $answer ) {
					return false;
				}
				$found = $found ?? $answer;
			}
		}

		return $found;
	}

	/**
	 * A template from theme or plugin files has no database row and only code put it there. A saved
	 * one needs a trusted author and last editor. One that cannot be resolved is not trusted.
	 *
	 * @param mixed $template Result of get_block_template().
	 * @return bool
	 */
	private static function template_is_trusted( $template ): bool {
		if ( ! $template instanceof \WP_Block_Template ) {
			return false;
		}
		if ( empty( $template->wp_id ) ) {
			return true;
		}
		$post = get_post( (int) $template->wp_id );

		return $post instanceof \WP_Post && self::user_is_trusted( (int) $post->post_author ) && self::last_editor_is_trusted( $post );
	}

	/**
	 * Whether an existing user may manage widgets or post unfiltered HTML.
	 *
	 * @param int $user_id User id.
	 * @return bool
	 */
	private static function user_is_trusted( int $user_id ): bool {
		return $user_id > 0
			&& false !== get_userdata( $user_id )
			&& ( user_can( $user_id, 'edit_theme_options' ) || user_can( $user_id, 'unfiltered_html' ) );
	}

	/**
	 * Whether the last person to change this post is trusted. Core writes `_edit_last` from the
	 * classic editor only, so a block editor save can leave it naming someone earlier: the newest
	 * revision must qualify too. A page saved before this version often has no `_edit_last`, and
	 * must keep working: with no stamp the newest revision decides, and with no revision either
	 * the post author does. A stamp that exists is always judged, so an untrusted one stays filtered.
	 *
	 * @param \WP_Post $post Post.
	 * @return bool
	 */
	private static function last_editor_is_trusted( \WP_Post $post ): bool {
		if ( isset( self::$editor_trust[ $post->ID ] ) ) {
			return self::$editor_trust[ $post->ID ];
		}

		$has_stamp = metadata_exists( 'post', $post->ID, '_edit_last' );
		$stamp     = (int) get_post_meta( $post->ID, '_edit_last', true );
		$newest    = self::newest_saved_revision( $post->ID );

		if ( $newest instanceof \WP_Post ) {
			$trusted = self::user_is_trusted( (int) $newest->post_author )
				&& ( ! $has_stamp || self::user_is_trusted( $stamp ) );
		} else {
			$trusted = self::user_is_trusted( $has_stamp ? $stamp : (int) $post->post_author );
		}

		self::$editor_trust[ $post->ID ] = $trusted;

		return $trusted;
	}

	/**
	 * The newest revision that records a real save. An autosave is skipped: it is unpublished, so
	 * it is not what the page renders. Each user keeps at most one, so this stops after a few rows.
	 *
	 * @param int $post_id Post id.
	 * @return \WP_Post|null
	 */
	private static function newest_saved_revision( int $post_id ): ?\WP_Post {
		for ( $offset = 0; ; ++$offset ) {
			$revisions = wp_get_post_revisions(
				$post_id,
				array(
					'numberposts'   => 1,
					'offset'        => $offset,
					'check_enabled' => false,
				)
			);
			$revision  = reset( $revisions );
			if ( ! $revision instanceof \WP_Post ) {
				return null;
			}
			if ( false === wp_is_post_autosave( $revision ) ) {
				return $revision;
			}
		}
	}

	/**
	 * Record who saved a post, on every save path: core does not do this for a REST save.
	 *
	 * @param int      $post_id Post id.
	 * @param \WP_Post $post    Post.
	 * @return void
	 */
	public static function note_editor( $post_id, $post ): void {
		$user_id = get_current_user_id();
		if ( $user_id > 0 && $post instanceof \WP_Post && 'revision' !== $post->post_type ) {
			update_post_meta( (int) $post_id, '_edit_last', $user_id );
			self::forget_editor( (int) $post_id );
		}
	}

	/**
	 * Drop the remembered answer for a post that changed.
	 *
	 * @param int $post_id Post id.
	 * @return void
	 */
	public static function forget_editor( $post_id ): void {
		unset( self::$editor_trust[ (int) $post_id ] );
	}

	/**
	 * Note the synced pattern being entered, so a block inside it is judged by its author.
	 *
	 * @param array<string, mixed> $block Parsed block.
	 * @return array<string, mixed>
	 */
	public static function enter_pattern( array $block ): array {
		if ( 'core/block' === ( $block['blockName'] ?? '' ) ) {
			$ref              = absint( $block['attrs']['ref'] ?? 0 );
			$post             = $ref > 0 ? get_post( $ref ) : null;
			self::$patterns[] = $post instanceof \WP_Post && 'wp_block' === $post->post_type ? $post : null;
		}

		return $block;
	}

	/**
	 * Leave the synced pattern entered by enter_pattern().
	 *
	 * @param string               $content Rendered block.
	 * @param array<string, mixed> $block   Parsed block.
	 * @return string
	 */
	public static function leave_pattern( $content, array $block ) {
		if ( 'core/block' === ( $block['blockName'] ?? '' ) ) {
			array_pop( self::$patterns );
		}

		return $content;
	}

	/**
	 * A registered, active sidebar's HTML (not wrapped).
	 *
	 * @param string $id Sidebar id.
	 * @return string
	 */
	public static function sidebar( string $id ): string {
		$id = sanitize_key( $id );
		if ( '' === $id || ! is_registered_sidebar( $id ) || ! is_active_sidebar( $id ) ) {
			return '';
		}
		ob_start();
		dynamic_sidebar( $id );

		// The markup is produced and escaped by core and by each widget's own code.
		return (string) ob_get_clean();
	}

	/**
	 * Keep scalar (and one level of scalar-list) values only: a widget instance is a flat form.
	 *
	 * @param array<string, mixed> $instance Instance.
	 * @return array<string, mixed>
	 */
	private static function scalars( array $instance ): array {
		$out = array();
		foreach ( $instance as $key => $value ) {
			if ( ! is_string( $key ) ) {
				continue;
			}
			if ( is_scalar( $value ) ) {
				$out[ $key ] = $value;
			} elseif ( is_array( $value ) ) {
				$out[ $key ] = array_filter( $value, 'is_scalar' );
			}
		}

		return $out;
	}
}
