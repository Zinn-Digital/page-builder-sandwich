<?php
/**
 * Live (plugin-active) HTML for the core blocks.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Core;

use ZinnDigital\PBS\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The pure half of every block render: attributes, the already-rendered inner HTML and the
 * class prefix in, HTML out. No WordPress state is read here, so the unit suite runs the exact
 * code a visitor gets; the thin callbacks in Blocks gather the inputs.
 *
 * ⛔ Every class comes from Frontend::classes() (the neutral prefix, ADR 0033) and every
 * `style` goes through Css::sanitize_declarations() ON OUTPUT — a block's attributes are
 * author input and are never trusted because they were accepted once.
 */
final class Render {

	/** Row width variants. */
	public const WIDTHS = array( 'full-width', 'full-width-retain-content' );

	/** Button alignments. */
	public const ALIGNS = array( 'left', 'center', 'right' );

	/** A class or attribute name that would print the product's name (ADR 0033). */
	private const FOOTPRINT = '/pbs|sandwich|page-builder|tranzly|wp-block-/i';

	/**
	 * `pbs/row`.
	 *
	 * @param array<string, mixed> $attrs  Attributes.
	 * @param string               $inner  Rendered columns.
	 * @param string               $prefix Class prefix.
	 * @return string
	 */
	public static function row( array $attrs, string $inner, string $prefix ): string {
		$parts = array( self::base( 'row', $attrs ) );
		$width = (string) ( $attrs['width'] ?? '' );
		if ( in_array( $width, self::WIDTHS, true ) ) {
			// A converted legacy row is widened exactly as 5.x widened it — by the legacy
			// behaviour script, measured against the body (Assets\Legacy_Script, whose pre-CSS
			// also covers a visitor without scripts) — so it carries 5.x's `data-width` and NOT
			// the new breakout class, which fought the script's measurement. Measured: with the
			// class, the CSS breakout sat 47px from where 5.x put the row on an off-centre page,
			// and with class and script together the retain-content padding came out 40px short.
			if ( empty( $attrs['legacy'] ) ) {
				$parts[] = 'row--' . $width;
			} else {
				$data                = is_array( $attrs['legacyData'] ?? null ) ? $attrs['legacyData'] : array();
				$data['width']       = $width;
				$attrs['legacyData'] = $data;
			}
		}

		return self::wrap( 'div', self::classes( $prefix, $parts, $attrs ), $attrs, $inner, $prefix );
	}

	/**
	 * `pbs/column`.
	 *
	 * @param array<string, mixed> $attrs  Attributes.
	 * @param string               $inner  Rendered inner blocks.
	 * @param string               $prefix Class prefix.
	 * @return string
	 */
	public static function column( array $attrs, string $inner, string $prefix ): string {
		return self::wrap( 'div', self::classes( $prefix, array( self::base( 'col', $attrs ) ), $attrs ), $attrs, $inner, $prefix );
	}

	/**
	 * `pbs/button` (and the legacy `[pbs_button]` shortcode, through Shortcodes).
	 *
	 * @param array<string, mixed> $attrs  Attributes: url, text (inline HTML), style, target, rel, align, legacyClass.
	 * @param string               $prefix Class prefix.
	 * @return string
	 */
	public static function button( array $attrs, string $prefix ): string {
		$text = trim( wp_kses_post( (string) ( $attrs['text'] ?? '' ) ) );
		$url  = self::safe_url( (string) ( $attrs['url'] ?? '' ) );
		if ( '' === $text && '' === $url ) {
			return '';
		}

		$a = '<a class="' . esc_attr( self::classes( $prefix, array( self::base( 'button', $attrs ) ), $attrs ) ) . '"';
		if ( '' !== $url ) {
			$a .= ' href="' . esc_url( $url ) . '"';
		}
		$style = Css::sanitize_declarations( (string) ( $attrs['style'] ?? '' ) );
		if ( '' !== $style ) {
			$a .= ' style="' . esc_attr( $style ) . '"';
		}
		$rel = self::rel_tokens( (string) ( $attrs['rel'] ?? '' ) );
		if ( '_blank' === ( $attrs['target'] ?? '' ) ) {
			$a  .= ' target="_blank"';
			$rel = array_values( array_unique( array_merge( $rel, array( 'noopener' ) ) ) );
		}
		if ( array() !== $rel ) {
			$a .= ' rel="' . esc_attr( implode( ' ', $rel ) ) . '"';
		}
		$a .= self::data_attributes( $attrs, $prefix ) . '>' . $text . '</a>';

		$align = (string) ( $attrs['align'] ?? '' );
		$wrap  = array();
		if ( in_array( $align, self::ALIGNS, true ) ) {
			$wrap[] = 'text-align:' . $align;
		}
		// The rest of a converted legacy button paragraph's style (its margins, measured: a
		// 250px bottom margin that set a whole hero's height was lost without it).
		$extra = Css::sanitize_declarations( (string) ( $attrs['wrapStyle'] ?? '' ) );
		if ( '' !== $extra ) {
			$wrap[] = $extra;
		}
		// The paragraph's own legacy behaviour settings (per-breakpoint margins, animation).
		$pdata = is_array( $attrs['wrapData'] ?? null ) && array() !== $attrs['wrapData']
			? self::data_attributes( array( 'legacyData' => $attrs['wrapData'] ), $prefix )
			: '';
		if ( array() !== $wrap ) {
			return '<p style="' . esc_attr( implode( ';', $wrap ) ) . '"' . $pdata . '>' . $a . '</p>';
		}
		// A converted legacy button kept its paragraph (a bare link in a flex column stretches).
		if ( ! empty( $attrs['wrapped'] ) || '' !== $pdata ) {
			return '<p' . $pdata . '>' . $a . '</p>';
		}

		return $a;
	}

