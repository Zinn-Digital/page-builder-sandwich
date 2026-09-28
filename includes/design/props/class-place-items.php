<?php
/**
 * Design prop `placeItems` (pbs-p4, pbs-d1).
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
 * Where grid items sit in their cells: one keyword, or `<block> <inline>`.
 * Twin: src/design/props/place-items.js.
 */
final class Place_Items extends Prop {

	/** Stored key. */
	public const KEY = 'placeItems';

	/** Panel section. */
	public const GROUP = 'layout';

	/** Lands on the element that lays out the children. */
	public const TARGET = 'layout';

	/**
	 * {@inheritDoc}
	 *
	 * @param mixed $value Untrusted value.
	 * @return string|null
	 */
	public function sanitize( $value ) {
		if ( ! is_string( $value ) ) {
			return null;
		}
		$value = strtolower( (string) preg_replace( '/\s+/', ' ', trim( $value ) ) );
		$word  = '(?:start|center|end|stretch|baseline|normal)';

		return 1 === preg_match( '/^' . $word . '(?: ' . $word . ')?$/', $value ) ? $value : null;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param mixed  $value  Sanitised value.
	 * @param string $prefix Class prefix.
	 * @return array<string, string>
	 */
	public function to_css( $value, string $prefix ): array {
		return array( 'place-items' => (string) $value );
	}
}
