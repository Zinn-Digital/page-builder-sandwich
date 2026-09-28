<?php
/**
 * Design prop `fontFamily` (pbs-p4).
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
 * A font token (theme.json preset or Pro font) or a family list such as
 * `"Open Sans", sans-serif`. Twin: src/design/props/font-family.js.
 */
final class Font_Family extends Prop {

	/** Stored key. */
	public const KEY = 'fontFamily';

	/** Panel section. */
	public const GROUP = 'typography';

	/**
	 * {@inheritDoc}
	 *
	 * @param mixed $value Untrusted value.
	 * @return string|array<string, string>|null
	 */
	public function sanitize( $value ) {
		if ( is_array( $value ) ) {
			return Values::token( $value, array( 'font', 'var' ) );
		}
		if ( ! is_string( $value ) ) {
			return null;
		}
		$value = trim( (string) preg_replace( '/\s+/', ' ', $value ) );
		if ( '' === $value || strlen( $value ) > 200 || 1 !== preg_match( '/^[A-Za-z0-9 ,\'"\-]+$/', $value ) ) {
			return null;
		}
		// Quotes must pair up, or a stray one would swallow the rest of the declaration.
		if ( 0 !== substr_count( $value, '"' ) % 2 || 0 !== substr_count( $value, "'" ) % 2 ) {
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
		return array( 'font-family' => Values::css( $value, $prefix ) );
	}
}
