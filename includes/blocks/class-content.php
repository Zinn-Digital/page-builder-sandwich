<?php
/**
 * Render callbacks of the "Content" group of design-system blocks (lane L09).
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Blocks;

use ZinnDigital\PBS\Assets\Perf\Modules;
use ZinnDigital\PBS\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One public static `render_<slug>()` per block of the group (Registry::callback()).
 *
 * Every callback REBUILDS the live HTML from the attributes (the saved markup is only the
 * footprint-free fallback that survives deactivation), prints classes only through
 * Frontend::cls(), escapes every value it prints, and translates every string it adds.
 */
final class Content {

	/** Alert variants → their ARIA role. Errors and warnings interrupt; the rest wait. */
	private const ALERT_ROLES = array(
		'info'    => 'status',
		'success' => 'status',
		'warning' => 'alert',
		'error'   => 'alert',
	);

	/** Icon per variant: 24×24, stroked in the text colour, decorative. */
	private const ALERT_ICONS = array(
		'info'    => '<circle cx="12" cy="12" r="10"/><path d="M12 16v-5M12 8h.01"/>',
		'success' => '<circle cx="12" cy="12" r="10"/><path d="m8 12.5 2.5 2.5L16 9.5"/>',
		'warning' => '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4M12 17h.01"/>',
		'error'   => '<circle cx="12" cy="12" r="10"/><path d="m15 9-6 6M9 9l6 6"/>',
	);

	/**
	 * `pbs/alert`.
	 *
	 * @param array<string, mixed> $attributes Attributes: variant, title, content, showIcon, dismissible.
	 * @return string
	 */
	public static function render_alert( array $attributes ): string {
		$title   = trim( wp_kses_post( (string) ( $attributes['title'] ?? '' ) ) );
		$content = trim( wp_kses_post( (string) ( $attributes['content'] ?? '' ) ) );
		if ( '' === $title && '' === $content ) {
			return '';
		}
		$variant = (string) ( $attributes['variant'] ?? 'info' );
		if ( ! isset( self::ALERT_ROLES[ $variant ] ) ) {
			$variant = 'info';
		}
		$dismissible = ! empty( $attributes['dismissible'] );

		$root = '<div class="' . esc_attr( Frontend::cls( 'alert', 'alert--' . $variant ) ) . '" role="' . self::ALERT_ROLES[ $variant ] . '"';
		if ( $dismissible ) {
			$root .= self::alert_interactive();
		}
		$html = $root . '>';

		if ( ! isset( $attributes['showIcon'] ) || ! empty( $attributes['showIcon'] ) ) {
			$html .= '<span class="' . esc_attr( Frontend::cls( 'alert__icon' ) ) . '" aria-hidden="true">'
				. '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false">'
				. self::ALERT_ICONS[ $variant ] . '</svg></span>';
		}

		$html .= '<div class="' . esc_attr( Frontend::cls( 'alert__body' ) ) . '">';
		if ( '' !== $title ) {
			$html .= '<p class="' . esc_attr( Frontend::cls( 'alert__title' ) ) . '">' . $title . '</p>';
		}
		if ( '' !== $content ) {
			$html .= '<p class="' . esc_attr( Frontend::cls( 'alert__text' ) ) . '">' . $content . '</p>';
		}
		$html .= '</div>';

		if ( $dismissible ) {
			// Hidden until the view module has run: a close button that cannot close is worse than none.
			$html .= '<button type="button" class="' . esc_attr( Frontend::cls( 'alert__close' ) ) . '" hidden'
				. ' data-wp-bind--hidden="!context.ready" data-wp-on--click="actions.alertDismiss"'
				. ' aria-label="' . esc_attr__( 'Dismiss this notice', 'page-builder-sandwich' ) . '">'
				. '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M18 6 6 18M6 6l12 12"/></svg>'
				. '</button>';
		}

		return $html . '</div>';
	}

	/**
	 * The Interactivity API attributes of a dismissible alert (and its view module, once).
	 *
	 * @return string Attributes, each with its leading space.
	 */
	private static function alert_interactive(): string {
		Modules::enqueue( 'alert' );

		return ' data-wp-interactive="' . esc_attr( Modules::store() ) . '"'
			. " data-wp-context='" . esc_attr(
				(string) wp_json_encode(
					array(
						'open'  => true,
						'ready' => false,
					)
				)
			) . "'"
			. ' data-wp-bind--hidden="!context.open" data-wp-init="callbacks.alertReady"';
	}
}
