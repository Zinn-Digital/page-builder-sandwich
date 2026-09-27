<?php
/**
 * Inline CSS declaration sanitiser.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sanitises a `style` attribute value (a list of declarations).
 *
 * Two layers, and a declaration must pass both:
 *
 * 1. our own parser, which runs everywhere (including the unit suite, where WordPress is not
 *    loaded): it splits on `;` outside quotes and brackets, requires a plain property name,
 *    and refuses any value carrying markup, escapes, braces, `expression(`, `javascript:`,
 *    `@import`, or a `url()` that is not an http(s)/relative image on a background property;
 * 2. WordPress's own `safecss_filter_attr()`, with the legacy layout properties the old
 *    builder wrote (flex*, position, justify-content, fill …) added to its allow-list for
 *    the duration of the call only.
 *
 * Output is `prop: value; prop: value` — normalised, so it is stable across saves.
 */
final class Css {

	/** Properties the legacy builder wrote that core's safecss list may not carry. */
	private const LEGACY_PROPERTIES = array(
		'display',
		'position',
		'top',
		'right',
		'bottom',
		'left',
		'z-index',
		'justify-content',
		'align-items',
		'align-self',
		'align-content',
		'flex',
		'flex-basis',
		'flex-direction',
		'flex-flow',
		'flex-grow',
		'flex-shrink',
		'flex-wrap',
		'order',
		'gap',
		'row-gap',
		'column-gap',
		'fill',
		'stroke',
		'opacity',
		'box-shadow',
		'text-shadow',
		'text-transform',
		'font-style',
		'min-height',
		'max-height',
		'min-width',
		'max-width',
		'background-attachment',
		'background-size',
		'background-position',
		'background-repeat',
		'overflow',
		'transform',
		'transition',
	);

	/** Properties that may carry an image `url()`. */
	private const URL_PROPERTIES = array( 'background', 'background-image' );

	/**
	 * Sanitise a declaration list.
	 *
	 * @param string $css Untrusted declarations, e.g. `min-height: 380px; color: #333`.
	 * @return string
	 */
	public static function sanitize_declarations( string $css ): string {
		$out = array();
		foreach ( self::split( $css ) as $decl ) {
			$clean = self::declaration( $decl );
			if ( null !== $clean ) {
				$out[] = $clean;
			}
		}

		return implode( '; ', $out );
	}

	/**
	 * Clean one declaration, or null to drop it.
	 *
	 * @param string $decl `prop: value`.
	 * @return string|null
	 */
	private static function declaration( string $decl ): ?string {
		$pos = strpos( $decl, ':' );
		if ( false === $pos ) {
			return null;
		}
		$prop  = strtolower( trim( substr( $decl, 0, $pos ) ) );
		$value = trim( substr( $decl, $pos + 1 ) );
		if ( '' === $value || 1 !== preg_match( '/^-?[a-z][a-z0-9-]*$/', $prop ) || strlen( $value ) > 2000 ) {
			return null;
		}
		// Markup, escapes, braces, comments, at-rules, legacy IE script hooks.
		if ( 1 === preg_match( '/[<>{}\\\\]|\/\*|@import|expression\s*\(|behavior\s*:|-moz-binding|javascript:|vbscript:/i', $value ) ) {
			return null;
		}
		// A fixed-position block can cover the whole page (clickjacking overlay).
		if ( 'position' === $prop && 1 === preg_match( '/fixed|sticky/i', $value ) ) {
			return null;
		}
		if ( 1 === preg_match( '/url\s*\(/i', $value ) && ! self::url_ok( $prop, $value ) ) {
			return null;
		}

		$decl = $prop . ': ' . $value;
		if ( function_exists( 'safecss_filter_attr' ) ) {
			$decl = self::with_wp( $decl );
		}

		return '' === $decl ? null : $decl;
	}

