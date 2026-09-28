<?php
/**
 * `pbs/tooltip` and the inline "Tooltip" text format — the WAI-ARIA "Tooltip" pattern.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Blocks\Interactive;

use ZinnDigital\PBS\Assets\Perf\Block_Assets;
use ZinnDigital\PBS\Blocks\Markup;
use ZinnDigital\PBS\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tooltip.
 *
 * The trigger is focusable and names its tip with `aria-describedby`, so a screen reader reads
 * the tip with the trigger whether or not it is shown. The tip shows on hover and on keyboard
 * focus (CSS alone) and is `hidden` until the stylesheet says otherwise, so without CSS it never
 * covers the text. Escape hides it while the pointer or focus stays (the view module) — the one
 * behaviour the pattern asks for that CSS cannot give.
 *
 * The inline format stores `<abbr title="…" data-tooltip="1">word</abbr>` in the post (no class,
 * no footprint; with the plugin off the browser still shows the title on hover) and is rewritten
 * into the same accessible markup when the block that holds it renders. Only an `<abbr>` carrying
 * the format's marker is touched — an abbreviation someone wrote by hand is left alone.
 */
final class Tooltip {

	/** Where the tip sits. */
	private const PLACEMENTS = array( 'top', 'bottom' );

	/**
	 * Hooks (the inline format).
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'render_block', array( self::class, 'inline' ), 20, 2 );
	}

	/**
	 * `pbs/tooltip`.
	 *
	 * @param array<string, mixed> $attributes Attributes: text, tip, placement.
	 * @return string
	 */
	public static function render( array $attributes ): string {
		$text = trim( wp_kses( (string) ( $attributes['text'] ?? '' ), Tabs::inline_tags() ) );
		$tip  = trim( wp_kses( (string) ( $attributes['tip'] ?? '' ), Tabs::inline_tags() ) );
		if ( '' === $text || '' === $tip ) {
			return '';
		}
		$placement = in_array( $attributes['placement'] ?? '', self::PLACEMENTS, true ) ? (string) $attributes['placement'] : 'top';

		return '<div class="' . esc_attr( Frontend::cls( 'tooltip' ) ) . '">'
			. self::markup( '<button type="button" class="' . esc_attr( Frontend::cls( 'tooltip__trigger' ) ) . '"', $text, '</button>', $tip, $placement )
			. '</div>';
	}

	/**
	 * The trigger + tip pair.
	 *
	 * @param string $open      Trigger's opening tag, WITHOUT its closing `>`.
	 * @param string $text      Trigger content (safe HTML).
	 * @param string $close     Trigger's closing tag.
	 * @param string $tip       Tip content (safe HTML).
	 * @param string $placement top|bottom.
	 * @return string
	 */
	private static function markup( string $open, string $text, string $close, string $tip, string $placement ): string {
		$uid = Markup::id( 'tip' );

		return '<span class="' . esc_attr( Frontend::cls( 'tooltip__wrap', 'tooltip__wrap--' . $placement ) ) . '"'
			. Markup::interactive( 'tooltip', array( 'tipHidden' => false ) )
			. ' data-wp-class--' . esc_attr( Frontend::cls( 'is-dismissed' ) ) . '="context.tipHidden"'
			. ' data-wp-on--keydown="actions.tipKey" data-wp-on--mouseleave="actions.tipReset" data-wp-on--focusout="actions.tipReset">'
			. $open . ' aria-describedby="' . esc_attr( $uid ) . '">' . $text . $close
			. '<span class="' . esc_attr( Frontend::cls( 'tooltip__tip' ) ) . '" role="tooltip" id="' . esc_attr( $uid ) . '" hidden>' . $tip . '</span>'
			. '</span>';
	}

	/**
	 * `render_block`: rewrite every inline tooltip in a block's HTML. Cheap for everything else:
	 * one `str_contains()`.
	 *
	 * @param string|mixed         $html  Rendered block.
	 * @param array<string, mixed> $block Parsed block.
	 * @return string|mixed
	 */
	public static function inline( $html, $block ) {
		unset( $block );
		if ( ! is_string( $html ) || ! str_contains( $html, 'data-tooltip=' ) ) {
			return $html;
		}
		$found = false;
		$out   = (string) preg_replace_callback(
			// One format element: no nested abbr inside it (a nested one is left as saved — readable).
			'#<abbr\b([^>]*\bdata-tooltip="1"[^>]*)>((?:(?!</?abbr\b).)*?)</abbr>#is',
			static function ( array $m ) use ( &$found ): string {
				$tip  = preg_match( '/\btitle="([^"]*)"/i', $m[1], $t ) ? $t[1] : '';
				$tip  = trim( wp_kses( html_entity_decode( $tip, ENT_QUOTES | ENT_HTML5, 'UTF-8' ), array() ) );
				$text = trim( wp_kses( $m[2], Tabs::inline_tags() ) );
				if ( '' === $tip || '' === $text ) {
					return $m[2];
				}
				$found = true;

				return self::markup( '<span class="' . esc_attr( Frontend::cls( 'tooltip__trigger', 'tooltip__trigger--inline' ) ) . '" tabindex="0"', $text, '</span>', esc_html( $tip ), 'top' );
			},
			$html
		);
		if ( $found && class_exists( Block_Assets::class ) ) {
			Block_Assets::ensure( 'pbs/tooltip' );
		}

		return $out;
	}
}
