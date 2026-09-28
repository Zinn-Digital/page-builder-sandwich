<?php
/**
 * Value validators shared by every design prop (pbs-p4).
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Design;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The pure helpers the props are built from. Mirrored line for line by src/design/values.js;
 * wp/tests/fixtures/pbs-design/props.json holds both sides to the same output.
 *
 * ⛔ Every function returns either a value that is SAFE to print inside a CSS declaration, or
 * null. A value that is not recognised is dropped, never echoed: nothing here can emit `;`, `{`,
 * `}`, `<`, a quote or a backslash, so a stored attribute cannot break out of its declaration.
 */
final class Values {

	/** Token types and the CSS custom property each resolves to. */
	public const TOKEN_TYPES = array( 'color', 'gradient', 'size', 'space', 'font', 'shadow', 'radius', 'var' );

	/** CSS length units accepted after a number. */
	private const UNITS = 'px|em|rem|%|vw|vh|vmin|vmax|svh|lvh|dvh|svw|lvw|dvw|vi|vb|ch|ex|lh|fr|cqi|cqb';

	/** Longest function value (calc, clamp, min, max) accepted. */
	private const MAX_FUNCTION = 120;

	/**
	 * A token reference `{"t":"color","v":"primary"}` whose type is one of $types.
	 *
	 * @param mixed              $value Candidate.
	 * @param array<int, string> $types Allowed token types.
	 * @return array{t: string, v: string}|null
	 */
	public static function token( $value, array $types ): ?array {
		if ( ! is_array( $value ) || ! isset( $value['t'], $value['v'] ) || ! is_string( $value['t'] ) || ! is_string( $value['v'] ) ) {
			return null;
		}
		if ( ! in_array( $value['t'], $types, true ) || ! in_array( $value['t'], self::TOKEN_TYPES, true ) ) {
			return null;
		}
		if ( 1 !== preg_match( '/^[a-z0-9][a-z0-9-]{0,63}$/', $value['v'] ) ) {
			return null;
		}

		return array(
			't' => $value['t'],
			'v' => $value['v'],
		);
	}

	/**
	 * The CSS for a token: a theme.json preset variable, or a Pro token variable that falls back
	 * to the theme.json preset of the same slug where one exists.
	 *
	 * @param array{t: string, v: string} $token  Token.
	 * @param string                      $prefix Class prefix.
	 * @return string
	 */
	public static function token_css( array $token, string $prefix ): string {
		$v = $token['v'];
		switch ( $token['t'] ) {
			case 'color':
				return 'var(--wp--preset--color--' . $v . ')';
			case 'gradient':
				return 'var(--wp--preset--gradient--' . $v . ')';
			case 'size':
				return 'var(--wp--preset--font-size--' . $v . ')';
			case 'space':
				return 'var(--' . $prefix . '-space-' . $v . ',var(--wp--preset--spacing--' . $v . '))';
			case 'font':
				return 'var(--' . $prefix . '-font-' . $v . ',var(--wp--preset--font-family--' . $v . '))';
			case 'shadow':
				return 'var(--' . $prefix . '-shadow-' . $v . ',var(--wp--preset--shadow--' . $v . '))';
			default: // radius, var.
				return 'var(--' . $prefix . '-' . $token['t'] . '-' . $v . ')';
		}
	}

	/**
	 * A sanitised value (string or token) as CSS.
	 *
	 * @param string|array{t: string, v: string} $value  Value from a validator.
	 * @param string                             $prefix Class prefix.
	 * @return string
	 */
	public static function css( $value, string $prefix ): string {
		return is_array( $value ) ? self::token_css( $value, $prefix ) : (string) $value;
	}

	/**
	 * A number as CSS writes it: at most four decimals, no trailing zeros, never `-0`.
	 *
	 * @param float|int $n Number.
	 * @return string
	 */
	public static function num( $n ): string {
		$n = (float) $n;
		if ( abs( $n ) < 0.00005 ) {
			return '0';
		}
		$s = rtrim( rtrim( sprintf( '%.4F', $n ), '0' ), '.' );

		return '-0' === $s ? '0' : $s;
	}

	/**
	 * A finite number within a range, or null. Numeric strings are accepted.
	 *
	 * @param mixed $value Candidate.
	 * @param float $min   Minimum.
	 * @param float $max   Maximum.
	 * @return float|null
	 */
	public static function number( $value, float $min, float $max ): ?float {
		if ( is_string( $value ) && 1 === preg_match( '/^-?(?:\d+(?:\.\d+)?|\.\d+)$/', trim( $value ) ) ) {
			$value = (float) trim( $value );
		}
		if ( ! is_int( $value ) && ! is_float( $value ) ) {
			return null;
		}
		$value = (float) $value;
		if ( ! is_finite( $value ) || $value < $min || $value > $max ) {
			return null;
		}

		return $value;
	}

