<?php
/**
 * A grid track list (pbs-p4, pbs-d1).
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Design\Kinds;

use ZinnDigital\PBS\Design\Prop;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A number of equal tracks (1–24) or a track list such as `1fr 2fr 200px`,
 * `repeat(3,minmax(0,1fr))` or `[full] 1fr [end]`. The visual grid editor writes fr lists.
 */
abstract class Template_Prop extends Prop {

	/** CSS property. */
	public const PROPERTY = '';

	/** One track of a counted template, as `repeat(N,<TRACK>)` writes it. */
	public const TRACK = 'minmax(0,1fr)';

	/** Section. */
	public const GROUP = 'layout';

	/** Lands on the element that lays out the children. */
	public const TARGET = 'layout';

	/** Longest track list accepted. */
	private const MAX = 300;

	/**
	 * {@inheritDoc}
	 *
	 * @param mixed $value Untrusted value.
	 * @return string|null
	 */
	public function sanitize( $value ) {
		if ( is_int( $value ) || ( is_string( $value ) && 1 === preg_match( '/^\d{1,2}$/', trim( $value ) ) ) ) {
			$count = (int) $value;
			return $count >= 1 && $count <= 24 ? 'repeat(' . $count . ',' . static::TRACK . ')' : null;
		}
		if ( ! is_string( $value ) ) {
			return null;
		}
		$value = strtolower( (string) preg_replace( '/\s+/', ' ', trim( $value ) ) );
		if ( '' === $value || strlen( $value ) > self::MAX || 1 !== preg_match( '/^[0-9a-z.%()\-, \[\]]+$/', $value ) ) {
			return null;
		}
		$depth = 0;
		foreach ( str_split( $value ) as $ch ) {
			if ( '(' === $ch || '[' === $ch ) {
				++$depth;
			} elseif ( ( ')' === $ch || ']' === $ch ) && --$depth < 0 ) {
				return null;
			}
		}

		return 0 === $depth ? $value : null;
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
