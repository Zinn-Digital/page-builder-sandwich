<?php
/**
 * Design prop `fontWeight` (pbs-p4).
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Design\Props;

use ZinnDigital\PBS\Design\Prop;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 100–900 in hundreds, `normal` or `bold`. Twin: src/design/props/font-weight.js.
 */
final class Font_Weight extends Prop {

	/** Stored key. */
	public const KEY = 'fontWeight';

	/** Panel section. */
	public const GROUP = 'typography';

	/**
	 * {@inheritDoc}
	 *
	 * @param mixed $value Untrusted value.
	 * @return string|null
	 */
	public function sanitize( $value ) {
		if ( is_int( $value ) ) {
			$value = (string) $value;
		}
		if ( ! is_string( $value ) ) {
			return null;
		}
		$value = strtolower( trim( $value ) );

		return 1 === preg_match( '/^(?:[1-9]00|normal|bold)$/', $value ) ? $value : null;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param mixed  $value  Sanitised value.
	 * @param string $prefix Class prefix.
	 * @return array<string, string>
	 */
	public function to_css( $value, string $prefix ): array {
		return array( 'font-weight' => (string) $value );
	}
}
