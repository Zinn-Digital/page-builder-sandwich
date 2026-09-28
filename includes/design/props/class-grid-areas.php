<?php
/**
 * Design prop `gridAreas` (pbs-p4, pbs-d1).
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
 * Named grid areas, one quoted string per row: `"head head" "side main"`.
 * Twin: src/design/props/grid-areas.js.
 */
final class Grid_Areas extends Prop {

	/** Stored key. */
	public const KEY = 'gridAreas';

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
		if ( ! is_string( $value ) || strlen( $value ) > 400 ) {
			return null;
		}
		$value = strtolower( trim( $value ) );
		if ( 1 !== preg_match( '/^(?:"[a-z0-9._ -]+"\s*)+$/', $value ) ) {
			return null;
		}
		preg_match_all( '/"([^"]+)"/', $value, $m );
		$rows = array();
		foreach ( $m[1] as $row ) {
			$row = trim( (string) preg_replace( '/\s+/', ' ', $row ) );
			if ( '' === $row ) {
				return null;
			}
			$rows[] = '"' . $row . '"';
		}

		return implode( ' ', $rows );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param mixed  $value  Sanitised value.
	 * @param string $prefix Class prefix.
	 * @return array<string, string>
	 */
	public function to_css( $value, string $prefix ): array {
		return array( 'grid-template-areas' => (string) $value );
	}
}
