<?php
/**
 * SVG sanitiser for icons.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Core;

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOMDocument's own properties (documentElement, localName, namespaceURI, childNodes) are camelCase.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rebuilds an SVG from a strict allow-list of elements and attributes.
 *
 * ⛔ The legacy plugin ran uploaded SVG through a `wp_kses` list that allowed `style` on almost
 * every element and `href`/`xlink:href` on `<a>` and `<image>`, and never touched the file on
 * disk (docs/plugins-overhaul/10-audit-pbs.md §3, "SVG upload"). This class is the replacement:
 *
 * - the input is PARSED (DOMDocument, LIBXML_NONET, no entity substitution, any DOCTYPE refused)
 *   and a NEW document is written from the parts that pass, so nothing the parser tolerated but
 *   the allow-list did not name can survive by being left in place;
 * - an element not on the list is dropped WITH its subtree (`script`, `foreignObject`, `a`,
 *   `image`, `iframe`, `style`, `animate`, `set` …);
 * - every `on*` attribute, every `href` that is not a same-document `#fragment`, every
 *   `javascript:`/`data:` value, and every `style` carrying `url(` are dropped;
 * - `class` is dropped: an icon never needs one and a legacy one may carry the plugin's name.
 *
 * The output is canonical, so sanitising twice gives the same bytes (asserted in the tests).
 */
final class Svg {

	/** The SVG namespace. */
	private const NS = 'http://www.w3.org/2000/svg';

	/** The XLink namespace. */
	private const XLINK = 'http://www.w3.org/1999/xlink';

	/** Allowed elements (lower-case local name => canonical spelling). */
	public const ELEMENTS = array(
		'svg'            => 'svg',
		'g'              => 'g',
		'path'           => 'path',
		'circle'         => 'circle',
		'ellipse'        => 'ellipse',
		'line'           => 'line',
		'polyline'       => 'polyline',
		'polygon'        => 'polygon',
		'rect'           => 'rect',
		'title'          => 'title',
		'desc'           => 'desc',
		'defs'           => 'defs',
		'symbol'         => 'symbol',
		'use'            => 'use',
		'lineargradient' => 'linearGradient',
		'radialgradient' => 'radialGradient',
		'stop'           => 'stop',
		'clippath'       => 'clipPath',
		'mask'           => 'mask',
		'text'           => 'text',
		'tspan'          => 'tspan',
	);

	/** Allowed attributes (lower-case name => canonical spelling). */
	public const ATTRIBUTES = array(
		'viewbox'             => 'viewBox',
		'preserveaspectratio' => 'preserveAspectRatio',
		'width'               => 'width',
		'height'              => 'height',
		'x'                   => 'x',
		'y'                   => 'y',
		'x1'                  => 'x1',
		'y1'                  => 'y1',
		'x2'                  => 'x2',
		'y2'                  => 'y2',
		'cx'                  => 'cx',
		'cy'                  => 'cy',
		'r'                   => 'r',
		'rx'                  => 'rx',
		'ry'                  => 'ry',
		'fx'                  => 'fx',
		'fy'                  => 'fy',
		'd'                   => 'd',
		'points'              => 'points',
		'transform'           => 'transform',
		'fill'                => 'fill',
		'fill-opacity'        => 'fill-opacity',
		'fill-rule'           => 'fill-rule',
		'clip-rule'           => 'clip-rule',
		'clip-path'           => 'clip-path',
		'mask'                => 'mask',
		'stroke'              => 'stroke',
		'stroke-width'        => 'stroke-width',
		'stroke-linecap'      => 'stroke-linecap',
		'stroke-linejoin'     => 'stroke-linejoin',
		'stroke-miterlimit'   => 'stroke-miterlimit',
		'stroke-dasharray'    => 'stroke-dasharray',
		'stroke-dashoffset'   => 'stroke-dashoffset',
		'stroke-opacity'      => 'stroke-opacity',
		'opacity'             => 'opacity',
		'offset'              => 'offset',
		'stop-color'          => 'stop-color',
		'stop-opacity'        => 'stop-opacity',
		'gradientunits'       => 'gradientUnits',
		'gradienttransform'   => 'gradientTransform',
		'spreadmethod'        => 'spreadMethod',
		'clippathunits'       => 'clipPathUnits',
		'maskunits'           => 'maskUnits',
		'font-size'           => 'font-size',
		'font-family'         => 'font-family',
		'font-weight'         => 'font-weight',
		'text-anchor'         => 'text-anchor',
		'dominant-baseline'   => 'dominant-baseline',
		'id'                  => 'id',
		'role'                => 'role',
		'aria-hidden'         => 'aria-hidden',
		'aria-label'          => 'aria-label',
		'focusable'           => 'focusable',
		'style'               => 'style',
		'href'                => 'href',
		'xlink:href'          => 'xlink:href',
	);

	/** Attributes that may hold `url(#id)` (a same-document paint server or clip). */
	private const PAINT = array( 'fill', 'stroke', 'clip-path', 'mask' );

	/**
	 * Sanitise an SVG document. Returns '' when the input is not a well-formed SVG.
	 *
	 * @param string $svg Untrusted SVG markup.
	 * @return string
	 */
	public static function sanitize( string $svg ): string {
		$svg = trim( $svg );
		if ( '' === $svg || strlen( $svg ) > 512000 ) {
			return '';
		}
		// A DOCTYPE is how entities (and XXE) get in. An icon never needs one.
		if ( false !== stripos( $svg, '<!DOCTYPE' ) || false !== stripos( $svg, '<!ENTITY' ) ) {
			return '';
		}
		// Drop an XML declaration: the result is embedded in HTML.
		$svg = (string) preg_replace( '/^<\?xml[^>]*\?>\s*/i', '', $svg );

		$doc      = new \DOMDocument();
		$internal = libxml_use_internal_errors( true );
		$loaded   = $doc->loadXML( $svg, LIBXML_NONET | LIBXML_NOCDATA | LIBXML_COMPACT );
		libxml_clear_errors();
		libxml_use_internal_errors( $internal );
		if ( ! $loaded || null === $doc->documentElement ) {
			return '';
		}

		$root = $doc->documentElement;
		if ( 'svg' !== strtolower( (string) $root->localName ) ) {
			return '';
		}

		return self::element( $root, true );
	}

