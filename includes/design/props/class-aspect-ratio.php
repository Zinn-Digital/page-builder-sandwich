<?php
/**
 * Design prop `aspectRatio` (pbs-p4).
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
 * `auto`, a number, or `W / H`. Twin: src/design/props/aspect-ratio.js.
 */
final class Aspect_Ratio extends Prop {

	/** Stored key. */
	public const KEY = 'aspectRatio';

	/** Panel section. */
	public const GROUP = 'size';

	/**
	 * {@inheritDoc}
	 *
	 * @param mixed $value Untrusted value.
	 * @return string|null
	 */
	public function sanitize( $value ) {
		if ( is_int( $value ) || is_float( $value ) ) {
			$n = Values::number( $value, 0.01, 100 );
			return null === $n ? null : Values::num( $n );
		}
		if ( ! is_string( $value ) ) {
			return null;
		}
		$value = strtolower( trim( $value ) );
		if ( 'auto' === $value ) {
			return 'auto';
		}
		if ( 1 !== preg_match( '/^(\d+(?:\.\d+)?)(?:\s*\/\s*(\d+(?:\.\d+)?))?$/', $value, $m ) ) {
			return null;
		}
		$w = (float) $m[1];
		$h = isset( $m[2] ) ? (float) $m[2] : null;
		if ( $w <= 0 || $w > 10000 || ( null !== $h && ( $h <= 0 || $h > 10000 ) ) ) {
			return null;
		}

		return Values::num( $w ) . ( null === $h ? '' : '/' . Values::num( $h ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param mixed  $value  Sanitised value.
	 * @param string $prefix Class prefix.
	 * @return array<string, string>
	 */
	public function to_css( $value, string $prefix ): array {
		return array( 'aspect-ratio' => (string) $value );
	}
}