	/**
	 * A CSS length: a number (px), a number with a unit, a keyword, calc/clamp/min/max, or a token.
	 *
	 * @param mixed                $value Candidate.
	 * @param array<string, mixed> $opts  neg (bool), unitless (bool: a bare number stays unitless),
	 *                                    keywords (string[]), tokens (string[]).
	 * @return string|array{t: string, v: string}|null
	 */
	public static function length( $value, array $opts = array() ) {
		$neg      = ! empty( $opts['neg'] );
		$unitless = ! empty( $opts['unitless'] );
		$keywords = (array) ( $opts['keywords'] ?? array() );
		$tokens   = (array) ( $opts['tokens'] ?? array() );

		if ( is_array( $value ) ) {
			return self::token( $value, $tokens );
		}
		if ( is_int( $value ) || is_float( $value ) ) {
			if ( ! is_finite( (float) $value ) || ( ! $neg && $value < 0 ) || abs( (float) $value ) > 100000 ) {
				return null;
			}
			return self::num( $value ) . ( $unitless || 0.0 === (float) $value ? '' : 'px' );
		}
		if ( ! is_string( $value ) ) {
			return null;
		}
		$value = strtolower( trim( $value ) );
		if ( '' === $value ) {
			return null;
		}
		if ( in_array( $value, $keywords, true ) ) {
			return $value;
		}
		if ( 1 === preg_match( '/^(-?)((?:\d+(?:\.\d+)?|\.\d+))(' . self::UNITS . ')?$/', $value, $m ) ) {
			if ( '-' === $m[1] && ! $neg ) {
				return null;
			}
			$n    = (float) ( $m[1] . $m[2] );
			$unit = $m[3] ?? '';
			if ( abs( $n ) > 100000 ) {
				return null;
			}
			if ( '' === $unit && ! $unitless && 0.0 !== $n ) {
				return null; // A bare number other than 0 is not a length.
			}
			return self::num( $n ) . ( 0.0 === $n && ! $unitless && '%' !== $unit && 'fr' !== $unit ? '' : $unit );
		}

		return self::func( $value );
	}

	/**
	 * `calc()`, `clamp()`, `min()` or `max()` over numbers, units and operators only.
	 *
	 * @param string $value Lower-cased, trimmed candidate.
	 * @return string|null
	 */
	public static function func( string $value ): ?string {
		if ( strlen( $value ) > self::MAX_FUNCTION || 1 !== preg_match( '/^(calc|clamp|min|max)\([0-9a-z.%+\-*\/ ,()]*\)$/', $value ) ) {
			return null;
		}
		// Every word must be a unit or one of the four functions: nothing else can run or load.
		preg_match_all( '/[a-z]+/', $value, $words );
		$allowed = array_merge( explode( '|', self::UNITS ), array( 'calc', 'clamp', 'min', 'max' ) );
		foreach ( $words[0] as $word ) {
			if ( ! in_array( $word, $allowed, true ) ) {
				return null;
			}
		}
		$depth = 0;
		foreach ( str_split( $value ) as $ch ) {
			if ( '(' === $ch ) {
				++$depth;
			} elseif ( ')' === $ch && --$depth < 0 ) {
				return null;
			}
		}

		return 0 === $depth ? $value : null;
	}

	/**
	 * A CSS colour: hex, a colour function, a keyword, or a colour/var token.
	 *
	 * @param mixed $value Candidate.
	 * @return string|array{t: string, v: string}|null
	 */
	public static function color( $value ) {
		if ( is_array( $value ) ) {
			return self::token( $value, array( 'color', 'var' ) );
		}
		if ( ! is_string( $value ) ) {
			return null;
		}
		$value = strtolower( trim( $value ) );
		if ( 1 === preg_match( '/^#(?:[0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/', $value ) ) {
			return $value;
		}
		if ( strlen( $value ) <= 80 && 1 === preg_match( '/^(?:rgba?|hsla?|hwb|lab|lch|oklab|oklch)\([0-9a-z.%, \/+\-]+\)$/', $value ) ) {
			return $value;
		}
		if ( 1 === preg_match( '/^[a-z]{3,20}$/', $value ) ) {
			return $value; // transparent, currentcolor, a named colour.
		}

		return null;
	}

	/**
	 * One of a fixed set of strings.
	 *
	 * @param mixed              $value   Candidate.
	 * @param array<int, string> $allowed Allowed values.
	 * @return string|null
	 */
	public static function choice( $value, array $allowed ): ?string {
		return is_string( $value ) && in_array( $value, $allowed, true ) ? $value : null;
	}
}
