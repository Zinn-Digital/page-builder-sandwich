<?php
/**
 * How many grid tracks a block spans (pbs-p4, pbs-d1).
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Design\Kinds;

use ZinnDigital\PBS\Design\Prop;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 1–24 tracks → `PROPERTY: span N`; `full` → `PROPERTY: 1 / -1` (every track). The visual grid
 * editor writes it when a child is dragged across cells.
 */
abstract class Span_Prop extends Prop {

	/** CSS property. */
	public const PROPERTY = '';

	/** Section. */
	public const GROUP = 'layout';

	/**
	 * {@inheritDoc}
	 *
	 * @param mixed $value Untrusted value.
	 * @return int|string|null
	 */
	public function sanitize( $value ) {
		if ( 'full' === $value ) {
			return 'full';
		}
		if ( is_string( $value ) && 1 === preg_match( '/^\d{1,2}$/', $value ) ) {
			$value = (int) $value;
		}
		if ( ! is_int( $value ) || $value < 1 || $value > 24 ) {
			return null;
		}

		return $value;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param mixed  $value  Sanitised value.
	 * @param string $prefix Class prefix.
	 * @return array<string, string>
	 */
	public function to_css( $value, string $prefix ): array {
		return array( static::PROPERTY => 'full' === $value ? '1/-1' : 'span ' . (int) $value );
	}
}
