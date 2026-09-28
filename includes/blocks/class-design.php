<?php
/**
 * Render callbacks of the "Design" group of design-system blocks (lane L09).
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Blocks;

use ZinnDigital\PBS\Core\Render;
use ZinnDigital\PBS\Frontend;
use ZinnDigital\PBS\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One public static `render_<slug>()` per block of the group (Registry::callback()). Every
 * callback REBUILDS the live HTML from the attributes, prints classes only through
 * Frontend::cls(), escapes every value it prints, and translates every string it adds.
 *
 * The layout blocks pbs/section and pbs/container (pbs-d1): a section is a page band (full width by default, its
 * content boxed to the theme's content width); a container nests inside anything. Both lay their
 * children out with flexbox or CSS grid, set per breakpoint in the Style tab (`pbs.s.<bp>.display`
 * …). A boxed block lays out in its inner wrapper (Design\Compiler::layout_suffix()), a full-width
 * one in itself. The design classes are added to the first tag by the render filter
 * (Design\Styles), like any other block's.
 */
final class Design {

	/** HTML tags an author may choose. */
	public const TAGS = array( 'section', 'div', 'header', 'footer', 'article', 'aside', 'main', 'nav' );

	/**
	 * `pbs/section`.
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 * @param string               $content    Inner blocks already rendered (the test bar), or the saved fallback.
	 * @param \WP_Block|null       $block      Block.
	 * @return string
	 */
	public static function render_section( array $attributes, string $content = '', $block = null ): string {
		return self::layout( 'section', $attributes, self::inner( $block, $content ), Settings::prefix() );
	}

	/**
	 * `pbs/container`.
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 * @param string               $content    Inner blocks already rendered (the test bar), or the saved fallback.
	 * @param \WP_Block|null       $block      Block.
	 * @return string
	 */
	public static function render_container( array $attributes, string $content = '', $block = null ): string {
		return self::layout( 'container', $attributes, self::inner( $block, $content ), Settings::prefix() );
	}

	/**
	 * The markup of a section or container (pure: tests call it directly).
	 *
	 * @param string               $kind   `section` or `container`.
	 * @param array<string, mixed> $attrs  Attributes: tag, width (full|boxed), align, link {url, newTab, label}.
	 * @param string               $inner  Rendered inner blocks.
	 * @param string               $prefix Class prefix.
	 * @return string
	 */
	public static function layout( string $kind, array $attrs, string $inner, string $prefix ): string {
		$default = 'section' === $kind ? 'section' : 'div';
		$tag     = in_array( $attrs['tag'] ?? '', self::TAGS, true ) ? (string) $attrs['tag'] : $default;
		$boxed   = 'boxed' === ( $attrs['width'] ?? ( 'section' === $kind ? 'boxed' : 'full' ) );
		$link    = is_array( $attrs['link'] ?? null ) ? $attrs['link'] : array();
		$url     = Render::safe_url( (string) ( $link['url'] ?? '' ) );

		$class = Frontend::classes( $prefix, $kind, $boxed ? $kind . '--boxed' : '' );
		$align = (string) ( $attrs['align'] ?? '' );
		if ( in_array( $align, array( 'wide', 'full' ), true ) ) {
			$class .= ' align' . $align; // WordPress's own alignment class, which themes style.
		}

		$open = '';
		if ( '' !== $url ) {
			// A linked block becomes the link itself (its content must not hold other links).
			$tag   = 'a';
			$open .= ' href="' . esc_url( $url ) . '"';
			if ( ! empty( $link['newTab'] ) ) {
				$open .= ' target="_blank" rel="noopener"';
			}
			$label = trim( (string) ( $link['label'] ?? '' ) );
			if ( '' !== $label ) {
				$open .= ' aria-label="' . esc_attr( $label ) . '"';
			}
		}
		if ( $boxed ) {
			$inner = '<div class="' . esc_attr( Frontend::classes( $prefix, $kind . '__in' ) ) . '">' . $inner . '</div>';
		}

		return '<' . $tag . ' class="' . esc_attr( $class ) . '"' . $open . '>' . $inner . '</' . $tag . '>';
	}

	/**
	 * The inner blocks, rendered one by one (their own render callbacks and filters run). Given no
	 * WP_Block (the per-block test bar), `$content` already holds them rendered.
	 *
	 * @param \WP_Block|null $block   Block.
	 * @param string         $content Rendered inner blocks when there is no WP_Block.
	 * @return string
	 */
	private static function inner( $block, string $content ): string {
		if ( ! $block instanceof \WP_Block ) {
			return $content;
		}
		$html = '';
		foreach ( $block->inner_blocks as $child ) {
			$html .= $child->render();
		}

		return $html;
	}
}
