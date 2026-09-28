<?php
/**
 * Design prop `backgroundImage` (pbs-p4).
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
 * `{url, id, position, size, repeat, attachment}`. `id` is the Media Library attachment (the
 * editor keeps it to reopen the picker); only `url` reaches CSS.
 * Twin: src/design/props/background-image.js.
 */
final class Background_Image extends Prop {

	/** Stored key. */
	public const KEY = 'backgroundImage';

	/** Panel section. */
	public const GROUP = 'colour';

	/** Positions (logical-safe: no left/right keywords — use percentages). */
	private const POSITION = '/^(?:center|top|bottom|center top|center bottom|\d{1,3}% \d{1,3}%)$/';

	/** Repeat values. */
	private const REPEAT = array( 'no-repeat', 'repeat', 'repeat-x', 'repeat-y', 'space', 'round' );

	/** Attachment values. */
	private const ATTACHMENT = array( 'scroll', 'fixed', 'local' );

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
		$url = self::url( $value['url'] ?? null );
		if ( null === $url ) {
			return null;
		}
		$out = array( 'url' => $url );
		if ( is_int( $value['id'] ?? null ) && $value['id'] > 0 ) {
			$out['id'] = $value['id'];
		}
		$position = is_string( $value['position'] ?? null ) ? strtolower( trim( $value['position'] ) ) : '';
		if ( 1 === preg_match( self::POSITION, $position ) ) {
			$out['position'] = $position;
		}
		$size = Values::length( $value['size'] ?? null, array( 'keywords' => array( 'auto', 'cover', 'contain' ) ) );
		if ( is_string( $size ) ) {
			$out['size'] = $size;
		}
		$repeat = Values::choice( $value['repeat'] ?? null, self::REPEAT );
		if ( null !== $repeat ) {
			$out['repeat'] = $repeat;
		}
		$attachment = Values::choice( $value['attachment'] ?? null, self::ATTACHMENT );
		if ( null !== $attachment ) {
			$out['attachment'] = $attachment;
		}

		return $out;
	}

	/**
	 * An http(s), protocol-relative or root-relative URL with no character that could end the
	 * `url("…")` token.
	 *
	 * @param mixed $url Candidate.
	 * @return string|null
	 */
	public static function url( $url ): ?string {
		if ( ! is_string( $url ) ) {
			return null;
		}
		$url = trim( $url );
		if ( strlen( $url ) > 2000 || 1 !== preg_match( '#^(?:https?://|//|/)[^\s"\'()\\\\<>]*$#i', $url ) ) {
			return null;
		}

		return $url;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param mixed  $value  Sanitised value.
	 * @param string $prefix Class prefix.
	 * @return array<string, string>
	 */
	public function to_css( $value, string $prefix ): array {
		$out = array( 'background-image' => 'url("' . $value['url'] . '")' );
		foreach ( array( 'position', 'size', 'repeat', 'attachment' ) as $k ) {
			if ( isset( $value[ $k ] ) ) {
				$out[ 'background-' . $k ] = (string) $value[ $k ];
			}
		}

		return $out;
	}
}