	/**
	 * Whether every `url()` in a value is an http(s) or root-relative image on a property that
	 * may carry one.
	 *
	 * @param string $prop  Property.
	 * @param string $value Value.
	 * @return bool
	 */
	private static function url_ok( string $prop, string $value ): bool {
		if ( ! in_array( $prop, self::URL_PROPERTIES, true ) ) {
			return false;
		}
		$count = preg_match_all( '/url\s*\(\s*([\'"]?)([^\'")\s]*)\1\s*\)/i', $value, $m );
		if ( 0 === $count || substr_count( strtolower( $value ), 'url' ) !== $count ) {
			return false;
		}
		foreach ( $m[2] as $url ) {
			if ( 1 !== preg_match( '#^(https?:)?//[^\s]+$|^/[^/\s][^\s]*$#i', $url ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Run one declaration through core's safecss_filter_attr() with the legacy properties
	 * allowed for this call only.
	 *
	 * @param string $decl `prop: value`.
	 * @return string
	 */
	private static function with_wp( string $decl ): string {
		$allow = static function ( $props ) {
			return array_values( array_unique( array_merge( (array) $props, self::LEGACY_PROPERTIES ) ) );
		};
		// ⛔ safecss_filter_attr() rejects any value that still holds a `(` once var()/calc()/
		// min()/max()/clamp()/repeat() are removed — so every `rgb()`/`rgba()`/`hsl()`/`hsla()`
		// colour, which legacy wrote on nearly every row, column and button, was silently dropped
		// (measured: a white 85% row background and every button colour lost after conversion).
		// Colour functions whose arguments are only numbers, units, commas and slashes are
		// masked with an inert token for the check and put back afterwards.
		$masked = self::mask_colors( $decl );
		add_filter( 'safe_style_css', $allow );
		$clean = safecss_filter_attr( $masked[0] );
		remove_filter( 'safe_style_css', $allow );

		return self::unmask_colors( trim( (string) $clean, " \t\n\r\0\x0B;" ), $masked[1] );
	}

	/**
	 * Replace each strictly-numeric colour function with an inert placeholder token.
	 *
	 * @param string $decl Declaration.
	 * @return array{0:string,1:array<int,string>} Masked declaration and the originals, in order.
	 */
	public static function mask_colors( string $decl ): array {
		$found  = array();
		$masked = (string) preg_replace_callback(
			'/\b(?:rgba?|hsla?)\(\s*[0-9.%+\-\s,\/a-z]*\)/i',
			static function ( array $m ) use ( &$found ): string {
				// Only digits, units (deg/turn/rad/%), separators: nothing a browser could load or run.
				if ( 1 !== preg_match( '/^[a-z]+\(\s*(?:[0-9.+\-]+(?:%|deg|turn|rad|grad)?[\s,\/]*)+\)$/i', $m[0] ) ) {
					return $m[0];
				}
				$found[] = $m[0];
				return 'zzcolor' . ( count( $found ) - 1 ) . 'zz';
			},
			$decl
		);
		// Then gradients — `linear-gradient(rgba(…), rgba(…)), url(…)` is how legacy tinted a
		// row's background photo, and safecss only accepts a gradient that is the WHOLE value.
		// Its arguments, colours already masked, may only be words, numbers, units and `#`.
		$masked = (string) preg_replace_callback(
			'/\b(?:repeating-)?(?:linear|radial|conic)-gradient\(([^()]*)\)/i',
			static function ( array $m ) use ( &$found ): string {
				if ( 1 !== preg_match( '/^[a-z0-9.%#,\s\/-]*$/i', $m[1] ) ) {
					return $m[0];
				}
				$found[] = $m[0];
				return 'zzcolor' . ( count( $found ) - 1 ) . 'zz';
			},
			$masked
		);

		return array( $masked, $found );
	}

	/**
	 * Put the colour functions mask_colors() took out back in.
	 *
	 * @param string             $decl  Checked declaration.
	 * @param array<int, string> $found Originals.
	 * @return string
	 */
	public static function unmask_colors( string $decl, array $found ): string {
		if ( array() === $found ) {
			return $decl;
		}

		// A gradient's own placeholders hold colour placeholders: unmask until none are left.
		$pass = 0;
		while ( $pass < 3 && str_contains( $decl, 'zzcolor' ) ) {
			++$pass;
			$decl = (string) preg_replace_callback(
				'/zzcolor(\d+)zz/',
				static fn( array $m ): string => $found[ (int) $m[1] ] ?? '',
				$decl
			);
		}

		return $decl;
	}

	/**
	 * Split on `;` outside quotes and brackets.
	 *
	 * @param string $css Declarations.
	 * @return array<int, string>
	 */
	private static function split( string $css ): array {
		$parts = array();
		$buf   = '';
		$depth = 0;
		$quote = '';
		$len   = strlen( $css );
		for ( $i = 0; $i < $len; $i++ ) {
			$c = $css[ $i ];
			if ( '' !== $quote ) {
				if ( $c === $quote ) {
					$quote = '';
				}
			} elseif ( '"' === $c || "'" === $c ) {
				$quote = $c;
			} elseif ( '(' === $c ) {
				++$depth;
			} elseif ( ')' === $c ) {
				$depth = max( 0, $depth - 1 );
			} elseif ( ';' === $c && 0 === $depth ) {
				$parts[] = $buf;
				$buf     = '';
				continue;
			}
			$buf .= $c;
		}
		$parts[] = $buf;

		return array_values( array_filter( array_map( 'trim', $parts ), static fn( $p ) => '' !== $p ) );
	}
}
