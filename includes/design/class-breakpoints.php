<?php
/**
 * Breakpoints (pbs-d2 free, pbs-d3 Pro).
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Design;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Option `pbsw_breakpoints`: `{tablet: int, mobile: int, custom: [{id, label, max}|{id, label, min}]}`.
 *
 * - base is desktop and has no media query (desktop-first).
 * - tablet and mobile are max-width breakpoints; their widths are editable (free, pbs-d2).
 * - custom entries are Pro (pbs-d3): a `max` entry is a smaller screen (small phone, landscape
 *   tablet), a `min` entry a larger one (large desktop). Without Pro they are kept in the
 *   option but ignored, so a lapsed licence changes nothing stored.
 *
 * Output order (what wins when two apply): base, then `min` entries from the smallest width up,
 * then `max` entries from the largest width down — the narrower the screen, the later its rules.
 */
final class Breakpoints {

	/** Option name. */
	public const OPTION = 'pbsw_breakpoints';

	/** Free defaults. */
	public const DEFAULTS = array(
		'tablet' => 1024,
		'mobile' => 767,
	);

	/** Ids a custom breakpoint may not take. */
	public const RESERVED = array( 'base', 'tablet', 'mobile', 'hover', 'desktop' );

	/** Most custom breakpoints. */
	public const MAX_CUSTOM = 8;

	/**
	 * The stored option, sanitised (custom entries only when allowed).
	 *
	 * @return array{tablet: int, mobile: int, custom: array<int, array<string, mixed>>}
	 */
	public static function get(): array {
		$stored = get_option( self::OPTION, array() );
		$clean  = self::sanitize( is_array( $stored ) ? $stored : array(), true );
		if ( $clean instanceof \WP_Error ) {
			$clean = array_merge( self::DEFAULTS, array( 'custom' => array() ) );
		}
		if ( ! self::custom_allowed() ) {
			$clean['custom'] = array();
		}

		return $clean;
	}

	/**
	 * Whether custom breakpoints are in effect (the premium layer turns this on).
	 *
	 * @return bool
	 */
	public static function custom_allowed(): bool {
		/**
		 * Filters whether custom breakpoints are available (pbs-d3, Pro).
		 *
		 * @param bool $allowed Default false.
		 */
		return (bool) apply_filters( 'pbsw_design_custom_breakpoints', false );
	}

	/**
	 * Validate input. Unknown keys are ignored; a bad width is an error, not a silent default,
	 * so the settings screen can say what is wrong.
	 *
	 * @param array<string, mixed> $in           Input.
	 * @param bool                 $allow_custom Keep custom entries.
	 * @return array{tablet: int, mobile: int, custom: array<int, array<string, mixed>>}|\WP_Error
	 */
	public static function sanitize( array $in, bool $allow_custom ) {
		$tablet = self::int( $in['tablet'] ?? self::DEFAULTS['tablet'] );
		$mobile = self::int( $in['mobile'] ?? self::DEFAULTS['mobile'] );
		if ( null === $tablet || $tablet < 480 || $tablet > 2560 ) {
			return new \WP_Error( 'pbsw_breakpoint_tablet', __( 'The tablet width must be a whole number of pixels from 480 to 2560.', 'page-builder-sandwich' ), array( 'status' => 400 ) );
		}
		if ( null === $mobile || $mobile < 240 || $mobile >= $tablet ) {
			return new \WP_Error( 'pbsw_breakpoint_mobile', __( 'The mobile width must be a whole number of pixels, at least 240 and smaller than the tablet width.', 'page-builder-sandwich' ), array( 'status' => 400 ) );
		}
		$custom = array();
		$seen   = array();
		foreach ( $allow_custom && is_array( $in['custom'] ?? null ) ? $in['custom'] : array() as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$id = is_string( $entry['id'] ?? null ) ? $entry['id'] : '';
			if ( 1 !== preg_match( '/^[a-z][a-z0-9]{1,11}$/', $id ) || in_array( $id, self::RESERVED, true ) || isset( $seen[ $id ] ) ) {
				return new \WP_Error( 'pbsw_breakpoint_id', __( 'Each custom breakpoint needs its own id: 2 to 12 lowercase letters or digits, starting with a letter.', 'page-builder-sandwich' ), array( 'status' => 400 ) );
			}
			$label = trim( sanitize_text_field( (string) ( $entry['label'] ?? '' ) ) );
			$label = '' === $label ? $id : mb_substr( $label, 0, 40 );
			$one   = array(
				'id'    => $id,
				'label' => $label,
			);
			$max   = isset( $entry['max'] ) ? self::int( $entry['max'] ) : null;
			$min   = isset( $entry['min'] ) ? self::int( $entry['min'] ) : null;
			if ( null !== $max && null === $min && $max >= 200 && $max <= 4000 ) {
				$one['max'] = $max;
			} elseif ( null !== $min && null === $max && $min >= 200 && $min <= 6000 ) {
				$one['min'] = $min;
			} else {
				return new \WP_Error( 'pbsw_breakpoint_width', __( 'A custom breakpoint applies either up to a width (200 to 4000 pixels) or from a width (200 to 6000 pixels), not both.', 'page-builder-sandwich' ), array( 'status' => 400 ) );
			}
			$seen[ $id ] = true;
			$custom[]    = $one;
			if ( count( $custom ) >= self::MAX_CUSTOM ) {
				break;
			}
		}

		return array(
			'tablet' => $tablet,
			'mobile' => $mobile,
			'custom' => $custom,
		);
	}

