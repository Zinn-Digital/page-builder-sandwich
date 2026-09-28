<?php
/**
 * A prop whose value is one of a fixed list (pbs-p4).
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
 * `PROPERTY: <one of CHOICES>`.
 */
abstract class Choice_Prop extends Prop {

	/** CSS property. */
	public const PROPERTY = '';

	/** Allowed values. */
	public const CHOICES = array();

	/**
	 * {@inheritDoc}
	 *
	 * @param mixed $value Untrusted value.
	 * @return string|null
	 */
	public function sanitize( $value ) {
		return Values::choice( $value, static::CHOICES );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param mixed  $value  Sanitised value.
	 * @param string $prefix Class prefix.
	 * @return array<string, string>
	 */
	public function to_css( $value, string $prefix ): array {
		return array( static::PROPERTY => (string) $value );
	}
}
