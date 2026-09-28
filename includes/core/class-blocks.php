<?php
/**
 * The P1 core blocks.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Core;

use ZinnDigital\PBS\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers pbs/row, pbs/column, pbs/button, pbs/icon, pbs/widget and pbs/sidebar.
 *
 * Every block is hybrid (docs/plugins-overhaul/plan/lanes/state/L08.md §P1.1): its save() stores
 * footprint-free fallback HTML that survives the plugin being turned off, and a render callback
 * REBUILDS the live HTML from the attributes and the inner blocks — it never echoes the saved
 * wrapper, so what the visitor gets always carries the current neutral prefix and sanitised
 * styles, whatever was stored.
 *
 * Styles are not enqueued here: the perf layer (includes/assets/perf/) loads each block's own
 * stylesheet only when that block renders, or folds it into the page's compiled stylesheet.
 */
final class Blocks {

	/** Editor script handle — wp-admin only, so it may carry the plugin's name. */
	public const EDITOR_HANDLE = 'pbsw-core-editor';

	/** Block directory names under blocks/. */
	public const NAMES = array( 'row', 'column', 'button', 'icon', 'widget', 'sidebar' );

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'init', array( self::class, 'register_blocks' ) );
		add_filter( 'wp_kses_allowed_html', array( self::class, 'allow_icon_svg' ), 10, 2 );
		add_action( 'enqueue_block_editor_assets', array( self::class, 'editor_data' ) );
		add_filter( 'wp_insert_post_data', array( self::class, 'widget_fallbacks' ), 10, 1 );
		add_filter( 'render_block_data', array( self::class, 'icon_display' ), 10, 3 );
	}

	/**
	 * A pbs/icon saved by 6.1 carries no `display`. At the TOP level of the content it is a block
	 * of its own (as a block theme lays out every top-level block) — 6.1 printed a bare inline
	 * span there, which the theme's layout could not give margins, so it sat at the page edge.
	 * Inside another block (a row or column from converted legacy content) it stays inline.
	 * Only the render sees this: the saved post is not changed, so 6.1 content stays valid.
	 *
	 * @param mixed $parsed_block Parsed block.
	 * @param mixed $source_block Unfiltered parsed block.
	 * @param mixed $parent_block The parent WP_Block, or null at the top level.
	 * @return mixed
	 */
	public static function icon_display( $parsed_block, $source_block = null, $parent_block = null ) {
		if ( ! is_array( $parsed_block ) || 'pbs/icon' !== ( $parsed_block['blockName'] ?? '' ) || isset( $parsed_block['attrs']['display'] ) || null !== $parent_block ) {
			return $parsed_block;
		}
		$parsed_block['attrs']['display'] = 'block';

		return $parsed_block;
	}

	/**
	 * On every save, refresh the deactivation fallback of the post's widget and sidebar blocks
	 * (Fallback::fill_widgets()), so one added in the editor is readable without the plugin too.
	 * Only content that holds such a block is touched, and only its snapshots change.
	 *
	 * @param mixed $data Slashed post data.
	 * @return mixed
	 */
	public static function widget_fallbacks( $data ) {
		if ( ! is_array( $data ) || ! isset( $data['post_content'] ) || ! is_string( $data['post_content'] ) ) {
			return $data;
		}
		$raw = wp_unslash( $data['post_content'] );
		if ( ! str_contains( $raw, 'wp:pbs/widget' ) && ! str_contains( $raw, 'wp:pbs/sidebar' ) ) {
			return $data;
		}
		$data['post_content'] = wp_slash( Fallback::fill_widgets( $raw, array( Fallback::class, 'render_widget_block' ) ) );

		return $data;
	}

	/**
	 * Give the editor the registered widgets and widget areas to choose from. Printed only
	 * where the editor script is (the block editor), never on the front end.
	 *
	 * @return void
	 */
	public static function editor_data(): void {
		global $wp_widget_factory, $wp_registered_sidebars;

		$widgets = array();
		foreach ( is_object( $wp_widget_factory ) ? (array) $wp_widget_factory->widgets : array() as $class_name => $widget ) {
			if ( $widget instanceof \WP_Widget && is_string( $class_name ) ) {
				$widgets[] = array(
					'class' => $class_name,
					'name'  => (string) $widget->name,
				);
			}
		}
		$sidebars = array();
		foreach ( (array) $wp_registered_sidebars as $id => $sidebar ) {
			$sidebars[] = array(
				'id'   => (string) $id,
				'name' => (string) ( $sidebar['name'] ?? $id ),
			);
		}

		wp_add_inline_script(
			self::EDITOR_HANDLE,
			'window.pbswCore = ' . wp_json_encode(
				array(
					'widgets'  => $widgets,
					'sidebars' => $sidebars,
				)
			) . ';',
			'before'
		);
	}

	/**
	 * Register the editor script and the blocks.
	 *
	 * @return void
	 */
	public static function register_blocks(): void {
		$asset_file = PBSW_DIR . 'build/core.asset.php';
		if ( is_readable( $asset_file ) ) {
			$asset = require $asset_file;
			wp_register_script(
				self::EDITOR_HANDLE,
				PBSW_URL . 'build/core.js',
				(array) ( $asset['dependencies'] ?? array() ),
				(string) ( $asset['version'] ?? PBSW_VERSION ),
				true
			);
			wp_set_script_translations( self::EDITOR_HANDLE, 'page-builder-sandwich', PBSW_DIR . 'languages' );
		}

		foreach ( self::NAMES as $name ) {
			register_block_type(
				PBSW_DIR . 'blocks/' . $name,
				array( 'render_callback' => array( self::class, 'render_' . $name ) )
			);
		}
	}

	/**
	 * Keep icon SVG in post content saved by authors without `unfiltered_html`. The list is
	 * Svg::kses_allowed() — the same allow-list the renderer applies, minus style and href.
	 *
	 * @param array<string, mixed> $tags    Allowed tags.
	 * @param string|mixed         $context kses context.
	 * @return array<string, mixed>
	 */
	public static function allow_icon_svg( $tags, $context ) {
		if ( 'post' !== $context || ! is_array( $tags ) ) {
			return $tags;
		}

		return array_merge( Svg::kses_allowed(), $tags );
	}

	/**
	 * The inner blocks, rendered one by one (their own render callbacks and filters run).
	 *
	 * @param \WP_Block|null $block Block.
	 * @return string
	 */
	private static function inner( $block ): string {
		if ( ! $block instanceof \WP_Block ) {
			return '';
		}
		$html = '';
		foreach ( $block->inner_blocks as $child ) {
			$html .= $child->render();
		}

		return $html;
	}

	/**
	 * Render callback for pbs/row.
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 * @param string               $content    Saved content (ignored — rebuilt).
	 * @param \WP_Block|null       $block      Block.
	 * @return string
	 */
	public static function render_row( array $attributes, string $content = '', $block = null ): string {
		return Render::row( $attributes, self::inner( $block ), Settings::prefix() );
	}

	/**
	 * Render callback for pbs/column.
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 * @param string               $content    Saved content (ignored — rebuilt).
	 * @param \WP_Block|null       $block      Block.
	 * @return string
	 */
	public static function render_column( array $attributes, string $content = '', $block = null ): string {
		return Render::column( $attributes, self::inner( $block ), Settings::prefix() );
	}

	/**
	 * Render callback for pbs/button.
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 * @return string
	 */
	public static function render_button( array $attributes ): string {
		return Render::button( $attributes, Settings::prefix() );
	}

	/**
	 * Render callback for pbs/icon.
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 * @return string
	 */
	public static function render_icon( array $attributes ): string {
		return Render::icon( $attributes, Settings::prefix() );
	}

	/**
	 * Render callback for pbs/widget.
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 * @return string
	 */
	public static function render_widget( array $attributes ): string {
		$instance = is_array( $attributes['instance'] ?? null ) ? $attributes['instance'] : array();

		// Widgets::widget() already prints one wrapper carrying the block's class (minimal DOM).
		return Widgets::widget( (string) ( $attributes['widget'] ?? '' ), $instance );
	}

	/**
	 * Render callback for pbs/sidebar.
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 * @return string
	 */
	public static function render_sidebar( array $attributes ): string {
		return Render::container(
			'sidebar',
			Widgets::sidebar( (string) ( $attributes['sidebar'] ?? '' ) ),
			Settings::prefix()
		);
	}
}
