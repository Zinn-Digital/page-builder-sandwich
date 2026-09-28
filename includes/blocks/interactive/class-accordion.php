<?php
/**
 * `pbs/accordion` + `pbs/accordion-item` — native disclosure widgets (`<details>`/`<summary>`).
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Blocks\Interactive;

use ZinnDigital\PBS\Blocks\Markup;
use ZinnDigital\PBS\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Accordion.
 *
 * Each item is a `<details>` element, so it opens and closes with no script at all, by mouse,
 * keyboard (Enter / Space on its summary) and assistive technology, and the browser's
 * find-in-page opens a closed item that holds the match. "One open at a time" is the `name`
 * attribute (an exclusive accordion) — also native. No view module is loaded.
 *
 * FAQ structured data (optional) is one JSON-LD `FAQPage` built from the items the visitor sees
 * (question = title, answer = the item's text): the same content, never extra.
 */
final class Accordion {

	/**
	 * `pbs/accordion`.
	 *
	 * @param array<string, mixed> $attributes Attributes: single, faq.
	 * @param string               $content    Rendered inner content.
	 * @param mixed                $block      WP_Block.
	 * @return string
	 */
	public static function render( array $attributes, string $content, $block ): string {
		unset( $content );
		$group = ! empty( $attributes['single'] ) ? Markup::id( 'accordion' ) : '';
		$items = '';
		$faq   = array();
		foreach ( Markup::children( $block ) as [ $child, $html ] ) {
			if ( 'pbs/accordion-item' !== $child->name || '' === trim( $html ) ) {
				continue;
			}
			$items .= '' === $group ? $html : Markup::add_attributes( $html, array( 'name' => $group ) );
			if ( ! empty( $attributes['faq'] ) ) {
				$question = trim( wp_strip_all_tags( (string) ( $child->attributes['title'] ?? '' ) ) );
				$answer   = trim( (string) preg_replace( '/\s+/', ' ', wp_strip_all_tags( Markup::inner( $child, '' ) ) ) );
				if ( '' !== $question && '' !== $answer ) {
					$faq[] = array(
						'@type'          => 'Question',
						'name'           => $question,
						'acceptedAnswer' => array(
							'@type' => 'Answer',
							'text'  => $answer,
						),
					);
				}
			}
		}
		if ( '' === $items ) {
			return '';
		}
		$html = '<div class="' . esc_attr( Frontend::cls( 'accordion' ) ) . '">' . $items . '</div>';
		if ( array() !== $faq ) {
			// WordPress's own helper prints the element (filterable, CSP-aware); JSON_HEX_TAG keeps a
			// `</script>` in an answer from closing it.
			$html .= wp_get_inline_script_tag(
				(string) wp_json_encode(
					array(
						'@context'   => 'https://schema.org',
						'@type'      => 'FAQPage',
						'mainEntity' => $faq,
					),
					JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
				),
				array( 'type' => 'application/ld+json' )
			);
		}

		return $html;
	}

	/**
	 * `pbs/accordion-item`.
	 *
	 * @param array<string, mixed> $attributes Attributes: title, open, level.
	 * @param string               $content    Rendered inner content.
	 * @param mixed                $block      WP_Block.
	 * @return string
	 */
	public static function render_item( array $attributes, string $content, $block ): string {
		$title = trim( wp_kses( (string) ( $attributes['title'] ?? '' ), Tabs::inline_tags() ) );
		$body  = Markup::inner( $block, $content );
		if ( '' === $title && '' === trim( $body ) ) {
			return '';
		}
		if ( '' === $title ) {
			$title = esc_html__( 'More', 'page-builder-sandwich' );
		}
		$level = (int) ( $attributes['level'] ?? 0 );
		$label = '<span class="' . esc_attr( Frontend::cls( 'accordion-item__title' ) ) . '">' . $title . '</span>';
		if ( $level >= 2 && $level <= 6 ) {
			$label = '<h' . $level . ' class="' . esc_attr( Frontend::cls( 'accordion-item__heading' ) ) . '">' . $label . '</h' . $level . '>';
		}

		return '<details class="' . esc_attr( Frontend::cls( 'accordion-item' ) ) . '"' . ( ! empty( $attributes['open'] ) ? ' open' : '' ) . '>'
			. '<summary class="' . esc_attr( Frontend::cls( 'accordion-item__summary' ) ) . '">' . $label
			. '<svg class="' . esc_attr( Frontend::cls( 'accordion-item__icon' ) ) . '" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m6 9 6 6 6-6"/></svg>'
			. '</summary><div class="' . esc_attr( Frontend::cls( 'accordion-item__content' ) ) . '">' . $body . '</div></details>';
	}
}
