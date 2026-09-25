<?php
/**
 * The two fixture blocks: sample content and a language switcher.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the fixture blocks.
 *
 * ⭐ They exist for two readers before any real page-builder block does: the rendered-HTML
 * footprint gate (F5) and the translation contract (F6, docs/adr/0033). Each render has a pure
 * half that takes its inputs as arguments, so the contract test can run it without WordPress,
 * and a thin block callback that gathers those inputs from the running site.
 */
final class Fixture {

	/**
	 * Block callback for `pbs/fixture`.
	 *
	 * @param array<string, mixed> $attributes Block attributes (already translated by the
	 *                                         translation plugin's `render_block_data` filter
	 *                                         when one is active).
	 * @return string
	 */
	public static function render_block( array $attributes ): string {
		Assets::enqueue_style( 'front.css' );

		return self::render_content( $attributes, Settings::prefix(), self::current_language() );
	}

	/**
	 * Block callback for `pbs/fixture-switcher`.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	public static function render_switcher_block( array $attributes ): string {
		if ( ! function_exists( 'tranzly_languages' ) || ! function_exists( 'tranzly_language_url' ) ) {
			return '';
		}
		Assets::enqueue_style( 'front.css' );

		$languages = array();
		foreach ( (array) tranzly_languages() as $language ) {
			if ( ! is_array( $language ) || empty( $language['code'] ) ) {
				continue;
			}
			$language['url'] = (string) tranzly_language_url( (string) $language['code'] );
			$languages[]     = $language;
		}

		return self::render_switcher( $attributes, Settings::prefix(), $languages, self::current_language() );
	}

	/**
	 * The sample content block's HTML.
	 *
	 * @param array<string, mixed> $attributes `heading`, `body`, `tone`.
	 * @param string               $prefix     The class prefix.
	 * @param string|null          $language   The current language, if known.
	 * @return string
	 */
	public static function render_content( array $attributes, string $prefix, ?string $language ): string {
		$heading = trim( (string) ( $attributes['heading'] ?? '' ) );
		$body    = trim( (string) ( $attributes['body'] ?? '' ) );
		$tone    = 'accent' === ( $attributes['tone'] ?? '' ) ? 'accent' : 'plain';

		if ( '' === $heading && '' === $body ) {
			return '';
		}

		$html = '<div class="' . esc_attr( Frontend::classes( $prefix, 'fx', 'fx--' . $tone ) ) . '"' . Frontend::lang_attr( $language ) . '>';
		if ( '' !== $heading ) {
			$html .= '<h2 class="' . esc_attr( Frontend::classes( $prefix, 'fx__title' ) ) . '">' . esc_html( $heading ) . '</h2>';
		}
		if ( '' !== $body ) {
			$html .= '<p class="' . esc_attr( Frontend::classes( $prefix, 'fx__body' ) ) . '">' . esc_html( $body ) . '</p>';
		}

		return $html . '</div>';
	}

	/**
	 * The language switcher's HTML.
	 *
	 * @param array<string, mixed>             $attributes `label`, `showCurrent`.
	 * @param string                           $prefix     The class prefix.
	 * @param array<int, array<string, mixed>> $languages  Each with `code`, `name`, `url`.
	 * @param string|null                      $current    The current language code.
	 * @return string
	 */
	public static function render_switcher( array $attributes, string $prefix, array $languages, ?string $current ): string {
		if ( count( $languages ) < 2 ) {
			return '';
		}

		$label = trim( (string) ( $attributes['label'] ?? '' ) );
		if ( '' === $label ) {
			$label = __( 'Languages', 'page-builder-sandwich' );
		}
		$show_current = false !== ( $attributes['showCurrent'] ?? true );

		$items = '';
		foreach ( $languages as $language ) {
			$code       = (string) $language['code'];
			$is_current = null !== $current && 0 === strcasecmp( $code, $current );
			if ( $is_current && ! $show_current ) {
				continue;
			}
			$name   = (string) ( $language['name'] ?? $code );
			$bcp47  = str_replace( '_', '-', $code );
			$class  = $is_current ? Frontend::classes( $prefix, 'ls__item', 'ls__item--current' ) : Frontend::classes( $prefix, 'ls__item' );
			$items .= '<li class="' . esc_attr( $class ) . '"><a href="' . esc_url( (string) ( $language['url'] ?? '' ) ) . '" hreflang="' . esc_attr( $bcp47 ) . '" lang="' . esc_attr( $bcp47 ) . '"'
				. ( $is_current ? ' aria-current="true"' : '' ) . '>' . esc_html( $name ) . '</a></li>';
		}

		return '<nav class="' . esc_attr( Frontend::classes( $prefix, 'ls' ) ) . '" aria-label="' . esc_attr( $label ) . '"><ul class="' . esc_attr( Frontend::classes( $prefix, 'ls__list' ) ) . '">' . $items . '</ul></nav>';
	}

	/**
	 * The current language from the translation plugin's API, when it is active.
	 *
	 * @return string|null
	 */
	private static function current_language(): ?string {
		if ( ! function_exists( 'tranzly_current_language' ) ) {
			return null;
		}
		$language = (string) tranzly_current_language();

		return '' === $language ? null : $language;
	}
}
