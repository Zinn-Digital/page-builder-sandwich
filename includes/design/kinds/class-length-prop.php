<?php
/**
 * A prop whose value is one CSS length (pbs-p4).
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
 * `PROPERTY: <length>`; OPTS is passed to Values::length().
 */
abstract class Length_Prop extends Prop {

	/** CSS property. */
	public const PROPERTY = '';

	/** Values::length() options. */
	public const OPTS = array();

	/**
	 * {@inheritDoc}
	 *
	 * @param mixed $value Untrusted value.
	 * @return string|array<string, string>|null
	 */
	public function sanitize( $value ) {
		return Values::length( $value, static::OPTS );
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
