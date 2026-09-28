<?php
/**
 * A prop holding one length per logical side (pbs-p4).
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
 * `{blockStart, inlineEnd, blockEnd, inlineStart}` → `<PATTERN with the side>: <length>`.
 * The stored keys are logical, so "start" is the right-hand side in a right-to-left page.
 */
abstract class Sides_Prop extends Prop {

	/** Stored side key → CSS side. Output follows this order. */
	public const SIDES = array(
		'blockStart'  => 'block-start',
		'inlineEnd'   => 'inline-end',
		'blockEnd'    => 'block-end',
		'inlineStart' => 'inline-start',
	);

	/** `sprintf` pattern taking the CSS side, e.g. `padding-%s`. */
	public const PATTERN = '';

	/** Values::length() options. */
	public const OPTS = array();

	/**
	 * {@inheritDoc}
	 *
	 * @param mixed $value Untrusted value.
	 * @return array<string, mixed>|null
	 */
	public function sanitize( $value ) {
		if ( ! is_array( $value ) ) {
			return null;
		}
		$out = array();
		foreach ( array_keys( static::SIDES ) as $side ) {
			if ( array_key_exists( $side, $value ) ) {
				$v = Values::length( $value[ $side ], static::OPTS );
				if ( null !== $v ) {
					$out[ $side ] = $v;
				}
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
		foreach ( static::SIDES as $side => $css ) {
			if ( isset( $value[ $side ] ) ) {
				$out[ sprintf( static::PATTERN, $css ) ] = Values::css( $value[ $side ], $prefix );
			}
		}

		return $out;
	}
}