	/**
	 * Save the option.
	 *
	 * @param array<string, mixed> $in Input.
	 * @return array<string, mixed>|\WP_Error The saved value.
	 */
	public static function save( array $in ) {
		$allow = self::custom_allowed();
		$clean = self::sanitize( $in, $allow );
		if ( $clean instanceof \WP_Error ) {
			return $clean;
		}
		if ( ! $allow ) {
			// Keep entries a Pro licence created, untouched, for when it is renewed.
			$stored          = get_option( self::OPTION, array() );
			$clean['custom'] = is_array( $stored ) && is_array( $stored['custom'] ?? null ) ? $stored['custom'] : array();
		}
		update_option( self::OPTION, $clean, true );

		/**
		 * Fires when the breakpoints change (compiled page sheets rebuild on their next view,
		 * because the breakpoints are part of their signature).
		 *
		 * @param array<string, mixed> $clean The saved value.
		 */
		do_action( 'pbsw_breakpoints_changed', $clean );

		return self::get();
	}

	/**
	 * Every breakpoint the compiler writes a media query for, in output order.
	 *
	 * @param array<string, mixed>|null $config A get()-shaped value (tests); null reads the option.
	 * @return array<int, array{id: string, label: string, max?: int, min?: int}>
	 */
	public static function media_list( ?array $config = null ): array {
		$config = $config ?? self::get();
		$mins   = array();
		$maxes  = array(
			array(
				'id'    => 'tablet',
				'label' => __( 'Tablet', 'page-builder-sandwich' ),
				'max'   => (int) $config['tablet'],
			),
			array(
				'id'    => 'mobile',
				'label' => __( 'Mobile', 'page-builder-sandwich' ),
				'max'   => (int) $config['mobile'],
			),
		);
		foreach ( (array) ( $config['custom'] ?? array() ) as $c ) {
			if ( isset( $c['min'] ) ) {
				$mins[] = $c;
			} else {
				$maxes[] = $c;
			}
		}
		usort( $mins, static fn( array $a, array $b ): int => $a['min'] <=> $b['min'] );
		usort( $maxes, static fn( array $a, array $b ): int => $b['max'] <=> $a['max'] );

		return array_merge( $mins, $maxes );
	}

	/**
	 * Ids known to the compiler, base first.
	 *
	 * @param array<string, mixed>|null $config Config (tests).
	 * @return array<int, string>
	 */
	public static function ids( ?array $config = null ): array {
		return array_merge( array( 'base' ), array_column( self::media_list( $config ), 'id' ) );
	}

	/**
	 * The media query that applies a breakpoint's styles.
	 *
	 * @param array<string, mixed> $bp Breakpoint.
	 * @return string
	 */
	public static function media( array $bp ): string {
		return isset( $bp['min'] ) ? '(min-width:' . (int) $bp['min'] . 'px)' : '(max-width:' . (int) $bp['max'] . 'px)';
	}

	/**
	 * The media query covering ONLY one breakpoint's own range ("hide on tablet" hides on tablets,
	 * not on phones too), or '' for a range that is every width.
	 *
	 * @param string                    $id     Breakpoint id (base included).
	 * @param array<string, mixed>|null $config Config (tests).
	 * @return string|null Null for an unknown id.
	 */
	public static function range( string $id, ?array $config = null ): ?string {
		$list  = self::media_list( $config );
		$maxes = array_values( array_map( static fn( array $b ): int => (int) $b['max'], array_filter( $list, static fn( array $b ): bool => isset( $b['max'] ) ) ) );
		$mins  = array_values( array_map( static fn( array $b ): int => (int) $b['min'], array_filter( $list, static fn( array $b ): bool => isset( $b['min'] ) ) ) );
		sort( $maxes );
		sort( $mins );
		$lo = null;
		$hi = null;
		if ( 'base' === $id ) {
			$lo = array() === $maxes ? null : end( $maxes ) + 1;
			$hi = array() === $mins ? null : $mins[0] - 1;
		} else {
			$bp = null;
			foreach ( $list as $b ) {
				if ( $b['id'] === $id ) {
					$bp = $b;
				}
			}
			if ( null === $bp ) {
				return null;
			}
			if ( isset( $bp['max'] ) ) {
				$hi = (int) $bp['max'];
				foreach ( $maxes as $m ) {
					if ( $m < $hi ) {
						$lo = $m + 1;
					}
				}
			} else {
				$lo = (int) $bp['min'];
				foreach ( array_reverse( $mins ) as $m ) {
					if ( $m > $lo ) {
						$hi = $m - 1;
					}
				}
			}
		}
		$parts = array();
		if ( null !== $lo ) {
			$parts[] = '(min-width:' . $lo . 'px)';
		}
		if ( null !== $hi ) {
			$parts[] = '(max-width:' . $hi . 'px)';
		}

		return implode( ' and ', $parts );
	}

	/**
	 * A short hash of what the compiled CSS depends on here (part of the page sheet signature).
	 *
	 * @return string
	 */
	public static function hash(): string {
		return substr( md5( (string) wp_json_encode( self::get() ) ), 0, 12 );
	}

	/**
	 * A whole number from an int or a digit string.
	 *
	 * @param mixed $v Candidate.
	 * @return int|null
	 */
	private static function int( $v ): ?int {
		if ( is_int( $v ) ) {
			return $v;
		}
		if ( is_string( $v ) && 1 === preg_match( '/^\d{1,5}$/', trim( $v ) ) ) {
			return (int) trim( $v );
		}

		return null;
	}
}
