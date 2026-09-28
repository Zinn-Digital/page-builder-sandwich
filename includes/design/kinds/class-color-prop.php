<?php
/**
 * A prop whose value is one colour (pbs-p4).
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
 * `PROPERTY: <colour>`.
 */
abstract class Color_Prop extends Prop {

	/** CSS property. */
	public const PROPERTY = '';

	/** Section. */
	public const GROUP = 'colour';

	/**
	 * {@inheritDoc}
	 *
	 * @param mixed $value Untrusted value.
	 * @return string|array<string, string>|null
	 */
	public function sanitize( $value ) {
		return Values::color( $value );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param mixed  $value  Sanitised value.
	 * @param string $prefix Class prefix.
	 * @return array<string, string>
	 */
	public function to_css( $value, string $prefix ): array {
		return array( static::PROPERTY => Values::css( $value, $prefix ) );
	}
}
