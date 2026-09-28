<?php
/**
 * `pbs/modal` and `pbs/off-canvas` — the WAI-ARIA "Dialog (Modal)" pattern on the native `<dialog>`.
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
 * Modal dialog and off-canvas panel: one renderer, two looks.
 *
 * A trigger button opens a `<dialog>` with `showModal()` (the view module), so the browser does
 * what the pattern asks for: focus moves into the dialog, the page behind is inert, Escape
 * closes it and focus returns to the trigger. The close button is a `<form method="dialog">`,
 * which closes it natively. The dialog is labelled by its title.
 */
final class Dialog {

	/** Allowed sizes (modal) and sides (off-canvas). */
	private const SIZES = array( 'small', 'medium', 'large' );
	private const SIDES = array( 'start', 'end' );

	/**
	 * Render.
	 *
	 * @param string               $kind       `modal` or `off-canvas`.
	 * @param array<string, mixed> $attributes Attributes: trigger, title, size|side, backdropClose.
	 * @param string               $content    Rendered inner content.
	 * @param mixed                $block      WP_Block.
	 * @return string
	 */
	public static function render( string $kind, array $attributes, string $content, $block ): string {
		$body    = Markup::inner( $block, $content );
		$trigger = trim( wp_kses( (string) ( $attributes['trigger'] ?? '' ), Tabs::inline_tags() ) );
		$title   = trim( wp_kses( (string) ( $attributes['title'] ?? '' ), Tabs::inline_tags() ) );
		if ( '' === trim( $body ) && '' === $title ) {
			return '';
		}
		if ( '' === $trigger ) {
			$trigger = esc_html__( 'Open', 'page-builder-sandwich' );
		}
		if ( '' === $title ) {
			$title = $trigger;
		}
		$variant  = 'modal' === $kind
			? ( in_array( $attributes['size'] ?? '', self::SIZES, true ) ? (string) $attributes['size'] : 'medium' )
			: ( in_array( $attributes['side'] ?? '', self::SIDES, true ) ? (string) $attributes['side'] : 'end' );
		$uid      = Markup::id( $kind );
		$backdrop = ! isset( $attributes['backdropClose'] ) || ! empty( $attributes['backdropClose'] );

		return '<div class="' . esc_attr( Frontend::cls( $kind ) ) . '"'
			. Markup::interactive( 'modal', array( 'dialogId' => $uid ) ) . '>'
			. '<button type="button" class="' . esc_attr( Frontend::cls( $kind . '__trigger' ) ) . '" aria-haspopup="dialog" aria-controls="' . esc_attr( $uid ) . '"'
			. ' data-wp-on--click="actions.dialogOpen">' . $trigger . '</button>'
			. '<dialog class="' . esc_attr( Frontend::cls( $kind . '__dialog', $kind . '__dialog--' . $variant ) ) . '" id="' . esc_attr( $uid ) . '"'
			. ' aria-labelledby="' . esc_attr( $uid . '-title' ) . '"'
			. ( $backdrop ? ' data-wp-on--click="actions.dialogBackdrop"' : '' ) . '>'
			. '<div class="' . esc_attr( Frontend::cls( $kind . '__inner' ) ) . '">'
			. '<div class="' . esc_attr( Frontend::cls( $kind . '__header' ) ) . '">'
			. '<h2 class="' . esc_attr( Frontend::cls( $kind . '__title' ) ) . '" id="' . esc_attr( $uid . '-title' ) . '">' . $title . '</h2>'
			. '<form method="dialog" class="' . esc_attr( Frontend::cls( $kind . '__close-form' ) ) . '">'
			. '<button class="' . esc_attr( Frontend::cls( $kind . '__close' ) ) . '" aria-label="' . esc_attr__( 'Close', 'page-builder-sandwich' ) . '">'
			. '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M18 6 6 18M6 6l12 12"/></svg>'
			. '</button></form></div>'
			. '<div class="' . esc_attr( Frontend::cls( $kind . '__body' ) ) . '">' . $body . '</div>'
			. '</div></dialog></div>';
	}
}
