<?php
/**
 * Per-block conditional assets (pbs-p1).
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Assets\Perf;

use ZinnDigital\PBS\Assets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Each block's stylesheet loads only on a page where that block renders.
 *
 * Three paths, cheapest first:
 * 1. A singular page gets ONE compiled stylesheet holding exactly the blocks it uses
 *    (Page_Css); a block covered by it enqueues nothing.
 * 2. Archives, the blog index and search pre-scan the main query's posts at `template_redirect`
 *    (before a block theme renders anything) so the stylesheets land in `<head>` rather than after
 *    the content (a late stylesheet is a
 *    flash of unstyled content and a layout shift).
 * 3. Anything else — a block in a widget area, a query loop, another plugin's `do_blocks()` —
 *    enqueues its own stylesheet from `render_block` as it renders. Still only that block's.
 */
final class Block_Assets {

	/** Block name → published source key. */
	public const MAP = array(
		'pbs/row'    => 'block/row.css',
		'pbs/column' => 'block/column.css',
		'pbs/button' => 'block/button.css',
		'pbs/icon'   => 'block/icon.css',
	);

	/** Legacy shortcodes that render a block's markup, → the block they render as. */
	public const SHORTCODES = array(
		'pbs_button' => 'pbs/button',
	);

	/**
	 * Register the sources and the hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		foreach ( self::MAP as $name => $key ) {
			Assets::add_source( $key, 'assets/blocks/' . substr( $name, 4 ) . '.css' );
		}
		add_filter( 'render_block', array( self::class, 'on_render_block' ), 10, 2 );
		add_filter( 'do_shortcode_tag', array( self::class, 'on_shortcode' ), 10, 2 );
		add_action( 'template_redirect', array( self::class, 'prescan_main_query' ), 2 );
	}

	/**
	 * `render_block`: enqueue this block's stylesheet unless the page's compiled sheet has it.
	 *
	 * @param string|mixed         $html  Rendered HTML.
	 * @param array<string, mixed> $block Parsed block.
	 * @return string|mixed
	 */
	public static function on_render_block( $html, $block ) {
		self::ensure( (string) ( $block['blockName'] ?? '' ) );

		return $html;
	}

	/**
	 * `do_shortcode_tag`: a legacy shortcode that prints a block's markup needs that block's CSS.
	 *
	 * @param string|mixed $output Output.
	 * @param string|mixed $tag    Shortcode tag.
	 * @return string|mixed
	 */
	public static function on_shortcode( $output, $tag ) {
		if ( is_string( $tag ) && isset( self::SHORTCODES[ $tag ] ) ) {
			self::ensure( self::SHORTCODES[ $tag ] );
		}

		return $output;
	}

	/**
	 * Enqueue a block's stylesheet if it has one and the page's compiled sheet does not cover it.
	 *
	 * @param string $name Block name.
	 * @return bool Whether a stylesheet was enqueued.
	 */
	public static function ensure( string $name ): bool {
		if ( ! isset( self::MAP[ $name ] ) || Page_Css::covers( $name ) ) {
			return false;
		}
		Assets::enqueue_style( self::MAP[ $name ] );

		return true;
	}

	/**
	 * Every stylable block name used in a parsed block tree (inner blocks included), in MAP order.
	 *
	 * @param array<int, array<string, mixed>> $blocks          Parsed blocks.
	 * @param bool                             $button_shortcode Whether the content uses `[pbs_button]`.
	 * @return array<int, string>
	 */
	public static function names_in( array $blocks, bool $button_shortcode = false ): array {
		$found = array();
		self::walk( $blocks, $found );
		if ( $button_shortcode ) {
			$found['pbs/button'] = true;
		}

		return array_values( array_filter( array_keys( self::MAP ), static fn( string $n ): bool => isset( $found[ $n ] ) ) );
	}

	/**
	 * Non-singular requests: enqueue, in `<head>`, the stylesheets of the blocks the main query's
	 * posts use. Each post's block list comes from its compiled state when that is current (a
	 * cached meta read), and from a parse only when it is not.
	 *
	 * @return void
	 */
	public static function prescan_main_query(): void {
		if ( is_singular() ) {
			return;
		}
		global $wp_query;
		$posts = $wp_query instanceof \WP_Query ? (array) $wp_query->posts : array();
		foreach ( $posts as $post ) {
			if ( ! $post instanceof \WP_Post || ! Perf::is_pbs_content( (string) $post->post_content ) ) {
				continue;
			}
			$state   = Page_Css::state( (int) $post->ID, $post );
			$content = (string) $post->post_content;
			$names   = null !== $state
				? (array) $state['names']
				: self::names_in( parse_blocks( $content ), has_shortcode( $content, 'pbs_button' ) );
			foreach ( $names as $name ) {
				self::ensure( $name );
			}
		}
	}

	/**
	 * Collect block names recursively.
	 *
	 * @param array<int, array<string, mixed>> $blocks Parsed blocks.
	 * @param array<string, bool>              $found  Names found (by reference).
	 * @return void
	 */
	private static function walk( array $blocks, array &$found ): void {
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			$name = (string) ( $block['blockName'] ?? '' );
			if ( isset( self::MAP[ $name ] ) ) {
				$found[ $name ] = true;
			}
			self::walk( (array) ( $block['innerBlocks'] ?? array() ), $found );
		}
	}
}
