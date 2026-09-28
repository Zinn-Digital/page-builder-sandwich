<?php
/**
 * `pbs/tabs` + `pbs/tab` — the WAI-ARIA Authoring Practices "Tabs" pattern (automatic activation).
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
 * Tabs.
 *
 * The server renders the whole widget in its initial state: the tablist (one `<button role=tab>`
 * per child, from the child's title), each child's panel with `role=tabpanel`, the ids that tie
 * them together, and every panel but the selected one `hidden`. The view module
 * (assets/modules/tabs.js) only moves the selection: click, and the keyboard the pattern asks
 * for — arrows (mirrored in right-to-left), Home, End. No layout shift when it loads.
 */
final class Tabs {

	/**
	 * `pbs/tabs`.
	 *
	 * @param array<string, mixed> $attributes Attributes: label, vertical, selected.
	 * @param string               $content    Rendered inner content.
	 * @param mixed                $block      WP_Block.
	 * @return string
	 */
	public static function render( array $attributes, string $content, $block ): string {
		unset( $content );
		$children = array();
		foreach ( Markup::children( $block ) as [ $child, $html ] ) {
			if ( 'pbs/tab' === $child->name && '' !== trim( $html ) ) {
				$children[] = array( $child, $html );
			}
		}
		if ( array() === $children ) {
			return '';
		}
		$count    = count( $children );
		$selected = max( 0, min( $count - 1, (int) ( $attributes['selected'] ?? 0 ) ) );
		$vertical = ! empty( $attributes['vertical'] );
		$uid      = Markup::id( 'tabs' );

		$list  = '<div class="' . esc_attr( Frontend::cls( 'tabs__list' ) ) . '" role="tablist"'
			. ( $vertical ? ' aria-orientation="vertical"' : '' );
		$label = trim( (string) ( $attributes['label'] ?? '' ) );
		if ( '' !== $label ) {
			$list .= ' aria-label="' . esc_attr( $label ) . '"';
		}
		$list  .= '>';
		$panels = '';
		foreach ( $children as $i => [ $child, $html ] ) {
			$title = trim( wp_kses( (string) ( $child->attributes['title'] ?? '' ), self::inline_tags() ) );
			if ( '' === $title ) {
				/* translators: %d: the number of a tab (1, 2, 3…). */
				$title = esc_html( sprintf( __( 'Tab %d', 'page-builder-sandwich' ), $i + 1 ) );
			}
			$on      = $i === $selected;
			$list   .= '<button type="button" role="tab" class="' . esc_attr( Frontend::cls( 'tabs__tab' ) ) . '"'
				. ' id="' . esc_attr( $uid . '-tab-' . $i ) . '" aria-controls="' . esc_attr( $uid . '-panel-' . $i ) . '"'
				. ' aria-selected="' . ( $on ? 'true' : 'false' ) . '" tabindex="' . ( $on ? '0' : '-1' ) . '"'
				. Markup::context( array( 'tabsI' => $i ) )
				. ' data-wp-bind--aria-selected="state.tabsSelected" data-wp-bind--tabindex="state.tabsTabindex"'
				. ' data-wp-on--click="actions.tabsSelect" data-wp-on--keydown="actions.tabsKey">'
				. $title . '</button>';
			$panels .= Markup::add_attributes(
				$html,
				array(
					'role'                 => 'tabpanel',
					'id'                   => $uid . '-panel-' . $i,
					'aria-labelledby'      => $uid . '-tab-' . $i,
					'tabindex'             => '0',
					'hidden'               => ! $on,
					'data-wp-context'      => (string) wp_json_encode( array( 'tabsI' => $i ) ),
					'data-wp-bind--hidden' => '!state.tabsSelected',
				)
			);
		}
		$list .= '</div>';

		return '<div class="' . esc_attr( Frontend::cls( 'tabs', $vertical ? 'tabs--vertical' : '' ) ) . '"'
			. Markup::interactive(
				'tabs',
				array(
					'tabsSel'   => $selected,
					'tabsCount' => $count,
				)
			)
			. '>' . $list . '<div class="' . esc_attr( Frontend::cls( 'tabs__panels' ) ) . '">' . $panels . '</div></div>';
	}

	/**
	 * `pbs/tab`: one panel. Its title is printed by the parent's tab button.
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 * @param string               $content    Rendered inner content.
	 * @param mixed                $block      WP_Block.
	 * @return string
	 */
	public static function render_tab( array $attributes, string $content, $block ): string {
		unset( $attributes );

		return '<div class="' . esc_attr( Frontend::cls( 'tab' ) ) . '">' . Markup::inner( $block, $content ) . '</div>';
	}

	/**
	 * The inline formatting a title may keep (the rest is text).
	 *
	 * @return array<string, array<string, bool>>
	 */
	public static function inline_tags(): array {
		return array(
			'em'     => array(),
			'strong' => array(),
			'code'   => array(),
			'span'   => array( 'lang' => true ),
			'br'     => array(),
		);
	}
}