	/**
	 * Rebuild one element and its allowed children.
	 *
	 * @param \DOMElement $el   The element.
	 * @param bool        $root Whether this is the document element.
	 * @return string
	 */
	private static function element( \DOMElement $el, bool $root = false ): string {
		$name = strtolower( (string) $el->localName );
		$ns   = $el->namespaceURI;
		if ( ! isset( self::ELEMENTS[ $name ] ) || ( null !== $ns && '' !== $ns && self::NS !== $ns ) ) {
			return '';
		}
		$tag = self::ELEMENTS[ $name ];

		$attrs = $root ? array( 'xmlns' => self::NS ) : array();
		foreach ( $el->attributes ?? array() as $attr ) {
			if ( ! $attr instanceof \DOMAttr ) {
				continue;
			}
			$key = self::attribute_name( $attr );
			if ( null === $key ) {
				continue;
			}
			$value = self::attribute_value( $key, (string) $attr->value );
			if ( null !== $value ) {
				$attrs[ self::ATTRIBUTES[ $key ] ] = $value;
			}
		}
		if ( isset( $attrs['xlink:href'] ) ) {
			$attrs['xmlns:xlink'] = self::XLINK;
		}

		$children = '';
		foreach ( $el->childNodes as $child ) {
			if ( $child instanceof \DOMElement ) {
				$children .= self::element( $child );
			} elseif ( $child instanceof \DOMText && ! $child instanceof \DOMCdataSection && in_array( $name, array( 'title', 'desc', 'text', 'tspan' ), true ) ) {
				$children .= htmlspecialchars( $child->data, ENT_QUOTES | ENT_XML1, 'UTF-8' );
			}
			// Comments, processing instructions and CDATA are never copied.
		}

		$out = '<' . $tag;
		foreach ( $attrs as $key => $value ) {
			$out .= ' ' . $key . '="' . htmlspecialchars( $value, ENT_QUOTES | ENT_XML1, 'UTF-8' ) . '"';
		}

		return '' === $children ? $out . '/>' : $out . '>' . $children . '</' . $tag . '>';
	}

	/**
	 * The allow-list key for an attribute, or null to drop it.
	 *
	 * @param \DOMAttr $attr The attribute.
	 * @return string|null
	 */
	private static function attribute_name( \DOMAttr $attr ): ?string {
		$local = strtolower( (string) $attr->localName );
		$ns    = $attr->namespaceURI;
		if ( self::XLINK === $ns ) {
			return 'href' === $local ? 'xlink:href' : null;
		}
		if ( null !== $ns && '' !== $ns ) {
			return null;
		}
		if ( 'xlink:href' === $local ) {
			return null;
		}

		return isset( self::ATTRIBUTES[ $local ] ) ? $local : null;
	}

	/**
	 * A cleaned attribute value, or null to drop the attribute.
	 *
	 * @param string $key   Allow-list key.
	 * @param string $value Raw value.
	 * @return string|null
	 */
	private static function attribute_value( string $key, string $value ): ?string {
		$value = trim( $value );
		// Remove whitespace and control characters before looking for a scheme, so
		// `java&#x09;script:` and friends cannot hide one.
		$squashed = strtolower( (string) preg_replace( '/[\x00-\x20]+/', '', $value ) );
		if ( str_contains( $squashed, 'javascript:' ) || str_contains( $squashed, 'data:' ) || str_contains( $squashed, 'vbscript:' ) ) {
			return null;
		}

		if ( 'href' === $key || 'xlink:href' === $key ) {
			return 1 === preg_match( '/^#[A-Za-z_][\w.:-]*$/', $value ) ? $value : null;
		}

		if ( 'style' === $key ) {
			if ( 1 === preg_match( '/url\s*\(|expression|@import|behavior|-moz-binding|[\\\\<>{}]/i', $value ) ) {
				return null;
			}
			return $value;
		}

		if ( str_contains( $squashed, 'url(' ) ) {
			if ( in_array( $key, self::PAINT, true ) && 1 === preg_match( '/^url\(\s*[\'"]?#[A-Za-z_][\w.:-]*[\'"]?\s*\)(\s+[#\w(),.%\s-]*)?$/', $value ) ) {
				return $value;
			}
			return null;
		}

		return $value;
	}

	/**
	 * The allow-list in wp_kses() form, so post content that carries an icon keeps it when an
	 * author without `unfiltered_html` saves (kses would otherwise strip every SVG tag and the
	 * block would no longer match its saved markup).
	 *
	 * @return array<string, array<string, bool>>
	 */
	public static function kses_allowed(): array {
		$attrs = array();
		foreach ( array_keys( self::ATTRIBUTES ) as $key ) {
			if ( 'style' === $key || 'href' === $key || 'xlink:href' === $key ) {
				continue;
			}
			$attrs[ $key ] = true;
		}
		$attrs['xmlns'] = true;

		$out = array();
		foreach ( array_keys( self::ELEMENTS ) as $name ) {
			if ( 'use' === $name ) {
				continue;
			}
			$out[ $name ] = $attrs;
		}

		return $out;
	}
}
