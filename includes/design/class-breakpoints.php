<?php
/**
 * Breakpoints: the tablet and mobile widths a site can set.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Design;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Option `pbsw_breakpoints`: `{tablet: int, mobile: int}`.
 *
 * - base is desktop and has no media query (desktop-first).
 * - tablet and mobile are max-width breakpoints; their widths are editable.
 * - This class reads and writes only those two widths. Any other key in the option belongs to
 *   whatever extension wrote it and is carried through a save unchanged.
 *
 * Output order (what wins when two apply): base, then the largest width down to the smallest,
 * so the narrower the screen, the later its rules.
 */
final class Breakpoints {

	/** Option name. */
	public const OPTION = 'pbsw_breakpoints';

	/** Free defaults. */
	public const DEFAULTS = array(
		'tablet' => 1024,
		'mobile' => 767,
	);

	/**
	 * The stored widths, sanitised.
	 *
	 * @return array<string, mixed> `tablet`, `mobile`, and anything an extension adds.
	 */
	public static function get(): array {
		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();
		$clean  = self::sanitize( $stored );
		if ( $clean instanceof \WP_Error ) {
			$clean = self::DEFAULTS;
		}

		/**
		 * Filters the breakpoints in effect.
		 *
		 * @param array<string, mixed> $clean  The sanitised widths.
		 * @param array<string, mixed> $stored The raw option.
		 */
		$filtered = apply_filters( 'pbsw_design_breakpoints', $clean, $stored );

		return is_array( $filtered ) ? $filtered : $clean;
	}

	/**
	 * Validate the two widths. Unknown keys are ignored; a bad width is an error, not a silent
	 * default, so the settings screen can say what is wrong.
	 *
	 * @param array<string, mixed> $in Input.
	 * @return array{tablet: int, mobile: int}|\WP_Error
	 */
	public static function sanitize( array $in ) {
		$tablet = self::int( $in['tablet'] ?? self::DEFAULTS['tablet'] );
		$mobile = self::int( $in['mobile'] ?? self::DEFAULTS['mobile'] );
		if ( null === $tablet || $tablet < 480 || $tablet > 2560 ) {
			return new \WP_Error( 'pbsw_breakpoint_tablet', __( 'The tablet width must be a whole number of pixels from 480 to 2560.', 'page-builder-sandwich' ), array( 'status' => 400 ) );
		}
		if ( null === $mobile || $mobile < 240 || $mobile >= $tablet ) {
			return new \WP_Error( 'pbsw_breakpoint_mobile', __( 'The mobile width must be a whole number of pixels, at least 240 and smaller than the tablet width.', 'page-builder-sandwich' ), array( 'status' => 400 ) );
		}

		return array(
			'tablet' => $tablet,
			'mobile' => $mobile,
		);
	}

	/**
	 * Save the option. Keys this class does not own stay exactly as they were stored.
	 *
	 * @param array<string, mixed> $in Input.
	 * @return array<string, mixed>|\WP_Error The saved value.
	 */
	public static function save( array $in ) {
		$clean = self::sanitize( $in );
		if ( $clean instanceof \WP_Error ) {
			return $clean;
		}
		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();

		/**
		 * Filters the value about to be saved. Return a WP_Error to refuse the save.
		 *
		 * @param array<string, mixed> $clean  The sanitised widths.
		 * @param array<string, mixed> $in     The raw input.
		 * @param array<string, mixed> $stored The raw option.
		 */
		$filtered = apply_filters( 'pbsw_design_breakpoints_save', $clean, $in, $stored );
		if ( $filtered instanceof \WP_Error ) {
			return $filtered;
		}
		$value = array_merge( $stored, is_array( $filtered ) ? $filtered : $clean );
		update_option( self::OPTION, $value, true );

		/**
		 * Fires when the breakpoints change (compiled page sheets rebuild on their next view,
		 * because the breakpoints are part of their signature).
		 *
		 * @param array<string, mixed> $value The saved value.
		 */
		do_action( 'pbsw_breakpoints_changed', $value );

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

		/**
		 * Filters the breakpoints the compiler writes a media query for, in output order.
		 *
		 * @param array<int, array<string, mixed>> $list   Tablet, then mobile.
		 * @param array<string, mixed>             $config The get()-shaped value.
		 */
		$filtered = apply_filters( 'pbsw_design_media_list', $maxes, $config );

		return is_array( $filtered ) ? $filtered : $maxes;
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
		$query = '(max-width:' . (int) ( $bp['max'] ?? 0 ) . 'px)';

		/**
		 * Filters the media query for one breakpoint.
		 *
		 * @param string               $query The query.
		 * @param array<string, mixed> $bp    The breakpoint.
		 */
		return (string) apply_filters( 'pbsw_design_media_query', $query, $bp );
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
		$list = self::media_list( $config );

		/**
		 * Short-circuits the range of one breakpoint. Null keeps the calculation below.
		 *
		 * @param string|null                      $range Null.
		 * @param string                           $id    Breakpoint id.
		 * @param array<int, array<string, mixed>> $list  Every breakpoint.
		 */
		$range = apply_filters( 'pbsw_design_breakpoint_range', null, $id, $list );
		if ( is_string( $range ) ) {
			return $range;
		}
		$maxes = array_map( 'intval', array_column( $list, 'max' ) );
		sort( $maxes );
		$lo = null;
		$hi = null;
		if ( 'base' === $id ) {
			$lo = array() === $maxes ? null : end( $maxes ) + 1;
		} else {
			$own = array_column( $list, 'max', 'id' );
			if ( ! isset( $own[ $id ] ) ) {
				return null;
			}
			$hi = (int) $own[ $id ];
			foreach ( $maxes as $m ) {
				if ( $m < $hi ) {
					$lo = $m + 1;
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
