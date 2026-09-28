<?php
/**
 * Design prop `gap` (pbs-p4).
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
 * Gap between children: one length for both directions, or `{row, column}`.
 * Twin: src/design/props/gap.js.
 */
final class Gap extends Prop {

	/** Stored key. */
	public const KEY = 'gap';

	/** Panel section. */
	public const GROUP = 'layout';

	/** Lands on the element that lays out the children. */
	public const TARGET = 'layout';

	/** Values::length() options. */
	private const OPTS = array(
		'keywords' => array( 'normal' ),
		'tokens'   => array( 'space', 'var' ),
	);

	/**
	 * {@inheritDoc}
	 *
	 * @param mixed $value Untrusted value.
	 * @return array<string, mixed>|null
	 */
	public function sanitize( $value ) {
		if ( is_array( $value ) && ! isset( $value['t'] ) ) {
			$out = array();
			foreach ( array( 'row', 'column' ) as $k ) {
				$v = array_key_exists( $k, $value ) ? Values::length( $value[ $k ], self::OPTS ) : null;
				if ( null !== $v ) {
					$out[ $k ] = $v;
				}
			}
			return array() === $out ? null : $out;
		}
		$v = Values::length( $value, self::OPTS );

		return null === $v ? null : array(
			'row'    => $v,
			'column' => $v,
		);
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
		if ( isset( $value['row'] ) ) {
			$out['row-gap'] = Values::css( $value['row'], $prefix );
		}
		if ( isset( $value['column'] ) ) {
			$out['column-gap'] = Values::css( $value['column'], $prefix );
		}

		return $out;
	}
}
