<?php
/**
 * Design prop `transition` (pbs-p4).
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
 * How hover changes animate: `{property, duration (ms), easing, delay (ms)}`. A visitor who asks
 * for reduced motion gets no transition (Compiler adds the media rule).
 * Twin: src/design/props/transition.js.
 */
final class Transition extends Prop {

	/** Stored key. */
	public const KEY = 'transition';

	/** Panel section. */
	public const GROUP = 'advanced';

	/** Named property sets → the CSS property list. */
	public const PROPERTIES = array(
		'all'       => 'all',
		'colors'    => 'color,background-color,border-color',
		'opacity'   => 'opacity',
		'transform' => 'transform',
		'shadow'    => 'box-shadow',
	);

	/** Easing functions. */
	public const EASINGS = array( 'ease', 'linear', 'ease-in', 'ease-out', 'ease-in-out' );

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
		$duration = Values::number( $value['duration'] ?? null, 0, 10000 );
		if ( null === $duration ) {
			return null;
		}
		$property = is_string( $value['property'] ?? null ) && isset( self::PROPERTIES[ $value['property'] ] ) ? $value['property'] : 'all';
		$out      = array(
			'property' => $property,
			'duration' => (int) round( $duration ),
			'easing'   => Values::choice( $value['easing'] ?? null, self::EASINGS ) ?? 'ease',
		);
		$delay    = Values::number( $value['delay'] ?? null, 0, 10000 );
		if ( null !== $delay && $delay > 0 ) {
			$out['delay'] = (int) round( $delay );
		}

		return $out;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param mixed  $value  Sanitised value.
	 * @param string $prefix Class prefix.
	 * @return array<string, string>
	 */
	public function to_css( $value, string $prefix ): array {
		$out = array(
			'transition-property'        => self::PROPERTIES[ $value['property'] ],
			'transition-duration'        => $value['duration'] . 'ms',
			'transition-timing-function' => $value['easing'],
		);
		if ( isset( $value['delay'] ) ) {
			$out['transition-delay'] = $value['delay'] . 'ms';
		}

		return $out;
	}
}
