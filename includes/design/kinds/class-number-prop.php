<?php
/**
 * A prop whose value is one bounded number (pbs-p4).
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Design\Kinds;

use ZinnDigital\PBS\Design\Prop;
use ZinnDigital\PBS\Design\Values;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * `PROPERTY: <number in MIN..MAX>` (whole numbers only when INTEGER).
 */
abstract class Number_Prop extends Prop {

	/** CSS property. */
	public const PROPERTY = '';

	/** Smallest value. */
	public const MIN = 0;

	/** Largest value. */
	public const MAX = 1;

	/** Whole numbers only. */
	public const INTEGER = false;

	/**
	 * {@inheritDoc}
	 *
	 * @param mixed $value Untrusted value.
	 * @return float|null
	 */
	public function sanitize( $value ) {
		$n = Values::number( $value, (float) static::MIN, (float) static::MAX );
		if ( null === $n || ( static::INTEGER && floor( $n ) !== $n ) ) {
			return null;
		}

		return $n;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param mixed  $value  Sanitised value.
	 * @param string $prefix Class prefix.
	 * @return array<string, string>
	 */
	public function to_css( $value, string $prefix ): array {
		return array( static::PROPERTY => Values::num( (float) $value ) );
	}
}
