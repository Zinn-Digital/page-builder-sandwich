<?php
/**
 * The glossary block's data and its optional auto-linking of terms (lane L09, P5 group G-B).
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Blocks;

use ZinnDigital\PBS\Frontend;
use ZinnDigital\PBS\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Entries (sanitised, sorted, each with a stable anchor) and the `the_content` pass that links
 * the FIRST mention of each term elsewhere on the page to its definition, when a glossary on that
 * page asks for it (`autoLink`).
 *
 * ⭐ The pass never links inside a link, a heading, code, a button, a form field or the glossary
 * itself, and links each term once, so a page does not turn into a sea of underlines. The link is
 * an ordinary in-page link: its text is the term, keyboard and screen-reader users follow it like
 * any other.
 */
final class Glossary {

	/** Elements whose text is never linked. */
	private const SKIP = array( 'a', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'script', 'style', 'code', 'pre', 'kbd', 'button', 'textarea', 'select', 'option', 'label', 'svg', 'title' );

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'the_content', array( self::class, 'autolink' ), 20 );
	}

	/**
	 * Plain text of a rich-text term (for matching, anchors and sorting).
	 *
	 * @param string $html Rich text.
	 * @return string
	 */
	public static function plain( string $html ): string {
		return trim( html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	}

	/**
	 * The entries of a glossary block.
	 *
	 * @param array<string, mixed> $attributes Attributes: items [{term, definition}], sort.
	 * @return array<int, array{term: string, definition: string, plain: string, id: string, letter: string}>
	 */
	public static function entries( array $attributes ): array {
		$prefix = Settings::prefix();
		$out    = array();
		$ids    = array();
		foreach ( Kit::items( $attributes['items'] ?? array(), 500 ) as $item ) {
			$term = Kit::rich( $item['term'] ?? '' );
			$def  = Kit::rich( $item['definition'] ?? '' );
			$text = self::plain( $term );
			if ( '' === $text || '' === $def ) {
				continue;
			}
			$slug = sanitize_title( $text );
			$base = $prefix . '-g-' . ( '' === $slug ? 'term' : $slug );
			$id   = $base;
			for ( $n = 2; isset( $ids[ $id ] ); $n++ ) {
				$id = $base . '-' . $n;
			}
			$ids[ $id ] = true;
			$first      = function_exists( 'mb_substr' ) ? mb_strtoupper( mb_substr( $text, 0, 1 ) ) : strtoupper( substr( $text, 0, 1 ) );
			$out[]      = array(
				'term'       => $term,
				'definition' => $def,
				'plain'      => $text,
				'id'         => $id,
				'letter'     => 1 === preg_match( '/^\p{L}$/u', $first ) ? $first : '#',
			);
		}
		if ( ! isset( $attributes['sort'] ) || ! empty( $attributes['sort'] ) ) {
			usort(
				$out,
				static fn( $a, $b ) => strcmp( self::fold( $a['plain'] ), self::fold( $b['plain'] ) )
			);
		}

		return $out;
	}

	/**
	 * A case- and accent-insensitive sort key.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function fold( string $text ): string {
		$lower = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text ) : strtolower( $text );
		if ( function_exists( 'remove_accents' ) ) {
			$lower = remove_accents( $lower );
		}

		return $lower;
	}

	/**
	 * Every glossary on the current post that asked for auto-linking.
	 *
	 * @param array<int, array<string, mixed>> $blocks Parsed blocks.
	 * @return array<int, array{plain: string, id: string}>
	 */
	public static function linkable( array $blocks ): array {
		$out   = array();
		$stack = $blocks;
		while ( array() !== $stack ) {
			$block = array_shift( $stack );
			if ( ! is_array( $block ) ) {
				continue;
			}
			foreach ( (array) ( $block['innerBlocks'] ?? array() ) as $child ) {
				$stack[] = $child;
			}
			if ( 'pbs/glossary' !== ( $block['blockName'] ?? '' ) || empty( $block['attrs']['autoLink'] ) ) {
				continue;
			}
			foreach ( self::entries( (array) $block['attrs'] ) as $e ) {
				$out[ $e['plain'] ] ??= array(
					'plain' => $e['plain'],
					'id'    => $e['id'],
				);
			}
		}
		$out = array_values( $out );
		// Longest first, so "block theme" wins over "block".
		usort( $out, static fn( $a, $b ) => strlen( $b['plain'] ) <=> strlen( $a['plain'] ) );

		return $out;
	}

	/**
	 * `the_content`: link the first mention of each term (only on a page whose glossary asks).
	 *
	 * @param mixed $content Rendered content.
	 * @return mixed
	 */
	public static function autolink( $content ) {
		$root = Frontend::cls( 'glossary' );
		if ( ! is_string( $content ) || ! str_contains( $content, $root ) || is_feed() ) {
			return $content;
		}
		$post = get_post();
		if ( ! $post instanceof \WP_Post || ! str_contains( $post->post_content, '"autoLink":true' ) ) {
			return $content;
		}

		return self::link_terms( $content, self::linkable( parse_blocks( $post->post_content ) ), Settings::prefix() );
	}

	/**
	 * Link the first mention of each term in the text of $html (outside SKIP elements and outside
	 * the glossary itself).
	 *
	 * @param string                                       $html   HTML.
	 * @param array<int, array{plain: string, id: string}> $terms  Terms, longest first.
	 * @param string                                       $prefix Class prefix.
	 * @return string
	 */
	public static function link_terms( string $html, array $terms, string $prefix ): string {
		if ( array() === $terms ) {
			return $html;
		}
		$parts = preg_split( '/(<[^>]*>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE );
		if ( false === $parts ) {
			return $html;
		}
		$skip = array(); // Stack of [tag, depth].
		$todo = $terms;
		$root = $prefix . '-glossary';
		foreach ( $parts as $i => $part ) {
			if ( '' === $part ) {
				continue;
			}
			if ( '<' === $part[0] ) {
				if ( 1 !== preg_match( '#^<(/?)([a-zA-Z][a-zA-Z0-9-]*)#', $part, $m ) ) {
					continue; // A comment or a doctype.
				}
				$tag     = strtolower( $m[2] );
				$closing = '/' === $m[1];
				$void    = str_ends_with( rtrim( $part, '> ' ), '/' ) || in_array( $tag, array( 'br', 'img', 'hr', 'input', 'meta', 'link', 'source', 'wbr' ), true );
				if ( array() !== $skip ) {
					$top = count( $skip ) - 1;
					if ( $tag === $skip[ $top ][0] && ! $void ) {
						$skip[ $top ][1] += $closing ? -1 : 1;
						if ( 0 === $skip[ $top ][1] ) {
							array_pop( $skip );
						}
					}
					continue;
				}
				if ( $closing || $void ) {
					continue;
				}
				$is_root = 1 === preg_match( '/\sclass="[^"]*(?<![\w-])' . preg_quote( $root, '/' ) . '(?![\w-])/', $part );
				if ( $is_root || in_array( $tag, self::SKIP, true ) ) {
					$skip[] = array( $tag, 1 );
				}
				continue;
			}
			if ( array() !== $skip || array() === $todo ) {
				continue;
			}
			// Segments: even = text still open to matching, odd = a link just made (never re-matched,
			// so a shorter term cannot land inside a longer one's link).
			$segments = array( $part );
			foreach ( $todo as $k => $term ) {
				$re = '/(?<![\p{L}\p{N}])(' . preg_quote( esc_html( $term['plain'] ), '/' ) . ')(?![\p{L}\p{N}])/iu';
				foreach ( $segments as $s => $segment ) {
					if ( 1 === $s % 2 || 1 !== preg_match( $re, $segment, $hit, PREG_OFFSET_CAPTURE ) ) {
						continue;
					}
					$at   = (int) $hit[1][1];
					$link = '<a class="' . esc_attr( $prefix . '-glossary-link' ) . '" href="#' . esc_attr( $term['id'] ) . '">' . $hit[1][0] . '</a>';
					array_splice( $segments, $s, 1, array( substr( $segment, 0, $at ), $link, substr( $segment, $at + strlen( $hit[1][0] ) ) ) );
					unset( $todo[ $k ] );
					break;
				}
			}
			$parts[ $i ] = implode( '', $segments );
		}

		return implode( '', $parts );
	}
}