	/**
	 * `pbs/icon`.
	 *
	 * @param array<string, mixed> $attrs  Attributes: svg, style, label.
	 * @param string               $prefix Class prefix.
	 * @return string
	 */
	public static function icon( array $attrs, string $prefix ): string {
		$svg = Svg::sanitize( (string) ( $attrs['svg'] ?? '' ) );
		if ( '' === $svg ) {
			return '';
		}
		// `display: block` (every icon inserted since 6.2, and a 6.1 icon that sits at the top level
		// of the content — Blocks::icon_display()) is a block of its own, which a block theme's
		// layout gives the content width and its margins; aligned with `justify` (logical: start /
		// centre / end). Otherwise it stays the inline span 6.1 printed, so an icon inside a row or
		// a column (converted legacy content) keeps its place in the line.
		$block   = 'block' === ( $attrs['display'] ?? '' );
		$justify = in_array( $attrs['justify'] ?? '', array( 'center', 'end' ), true ) ? (string) $attrs['justify'] : 'start';
		$tag     = $block ? 'div' : 'span';
		$names   = $block ? array( 'icon', 'icon--block', 'icon--' . $justify ) : array( 'icon' );
		$out     = '<' . $tag . ' class="' . esc_attr( Frontend::classes( $prefix, ...$names ) ) . '"';
		$style   = Css::sanitize_declarations( (string) ( $attrs['style'] ?? '' ) );
		if ( '' !== $style ) {
			$out .= ' style="' . esc_attr( $style ) . '"';
		}
		$label = trim( (string) ( $attrs['label'] ?? '' ) );
		$out  .= '' !== $label
			? ' role="img" aria-label="' . esc_attr( $label ) . '"'
			: ' aria-hidden="true"';

		return $out . '>' . $svg . '</' . $tag . '>';
	}

	/**
	 * A wrapper for widget / sidebar output.
	 *
	 * @param string $kind   `widget` or `sidebar`.
	 * @param string $inner  Rendered HTML.
	 * @param string $prefix Class prefix.
	 * @return string
	 */
	public static function container( string $kind, string $inner, string $prefix ): string {
		if ( '' === trim( $inner ) ) {
			return '';
		}

		return '<div class="' . esc_attr( Frontend::classes( $prefix, $kind ) ) . '">' . $inner . '</div>';
	}

