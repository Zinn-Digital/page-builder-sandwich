<?php
/**
 * Custom HTML attributes on a block: `data-*` and `aria-*`, set from the block's Advanced section.
 *
 * @package ZinnDigital\PBS
 */

namespace ZinnDigital\PBS\Design;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * - The attributes live in the block's `pbs` attribute as `attrs` (name => value), next to its
 *   styles. One `render_block` filter adds them to the first tag of the block's rendered HTML,
 *   so saved content is never touched: core blocks stay valid and switching the plugin off
 *   leaves clean markup.
 * - Only `data-*` and `aria-*` names are accepted ({@see self::NAME_PATTERN}). Those are the two
 *   families WordPress itself allows on every tag for any author (`_wp_add_global_attributes`),
 *   so this adds nothing an author could not already write. Event handlers (`on*`), `style`,
 *   `href`, `src`, `class`, `id` and every other name are refused here AND in the editor.
 * - Values are plain text: the tag processor escapes them, and control characters are dropped.
 *   At most {@see self::MAX} attributes per block, each value at most {@see self::MAX_VALUE}
 *   characters.
 */
final class Html_Attributes {

	/**
	 * Accepted names: `data-` or `aria-` followed by a lower-case name.
	 *
	 * @var string
	 */
	public const NAME_PATTERN = '/^(?:data-[a-z0-9][a-z0-9_.:-]{0,62}|aria-[a-z]{2,40})$/';

	/**
	 * Attributes per block.
	 *
	 * @var int
	 */
	public const MAX = 20;

	/**
	 * Characters per value.
	 *
	 * @var int
	 */
	public const MAX_VALUE = 500;

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'render_block', array( self::class, 'render_block' ), 11, 2 );
	}

	/**
	 * Is a name allowed?
	 *
	 * @param mixed $name Name.
	 * @return bool
	 */
	public static function allowed( $name ): bool {
		return is_string( $name ) && 1 === preg_match( self::NAME_PATTERN, $name );
	}

	/**
	 * The safe subset of a stored `attrs` value.
	 *
	 * @param mixed $attrs Stored value (name => value).
	 * @return array<string, string>
	 */
	public static function sanitize( $attrs ): array {
		$out = array();
		if ( ! is_array( $attrs ) ) {
			return $out;
		}
		foreach ( $attrs as $name => $value ) {
			if ( count( $out ) >= self::MAX ) {
				break;
			}
			$name = is_string( $name ) ? strtolower( trim( $name ) ) : '';
			if ( ! self::allowed( $name ) || ! ( is_string( $value ) || is_int( $value ) || is_float( $value ) || is_bool( $value ) ) ) {
				continue;
			}
			if ( is_bool( $value ) ) {
				$value = $value ? 'true' : 'false';
			}
			$value = (string) preg_replace( '/[\x00-\x1F\x7F]/u', '', (string) $value );
			if ( function_exists( 'mb_substr' ) ) {
				$value = mb_substr( $value, 0, self::MAX_VALUE );
			} else {
				$value = substr( $value, 0, self::MAX_VALUE );
			}
			$out[ $name ] = $value;
		}

		return $out;
	}

	/**
	 * Add attributes to the first tag of an HTML fragment.
	 *
	 * @param string                $html  HTML.
	 * @param array<string, string> $attrs Sanitized attributes.
	 * @return string
	 */
	public static function apply( string $html, array $attrs ): string {
		if ( array() === $attrs || ! class_exists( '\WP_HTML_Tag_Processor' ) ) {
			return $html;
		}
		$tags = new \WP_HTML_Tag_Processor( $html );
		if ( ! $tags->next_tag() ) {
			return $html;
		}
		foreach ( $attrs as $name => $value ) {
			// Re-checked here: apply() is public and must never write a name sanitize() refuses.
			if ( self::allowed( $name ) ) {
				$tags->set_attribute( $name, $value );
			}
		}

		return $tags->get_updated_html();
	}

	/**
	 * `render_block`.
	 *
	 * @param string|mixed         $html  Rendered HTML.
	 * @param array<string, mixed> $block Parsed block.
	 * @return string|mixed
	 */
	public static function render_block( $html, $block ) {
		if ( ! is_string( $html ) || '' === $html || ! is_array( $block ) || ! is_array( $block['attrs']['pbs']['attrs'] ?? null ) ) {
			return $html;
		}

		return self::apply( $html, self::sanitize( $block['attrs']['pbs']['attrs'] ) );
	}
}
