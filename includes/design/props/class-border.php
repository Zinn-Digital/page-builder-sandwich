<?php
/**
 * Design prop `border` (pbs-p4).
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Design\Props;

use ZinnDigital\PBS\Design\Kinds\Sides_Prop;
use ZinnDigital\PBS\Design\Prop;
use ZinnDigital\PBS\Design\Values;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Width, style and colour per logical side:
 * `{blockStart: {width, style, color}, inlineEnd: {...}, blockEnd: {...}, inlineStart: {...}}`.
 * Twin: src/design/props/border.js.
 */
final class Border extends Prop {

	/** Stored key. */
	public const KEY = 'border';

	/** Panel section. */
	public const GROUP = 'border';

	/** Line styles. */
	public const STYLES = array( 'none', 'solid', 'dashed', 'dotted', 'double', 'groove', 'ridge', 'inset', 'outset' );

	/**
	 * {@inheritDoc}
	 *
	 * @param mixed $value Untrusted value.
	 * @return array<string, array<string, mixed>>|null
	 */
	public function sanitize( $value ) {
		if ( ! is_array( $value ) ) {
			return null;
		}
		$out = array();
		foreach ( array_keys( Sides_Prop::SIDES ) as $side ) {
			$in = $value[ $side ] ?? null;
			if ( ! is_array( $in ) ) {
				continue;
			}
			$one   = array();
			$width = Values::length(
				$in['width'] ?? null,
				array(
					'keywords' => array( 'thin', 'medium', 'thick' ),
					'tokens'   => array( 'var' ),
				)
			);
			if ( null !== $width ) {
				$one['width'] = $width;
			}
			$style = Values::choice( $in['style'] ?? null, self::STYLES );
			if ( null !== $style ) {
				$one['style'] = $style;
			}
			$color = Values::color( $in['color'] ?? null );
			if ( null !== $color ) {
				$one['color'] = $color;
			}
			if ( array() !== $one ) {
				$out[ $side ] = $one;
			}
		}

		return array() === $out ? null : $out;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param mixed  $value  Sanitised value.
	 * @param string $prefix Class prefix.
	 * @return array<string, string>
	 */
	public function to_css( $value, string $prefix ): array {
		$out = array();
		foreach ( Sides_Prop::SIDES as $side => $css ) {
			foreach ( array( 'width', 'style', 'color' ) as $k ) {
				if ( isset( $value[ $side ][ $k ] ) ) {
					$out[ 'border-' . $css . '-' . $k ] = Values::css( $value[ $side ][ $k ], $prefix );
				}
			}
		}

		return $out;
	}
}
