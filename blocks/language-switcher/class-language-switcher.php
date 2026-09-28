<?php
/**
 * `pbs/language-switcher`: Page Builder Sandwich's language switcher element (Tranzly tz-l2).
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ⭐ THE PBS ↔ TRANZLY CONTRACT (docs/adr/0033 §D, §E3). The element owns its SETTINGS (design,
 * colours, spacing — the styling a Page Builder Sandwich user expects) and Tranzly owns the
 * LANGUAGES and the accessible markup: this calls Tranzly's public `tranzly_language_switcher()`
 * after a `function_exists()` test, so Page Builder Sandwich still works, and prints nothing,
 * without Tranzly. Nothing of Tranzly's is imported.
 */
final class Language_Switcher {

	/** Style variables the element may set (Tranzly's Switcher::STYLE_VARS keys). */
	private const VARS = array( 'color', 'background', 'activeColor', 'activeBg', 'borderColor', 'radius', 'gap', 'fontSize' );

	/**
	 * Block callback.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	public static function render( array $attributes ): string {
		if ( ! function_exists( 'tranzly_language_switcher' ) ) {
			return '';
		}

		return (string) tranzly_language_switcher( self::args( $attributes ) );
	}

	/**
	 * Block attributes as Tranzly's switcher arguments (the pure half).
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, mixed>
	 */
	public static function args( array $attributes ): array {
		$vars = array();
		foreach ( (array) ( $attributes['vars'] ?? array() ) as $key => $value ) {
			if ( in_array( $key, self::VARS, true ) && is_string( $value ) && '' !== $value ) {
				$vars[ $key ] = $value;
			}
		}

		return array(
			'style'       => (string) ( $attributes['design'] ?? 'pills' ),
			'display'     => (string) ( $attributes['display'] ?? 'name' ),
			'flags'       => ! empty( $attributes['flags'] ),
			'showCurrent' => false !== ( $attributes['showCurrent'] ?? true ),
			'hideMissing' => ! empty( $attributes['hideMissing'] ),
			'vertical'    => ! empty( $attributes['vertical'] ),
			'label'       => (string) ( $attributes['label'] ?? '' ),
			'vars'        => $vars,
		);
	}
}
