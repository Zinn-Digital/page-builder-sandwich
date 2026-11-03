<?php
/**
 * Design prop `transform` (pbs-p4b): rotate, scale, skew and offset.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Design\Props;

use ZinnDigital\PBS\Design\Prop;
use ZinnDigital\PBS\Design\Values;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Transform: `{rotate (deg), scale, skewX (deg), skewY (deg), x (length), y (length)}`, every part
 * optional. Rows, columns and every other block carry it, per breakpoint and on hover.
 *
 * Offset, rotation and scale are written as the INDIVIDUAL transform properties (`translate`,
 * `rotate`, `scale`), so they compose with the Pro hover effects and entrance animations, which
 * set `transform`; only skew needs `transform`. The horizontal offset follows the reading
 * direction (positive = towards the end of the line): it is multiplied by `--<prefix>-tdir`,
 * which the compiler sets to -1 under `[dir=rtl]`. Twin: src/design/props/transform.js.
 */
final class Transform extends Prop {

	/** Stored key. */
	public const KEY = 'transform';

	/** Panel section. */
	public const GROUP = 'transform';

	/** Number parts => [min, max]. */
	public const RANGES = array(
		'rotate' => array( -360, 360 ),
		'scale'  => array( 0, 5 ),
		'skewX'  => array( -80, 80 ),
		'skewY'  => array( -80, 80 ),
	);

	/** Length options for the offsets. */
	public const OFFSET = array( 'neg' => true );

	/**
	 * {@inheritDoc}
	 *
	 * @param mixed $value Untrusted value.
	 * @return array<string, float|string>|null
	 */
	public function sanitize( $value ) {
		if ( ! is_array( $value ) ) {
			return null;
		}
		$out = array();
		foreach ( self::RANGES as $part => $range ) {
			$n = Values::number( $value[ $part ] ?? null, (float) $range[0], (float) $range[1] );
			if ( null !== $n ) {
				$out[ $part ] = $n;
			}
		}
		foreach ( array( 'x', 'y' ) as $part ) {
			$len = Values::length( $value[ $part ] ?? null, self::OFFSET );
			if ( is_string( $len ) ) {
				$out[ $part ] = $len;
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
		$x   = (string) ( $value['x'] ?? '0' );
		$y   = (string) ( $value['y'] ?? '0' );
		if ( '0' !== $x || '0' !== $y ) {
			$out['translate'] = ( '0' === $x ? '0' : 'calc(' . $x . '*var(--' . $prefix . '-tdir,1))' ) . ' ' . $y;
		}
		if ( isset( $value['rotate'] ) ) {
			$out['rotate'] = Values::num( $value['rotate'] ) . 'deg';
		}
		if ( isset( $value['scale'] ) ) {
			$out['scale'] = Values::num( $value['scale'] );
		}
		if ( isset( $value['skewX'] ) || isset( $value['skewY'] ) ) {
			$out['transform'] = 'skew(' . Values::num( $value['skewX'] ?? 0 ) . 'deg,' . Values::num( $value['skewY'] ?? 0 ) . 'deg)';
		}

		return $out;
	}
}
