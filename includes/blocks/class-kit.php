<?php
/**
 * Shared pieces of the content and marketing blocks (lane L09, P5 group G-B).
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Blocks;

use ZinnDigital\PBS\Core\Render;
use ZinnDigital\PBS\Core\Svg;
use ZinnDigital\PBS\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Small, pure helpers the render callbacks share: the built-in icons, a picked (inline SVG) icon,
 * rich text, list items, URLs, heading levels and the visually hidden text screen readers need.
 *
 * ⭐ Twin: src/blocks/shared/kit.js draws the SAME built-in icons in the editor, so the editor
 * preview and the page show one drawing.
 */
final class Kit {

	/** Built-in icons: 24×24, stroked in the text colour, decorative. */
	public const ICONS = array(
		'check'  => '<path d="M20 6 9 17l-5-5"/>',
		'circle' => '<circle cx="12" cy="12" r="9"/>',
		'cross'  => '<path d="M18 6 6 18M6 6l12 12"/>',
		'dot'    => '<circle cx="12" cy="12" r="3.5" fill="currentColor"/>',
		'arrow'  => '<path d="M5 12h14M13 6l6 6-6 6"/>',
		'star'   => '<path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9Z"/>',
		'info'   => '<circle cx="12" cy="12" r="10"/><path d="M12 16v-5M12 8h.01"/>',
		'link'   => '<path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7"/>',
		'mail'   => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
		'rss'    => '<path d="M4 11a9 9 0 0 1 9 9M4 4a16 16 0 0 1 16 16"/><circle cx="5" cy="19" r="1.5" fill="currentColor"/>',
		'phone'  => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2Z"/>',
		'star-f' => '<path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9Z" fill="currentColor"/>',
	);

	/** Accent colours (theme.json presets with fallbacks), never an inline style. */
	public const ACCENTS = array( 'text', 'primary', 'secondary', 'contrast' );

	/**
	 * An icon: the picked inline SVG when there is one (sanitised again here), else a built-in.
	 *
	 * @param string $svg     Picked SVG ('' for none).
	 * @param string $builtin Built-in name (ICONS key).
	 * @return string Decorative `<svg>` markup, '' when neither is usable.
	 */
	public static function icon( string $svg, string $builtin ): string {
		if ( '' !== trim( $svg ) ) {
			$clean = Svg::sanitize( $svg );
			if ( '' !== $clean ) {
				return '<span class="' . esc_attr( Frontend::cls( 'kit-icon' ) ) . '" aria-hidden="true">' . $clean . '</span>';
			}
		}
		if ( ! isset( self::ICONS[ $builtin ] ) ) {
			return '';
		}

		return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="1em" height="1em" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . self::ICONS[ $builtin ] . '</svg>';
	}

	/**
	 * Rich text (inline HTML) as the post allows it, trimmed.
	 *
	 * @param mixed $html Attribute value.
	 * @return string
	 */
	public static function rich( $html ): string {
		return is_string( $html ) ? trim( wp_kses_post( $html ) ) : '';
	}

	/**
	 * A plain-text attribute, escaped for HTML.
	 *
	 * @param mixed $text Attribute value.
	 * @return string
	 */
	public static function text( $text ): string {
		return is_string( $text ) ? esc_html( trim( wp_strip_all_tags( $text ) ) ) : '';
	}

	/**
	 * A URL safe to print in href/src (http(s), mailto, tel, relative or #anchor), escaped.
	 *
	 * @param mixed $url Attribute value.
	 * @return string '' when unsafe or empty.
	 */
	public static function url( $url ): string {
		$safe = is_string( $url ) ? Render::safe_url( $url ) : '';

		return '' === $safe ? '' : esc_url( $safe );
	}

	/**
	 * The array items of an attribute (objects only), at most $max.
	 *
	 * @param mixed $items Attribute value.
	 * @param int   $max   Most items.
	 * @return array<int, array<string, mixed>>
	 */
	public static function items( $items, int $max = 100 ): array {
		$out = array();
		foreach ( is_array( $items ) ? array_slice( array_values( $items ), 0, $max ) : array() as $item ) {
			if ( is_array( $item ) ) {
				$out[] = $item;
			}
		}

		return $out;
	}

	/**
	 * A heading level between 2 and 6.
	 *
	 * @param mixed $level   Attribute value.
	 * @param int   $fallback Default.
	 * @return int
	 */
	public static function level( $level, int $fallback = 3 ): int {
		$n = is_numeric( $level ) ? (int) $level : $fallback;

		return max( 2, min( 6, $n ) );
	}

	/**
	 * One of a fixed set.
	 *
	 * @param mixed    $value   Attribute value.
	 * @param string[] $allowed Allowed values (the first is the default).
	 * @return string
	 */
	public static function choice( $value, array $allowed ): string {
		return is_string( $value ) && in_array( $value, $allowed, true ) ? $value : $allowed[0];
	}

	/**
	 * Text only screen readers read (the visually-hidden utility class of the plugin).
	 *
	 * @param string $text Already-escaped text.
	 * @return string
	 */
	public static function sr( string $text ): string {
		return '<span class="' . esc_attr( Frontend::cls( 'sr' ) ) . '">' . $text . '</span>';
	}

	/**
	 * A link's target/rel attributes for "open in a new tab".
	 *
	 * @param bool $new_tab New tab.
	 * @return string Attributes with a leading space, or ''.
	 */
	public static function target( bool $new_tab ): string {
		return $new_tab ? ' target="_blank" rel="noopener"' : '';
	}
}
