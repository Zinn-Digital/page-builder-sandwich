<?php
/**
 * `pbs/table-of-contents` — built from the headings of the post it sits in.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Blocks\Site;

use ZinnDigital\PBS\Blocks\Markup;
use ZinnDigital\PBS\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Table of contents.
 *
 * The list comes from the post's `core/heading` blocks between the chosen levels, nested by
 * level. A heading that has no anchor gets one when the page renders (a `render_block` filter,
 * only on a post that holds a table of contents), computed by the SAME function that built the
 * list, so the links always land. An anchor the author set is kept. Headings whose text has no
 * Latin letters (Arabic, Chinese…) get `section-<n>` rather than a percent-encoded slug.
 *
 * Collapsible = a native `<details>`. Scroll-spy (the view module) marks the section being read
 * with `aria-current="true"`; without script the list is plain links.
 */
final class Toc {

	/**
	 * Per post id: the anchors still to hand out to rendering headings, by heading text.
	 *
	 * @var array<int, array<string, array<int, string>>>
	 */
	private static array $queue = array();

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'render_block_core/heading', array( self::class, 'anchor_heading' ), 10, 2 );
		// the_content renders blocks at priority 9; start each pass with a full queue.
		add_filter( 'the_content', array( self::class, 'reset' ), 8 );
	}

	/**
	 * Every heading of some content: level, text, anchor (existing or computed), in order.
	 *
	 * @param string $content Post content.
	 * @return array<int, array{level: int, text: string, id: string, own: bool}>
	 */
	public static function headings( string $content ): array {
		$out  = array();
		$used = array();
		$n    = 0;
		foreach ( self::heading_blocks( parse_blocks( $content ) ) as $block ) {
			$html = (string) ( $block['innerHTML'] ?? '' );
			$text = trim( (string) preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
			if ( '' === $text ) {
				continue;
			}
			++$n;
			$level = (int) ( $block['attrs']['level'] ?? 2 );
			$own   = preg_match( '/<h[1-6][^>]*\sid="([^"]+)"/i', $html, $m ) ? html_entity_decode( $m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) : '';
			$id    = '' !== $own ? $own : self::slug( $text, $n );
			$base  = $id;
			for ( $i = 2; isset( $used[ $id ] ); $i++ ) {
				$id = $base . '-' . $i;
			}
			$used[ $id ] = true;
			$out[]       = array(
				'level' => max( 1, min( 6, $level ) ),
				'text'  => $text,
				'id'    => $id,
				'own'   => '' !== $own,
			);
		}

		return $out;
	}

	/**
	 * An anchor for a heading's text.
	 *
	 * @param string $text Heading text.
	 * @param int    $n    Its position among the headings (1-based).
	 * @return string
	 */
	public static function slug( string $text, int $n ): string {
		$slug = sanitize_title( remove_accents( $text ) );

		return ( '' === $slug || str_contains( $slug, '%' ) ) ? 'section-' . $n : $slug;
	}

	/**
	 * `pbs/table-of-contents`.
	 *
	 * @param array<string, mixed> $attributes Attributes: title, minLevel, maxLevel, ordered, collapsible, spy.
	 * @return string
	 */
	public static function render( array $attributes ): string {
		$post = self::post();
		if ( ! $post ) {
			return '';
		}
		$min   = max( 1, min( 6, (int) ( $attributes['minLevel'] ?? 2 ) ) );
		$max   = max( $min, min( 6, (int) ( $attributes['maxLevel'] ?? 3 ) ) );
		$items = array_values( array_filter( self::headings( (string) $post->post_content ), static fn( array $h ): bool => $h['level'] >= $min && $h['level'] <= $max ) );
		if ( array() === $items ) {
			return '';
		}
		$tag   = ! empty( $attributes['ordered'] ) ? 'ol' : 'ul';
		$list  = self::nested( $items, 0, $min, $tag )[0];
		$title = trim( wp_kses( (string) ( $attributes['title'] ?? '' ), array() ) );
		$label = '' !== $title ? $title : __( 'Contents', 'page-builder-sandwich' );
		$spy   = ! isset( $attributes['spy'] ) || ! empty( $attributes['spy'] );

		$open = '<nav class="' . esc_attr( Frontend::cls( 'table-of-contents' ) ) . '" aria-label="' . esc_attr( $label ) . '"'
			. ( $spy ? Markup::interactive( 'table-of-contents', array() ) . ' data-wp-init="callbacks.tocSpy"' : '' ) . '>';
		if ( ! empty( $attributes['collapsible'] ) ) {
			return $open . '<details class="' . esc_attr( Frontend::cls( 'table-of-contents__details' ) ) . '"' . ( ! empty( $attributes['collapsed'] ) ? '' : ' open' ) . '>'
				. '<summary class="' . esc_attr( Frontend::cls( 'table-of-contents__title' ) ) . '">' . esc_html( $label ) . '</summary>'
				. $list . '</details></nav>';
		}

		return $open . ( '' !== $title ? '<p class="' . esc_attr( Frontend::cls( 'table-of-contents__title' ) ) . '">' . esc_html( $title ) . '</p>' : '' ) . $list . '</nav>';
	}

	/**
	 * Build one list level from $i on; returns [html, next index].
	 *
	 * @param array<int, array<string, mixed>> $items Headings.
	 * @param int                              $i     Start.
	 * @param int                              $level This list's level.
	 * @param string                           $tag   ol|ul.
	 * @return array{0: string, 1: int}
	 */
	private static function nested( array $items, int $i, int $level, string $tag ): array {
		$html  = '<' . $tag . ' class="' . esc_attr( Frontend::cls( 'table-of-contents__list' ) ) . '">';
		$count = count( $items );
		while ( $i < $count && $items[ $i ]['level'] >= $level ) {
			$item  = $items[ $i ];
			$html .= '<li class="' . esc_attr( Frontend::cls( 'table-of-contents__item' ) ) . '"><a class="' . esc_attr( Frontend::cls( 'table-of-contents__link' ) ) . '" href="#' . esc_attr( rawurlencode( (string) $item['id'] ) ) . '">' . esc_html( (string) $item['text'] ) . '</a>';
			++$i;
			if ( $i < $count && $items[ $i ]['level'] > $item['level'] ) {
				[ $sub, $i ] = self::nested( $items, $i, (int) $items[ $i ]['level'], $tag );
				$html       .= $sub;
			}
			$html .= '</li>';
		}

		return array( $html . '</' . $tag . '>', $i );
	}

	/**
	 * `render_block_core/heading`: give a heading with no id the anchor the list links to — only
	 * on a post that holds a table of contents.
	 *
	 * @param string|mixed         $html  Rendered heading.
	 * @param array<string, mixed> $block Parsed block.
	 * @return string|mixed
	 */
	public static function anchor_heading( $html, $block ) {
		unset( $block );
		$post = self::post();
		if ( ! is_string( $html ) || ! $post || ! has_block( 'pbs/table-of-contents', $post ) || preg_match( '/^\s*<h[1-6][^>]*\sid=/i', $html ) ) {
			return $html;
		}
		$id = (int) $post->ID;
		if ( ! isset( self::$queue[ $id ] ) ) {
			self::$queue[ $id ] = array();
			foreach ( self::headings( (string) $post->post_content ) as $h ) {
				if ( ! $h['own'] ) {
					self::$queue[ $id ][ $h['text'] ][] = $h['id'];
				}
			}
		}
		$text   = trim( (string) preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
		$anchor = isset( self::$queue[ $id ][ $text ] ) ? array_shift( self::$queue[ $id ][ $text ] ) : null;

		return null === $anchor ? $html : Markup::add_attributes( $html, array( 'id' => $anchor ) );
	}

	/**
	 * `the_content`, before blocks render: hand out the anchors again from the first heading.
	 *
	 * @param string|mixed $content Content.
	 * @return string|mixed
	 */
	public static function reset( $content ) {
		self::$queue = array();

		return $content;
	}

	/**
	 * The post being rendered: the loop's global post (what get_post() returns without an id).
	 *
	 * @return object|null
	 */
	private static function post(): ?object {
		$post = $GLOBALS['post'] ?? null;
		if ( ! is_object( $post ) ) {
			$post = get_post();
		}

		return is_object( $post ) && isset( $post->post_content ) ? $post : null;
	}

	/**
	 * The heading blocks of a tree, in document order.
	 *
	 * @param array<int, array<string, mixed>> $blocks Parsed blocks.
	 * @return array<int, array<string, mixed>>
	 */
	private static function heading_blocks( array $blocks ): array {
		$out = array();
		foreach ( $blocks as $block ) {
			if ( 'core/heading' === ( $block['blockName'] ?? '' ) ) {
				$out[] = $block;
			}
			$out = array_merge( $out, self::heading_blocks( (array) ( $block['innerBlocks'] ?? array() ) ) );
		}

		return $out;
	}
}
