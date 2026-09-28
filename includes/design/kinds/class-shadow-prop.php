<?php
/**
 * A list of shadows (pbs-p4).
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
 * `none`, a shadow token, or up to four layers `{x, y, blur, spread, color, inset}`.
 */
abstract class Shadow_Prop extends Prop {

	/** CSS property. */
	public const PROPERTY = '';

	/** Section. */
	public const GROUP = 'shadow';

	/** Whether layers take `spread` and `inset` (box shadows do, text shadows do not). */
	public const BOX = true;

	/** Most layers kept. */
	private const MAX_LAYERS = 4;

	/**
	 * {@inheritDoc}
	 *
	 * @param mixed $value Untrusted value.
	 * @return string|array<mixed>|null
	 */
	public function sanitize( $value ) {
		if ( 'none' === $value ) {
			return 'none';
		}
		if ( ! is_array( $value ) ) {
			return null;
		}
		if ( isset( $value['t'] ) ) {
			return Values::token( $value, array( 'shadow', 'var' ) );
		}
		if ( ! array_is_list( $value ) ) {
			return null;
		}
		$layers = array();
		foreach ( array_slice( $value, 0, self::MAX_LAYERS ) as $layer ) {
			if ( ! is_array( $layer ) ) {
				continue;
			}
			$x     = Values::length( $layer['x'] ?? 0, array( 'neg' => true ) );
			$y     = Values::length( $layer['y'] ?? 0, array( 'neg' => true ) );
			$blur  = Values::length( $layer['blur'] ?? 0 );
			$color = Values::color( $layer['color'] ?? null );
			if ( ! is_string( $x ) || ! is_string( $y ) || ! is_string( $blur ) || null === $color ) {
				continue;
			}
			$one = array(
				'x'     => $x,
				'y'     => $y,
				'blur'  => $blur,
				'color' => $color,
			);
			if ( static::BOX ) {
				$spread = Values::length( $layer['spread'] ?? 0, array( 'neg' => true ) );
				if ( ! is_string( $spread ) ) {
					continue;
				}
				$one['spread'] = $spread;
				if ( true === ( $layer['inset'] ?? false ) ) {
					$one['inset'] = true;
				}
			}
			$layers[] = $one;
		}

		return array() === $layers ? null : $layers;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param mixed  $value  Sanitised value.
	 * @param string $prefix Class prefix.
	 * @return array<string, string>
	 */
	public function to_css( $value, string $prefix ): array {
		if ( is_string( $value ) ) {
			return array( static::PROPERTY => $value );
		}
		if ( isset( $value['t'] ) ) {
			return array( static::PROPERTY => Values::token_css( $value, $prefix ) );
		}
		$parts = array();
		foreach ( $value as $l ) {
			$parts[] = ( empty( $l['inset'] ) ? '' : 'inset ' ) . $l['x'] . ' ' . $l['y'] . ' ' . $l['blur']
				. ( static::BOX ? ' ' . $l['spread'] : '' ) . ' ' . Values::css( $l['color'], $prefix );
		}

		return array( static::PROPERTY => implode( ',', $parts ) );
	}
}