	/**
	 * An http(s), mailto, tel, root-relative or fragment URL; anything else becomes ''.
	 *
	 * @param string $url Untrusted URL.
	 * @return string
	 */
	public static function safe_url( string $url ): string {
		$url = trim( $url );
		if ( '' === $url ) {
			return '';
		}
		$squashed = strtolower( (string) preg_replace( '/[\x00-\x20]+/', '', html_entity_decode( $url, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
		if ( 1 === preg_match( '/^[a-z][a-z0-9+.-]*:/', $squashed ) && 1 !== preg_match( '/^(https?|mailto|tel):/', $squashed ) ) {
			return '';
		}

		return $url;
	}

	/**
	 * The structural class part of a row, column or button.
	 *
	 * A block converted from legacy content (`legacy: true`) takes legacy's own structural class
	 * in its neutral form — `l-row`, `l-col`, `l-button` — so the legacy compat stylesheet
	 * (assets/legacy.css, enqueued for exactly these posts by Assets\Legacy) lays it out as legacy
	 * 5.x did: 24px row gaps, 15px column padding, column flex direction, the button's metrics.
	 * ⭐ That is what "converts within a visual-diff threshold" rests on: the new block
	 * stylesheets describe NEW content and are a different design, so rendering converted
	 * content through them moved every converted page (measured, docs/plugins-overhaul L08 vdiff). Content made in the
	 * new editor has no flag and keeps the plain `row` / `col` / `button` classes.
	 *
	 * @param string               $part  `row`, `col` or `button`.
	 * @param array<string, mixed> $attrs Attributes (reads legacy).
	 * @return string
	 */
	private static function base( string $part, array $attrs ): string {
		return ! empty( $attrs['legacy'] ) ? 'l-' . $part : $part;
	}

	/**
	 * Class list: the base parts, each legacy class as `l-<name>`, then the author's own classes.
	 *
	 * @param string               $prefix Prefix.
	 * @param array<int, string>   $parts  Base parts.
	 * @param array<string, mixed> $attrs  Attributes (reads legacyClass).
	 * @return string
	 */
	private static function classes( string $prefix, array $parts, array $attrs ): string {
		$list = preg_split( '/\s+/', trim( (string) ( $attrs['legacyClass'] ?? '' ) ) );
		foreach ( false === $list ? array() : $list as $legacy ) {
			$legacy = (string) preg_replace( '/^pbs-/', '', $legacy );
			if ( '' !== $legacy ) {
				$parts[] = 'l-' . $legacy;
			}
		}

		$names = Frontend::classes( $prefix, ...$parts );
		// The author's own (non-legacy) classes the converter kept in `legacyData`.
		$data  = $attrs['legacyData'] ?? null;
		$other = is_array( $data ) && is_string( $data['class'] ?? null ) ? preg_split( '/\s+/', trim( $data['class'] ) ) : array();
		foreach ( false === $other ? array() : $other as $name ) {
			$name = sanitize_html_class( $name );
			if ( '' !== $name && 1 !== preg_match( self::FOOTPRINT, $name ) ) {
				$names .= ' ' . $name;
			}
		}

		return $names;
	}

	/**
	 * A block element with class, sanitised style and inner HTML.
	 *
	 * @param string               $tag   Tag.
	 * @param string               $names Classes.
	 * @param array<string, mixed> $attrs Attributes (reads style).
	 * @param string               $inner Inner HTML (already rendered and escaped by its own blocks).
	 * @param string               $prefix Class prefix (for the legacy `data-*` names).
	 * @return string
	 */
	private static function wrap( string $tag, string $names, array $attrs, string $inner, string $prefix = '' ): string {
		$style = Css::sanitize_declarations( (string) ( $attrs['style'] ?? '' ) );
		$out   = '<' . $tag . ' class="' . esc_attr( $names ) . '"';
		if ( '' !== $style ) {
			$out .= ' style="' . esc_attr( $style ) . '"';
		}

		return $out . self::data_attributes( $attrs, $prefix ) . '>' . $inner . '</' . $tag . '>';
	}

	/**
	 * The legacy behaviour settings a converted block keeps in `legacyData` (per-breakpoint
	 * margins, hide-on-device, video / parallax / Ken Burns backgrounds, scroll animations …),
	 * printed as the attributes the legacy compat stylesheet and script read: `pbs-X` as
	 * `data-<prefix>-l-X` (the name Assets\Legacy gives it in unconverted content), anything else
	 * (`aos`, `aos-delay`, `cu-time` …) as `data-X`. A name that is not a plain token, or that
	 * would print a product name, is dropped.
	 *
	 * @param array<string, mixed> $attrs  Attributes (reads legacyData).
	 * @param string               $prefix Class prefix.
	 * @return string
	 */
	private static function data_attributes( array $attrs, string $prefix ): string {
		$data = $attrs['legacyData'] ?? null;
		if ( ! is_array( $data ) || '' === $prefix ) {
			return '';
		}
		$out = '';
		foreach ( $data as $key => $value ) {
			$key = strtolower( (string) $key );
			if ( 'class' === $key || ! is_scalar( $value ) || 1 !== preg_match( '/^[a-z][a-z0-9-]{0,63}$/', $key ) ) {
				continue;
			}
			$name = str_starts_with( $key, 'pbs-' ) ? $prefix . '-l-' . substr( $key, 4 ) : $key;
			if ( 1 === preg_match( self::FOOTPRINT, $name ) ) {
				continue;
			}
			$out .= ' data-' . $name . '="' . esc_attr( (string) $value ) . '"';
		}

		return $out;
	}

	/**
	 * Valid `rel` tokens.
	 *
	 * @param string $rel Raw rel.
	 * @return array<int, string>
	 */
	private static function rel_tokens( string $rel ): array {
		$out  = array();
		$list = preg_split( '/\s+/', strtolower( trim( $rel ) ) );
		foreach ( false === $list ? array() : $list as $token ) {
			if ( 1 === preg_match( '/^[a-z]+$/', $token ) ) {
				$out[] = $token;
			}
		}

		return array_values( array_unique( $out ) );
	}
}
