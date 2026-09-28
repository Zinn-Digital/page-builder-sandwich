<?php
/**
 * Design prop `gridArea` (pbs-p4, pbs-d1).
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
 * Place this block in a named area of its parent grid. Twin: src/design/props/grid-area.js.
 */
final class Grid_Area extends Prop {

	/** Stored key. */
	public const KEY = 'gridArea';

	/** Panel section. */
	public const GROUP = 'layout';

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
		$value = strtolower( trim( $value ) );

		return 1 === preg_match( '/^[a-z_][a-z0-9_-]{0,31}$/', $value ) ? $value : null;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param mixed  $value  Sanitised value.
	 * @param string $prefix Class prefix.
	 * @return array<string, string>
	 */
	public function to_css( $value, string $prefix ): array {
		return array( 'grid-area' => (string) $value );
	}
}
