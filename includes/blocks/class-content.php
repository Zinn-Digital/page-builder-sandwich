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

require_once __DIR__ . '/class-kit.php';
require_once __DIR__ . '/class-glossary.php';

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

	/**
	 * `pbs/tabs`.
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 * @param string               $content    Rendered inner content.
	 * @param mixed                $block      WP_Block.
	 * @return string
	 */
	public static function render_tabs( array $attributes, string $content = '', $block = null ): string {
		return Interactive\Tabs::render( $attributes, $content, $block );
	}

	/**
	 * `pbs/tab`.
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 * @param string               $content    Rendered inner content.
	 * @param mixed                $block      WP_Block.
	 * @return string
	 */
	public static function render_tab( array $attributes, string $content = '', $block = null ): string {
		return Interactive\Tabs::render_tab( $attributes, $content, $block );
	}

	/**
	 * `pbs/accordion`.
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 * @param string               $content    Rendered inner content.
	 * @param mixed                $block      WP_Block.
	 * @return string
	 */
	public static function render_accordion( array $attributes, string $content = '', $block = null ): string {
		return Interactive\Accordion::render( $attributes, $content, $block );
	}

	/**
	 * `pbs/accordion-item`.
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 * @param string               $content    Rendered inner content.
	 * @param mixed                $block      WP_Block.
	 * @return string
	 */
	public static function render_accordion_item( array $attributes, string $content = '', $block = null ): string {
		return Interactive\Accordion::render_item( $attributes, $content, $block );
	}

	/**
	 * `pbs/modal`.
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 * @param string               $content    Rendered inner content.
	 * @param mixed                $block      WP_Block.
	 * @return string
	 */
	public static function render_modal( array $attributes, string $content = '', $block = null ): string {
		return Interactive\Dialog::render( 'modal', $attributes, $content, $block );
	}

	/**
	 * `pbs/off-canvas`.
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 * @param string               $content    Rendered inner content.
	 * @param mixed                $block      WP_Block.
	 * @return string
	 */
	public static function render_off_canvas( array $attributes, string $content = '', $block = null ): string {
		return Interactive\Dialog::render( 'off-canvas', $attributes, $content, $block );
	}

	/**
	 * `pbs/tooltip`.
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 * @return string
	 */
	public static function render_tooltip( array $attributes ): string {
		return Interactive\Tooltip::render( $attributes );
	}

	// ── Group G-B (lane L09 P5): lists, boxes and headings ──────────────────────────────────

	/**
	 * `pbs/icon-list`: a list whose every line starts with an icon.
	 *
	 * @param array<string, mixed> $attributes Attributes: items [{text, icon, iconId}], icon, layout, accent.
	 * @return string
	 */
	public static function render_icon_list( array $attributes ): string {
		$icon   = Kit::choice( $attributes['icon'] ?? '', array( 'check', 'dot', 'arrow', 'star', 'circle' ) );
		$layout = Kit::choice( $attributes['layout'] ?? '', array( 'stacked', 'inline' ) );
		$accent = Kit::choice( $attributes['accent'] ?? '', array( 'primary', 'text', 'secondary', 'contrast' ) );
		$lines  = '';
		foreach ( Kit::items( $attributes['items'] ?? array() ) as $item ) {
			$text = Kit::rich( $item['text'] ?? '' );
			if ( '' === $text ) {
				continue;
			}
			$lines .= '<li class="' . esc_attr( Frontend::cls( 'icon-list__item' ) ) . '">'
				. '<span class="' . esc_attr( Frontend::cls( 'icon-list__icon' ) ) . '">' . Kit::icon( (string) ( $item['icon'] ?? '' ), $icon ) . '</span>'
				. '<span class="' . esc_attr( Frontend::cls( 'icon-list__text' ) ) . '">' . $text . '</span></li>';
		}
		if ( '' === $lines ) {
			return '';
		}

		return '<ul class="' . esc_attr( Frontend::cls( 'icon-list', 'icon-list--' . $layout, 'accent--' . $accent ) ) . '">' . $lines . '</ul>';
	}

	/**
	 * `pbs/info-box`: an icon, a heading, a short text and an optional link.
	 *
	 * @param array<string, mixed> $attributes Attributes: svg, icon, title, content, linkUrl, linkText, newTab, align, level, accent.
	 * @return string
	 */
	public static function render_info_box( array $attributes ): string {
		$title   = Kit::rich( $attributes['title'] ?? '' );
		$content = Kit::rich( $attributes['content'] ?? '' );
		if ( '' === $title && '' === $content ) {
			return '';
		}
		$icon   = Kit::choice( $attributes['icon'] ?? '', array( 'info', 'star', 'check', 'arrow', 'dot', 'circle' ) );
		$align  = Kit::choice( $attributes['align'] ?? '', array( 'start', 'center' ) );
		$accent = Kit::choice( $attributes['accent'] ?? '', array( 'primary', 'text', 'secondary', 'contrast' ) );
		$level  = Kit::level( $attributes['level'] ?? 2, 2 );

		$html = '<div class="' . esc_attr( Frontend::cls( 'info-box', 'info-box--' . $align, 'accent--' . $accent ) ) . '">';
		if ( ! isset( $attributes['showIcon'] ) || ! empty( $attributes['showIcon'] ) ) {
			$html .= '<span class="' . esc_attr( Frontend::cls( 'info-box__icon' ) ) . '">' . Kit::icon( (string) ( $attributes['svg'] ?? '' ), $icon ) . '</span>';
		}
		if ( '' !== $title ) {
			$html .= '<h' . $level . ' class="' . esc_attr( Frontend::cls( 'info-box__title' ) ) . '">' . $title . '</h' . $level . '>';
		}
		if ( '' !== $content ) {
			$html .= '<p class="' . esc_attr( Frontend::cls( 'info-box__text' ) ) . '">' . $content . '</p>';
		}
		$url  = Kit::url( $attributes['linkUrl'] ?? '' );
		$text = Kit::text( $attributes['linkText'] ?? '' );
		if ( '' !== $url && '' !== $text ) {
			$new   = ! empty( $attributes['newTab'] );
			$html .= '<p class="' . esc_attr( Frontend::cls( 'info-box__more' ) ) . '"><a class="' . esc_attr( Frontend::cls( 'info-box__link' ) ) . '" href="' . $url . '"' . Kit::target( $new ) . '>' . $text
				. ( $new ? Kit::sr( esc_html__( '(opens in a new tab)', 'page-builder-sandwich' ) ) : '' ) . '</a></p>';
		}

		return $html . '</div>';
	}

	/**
	 * `pbs/feature-list`: a grid of features, each an icon, a heading and a text.
	 *
	 * @param array<string, mixed> $attributes Attributes: items [{title, text, icon, iconId}], icon, columns, level, accent.
	 * @return string
	 */
	public static function render_feature_list( array $attributes ): string {
		$icon    = Kit::choice( $attributes['icon'] ?? '', array( 'check', 'star', 'info', 'arrow', 'dot', 'circle' ) );
		$accent  = Kit::choice( $attributes['accent'] ?? '', array( 'primary', 'text', 'secondary', 'contrast' ) );
		$columns = max( 1, min( 4, (int) ( is_numeric( $attributes['columns'] ?? null ) ? $attributes['columns'] : 3 ) ) );
		$level   = Kit::level( $attributes['level'] ?? 2, 2 );
		$cards   = '';
		foreach ( Kit::items( $attributes['items'] ?? array() ) as $item ) {
			$title = Kit::rich( $item['title'] ?? '' );
			$text  = Kit::rich( $item['text'] ?? '' );
			if ( '' === $title && '' === $text ) {
				continue;
			}
			$cards .= '<li class="' . esc_attr( Frontend::cls( 'feature-list__item' ) ) . '">'
				. '<span class="' . esc_attr( Frontend::cls( 'feature-list__icon' ) ) . '">' . Kit::icon( (string) ( $item['icon'] ?? '' ), $icon ) . '</span>'
				. '<div class="' . esc_attr( Frontend::cls( 'feature-list__body' ) ) . '">'
				. ( '' !== $title ? '<h' . $level . ' class="' . esc_attr( Frontend::cls( 'feature-list__title' ) ) . '">' . $title . '</h' . $level . '>' : '' )
				. ( '' !== $text ? '<p class="' . esc_attr( Frontend::cls( 'feature-list__text' ) ) . '">' . $text . '</p>' : '' )
				. '</div></li>';
		}
		if ( '' === $cards ) {
			return '';
		}

		return '<ul class="' . esc_attr( Frontend::cls( 'feature-list', 'feature-list--cols-' . $columns, 'accent--' . $accent ) ) . '">' . $cards . '</ul>';
	}

	/**
	 * `pbs/steps`: numbered steps of a process.
	 *
	 * @param array<string, mixed> $attributes Attributes: items [{title, text}], layout, level, accent.
	 * @return string
	 */
	public static function render_steps( array $attributes ): string {
		$layout = Kit::choice( $attributes['layout'] ?? '', array( 'vertical', 'horizontal' ) );
		$accent = Kit::choice( $attributes['accent'] ?? '', array( 'primary', 'text', 'secondary', 'contrast' ) );
		$level  = Kit::level( $attributes['level'] ?? 2, 2 );
		$steps  = '';
		$n      = 0;
		foreach ( Kit::items( $attributes['items'] ?? array() ) as $item ) {
			$title = Kit::rich( $item['title'] ?? '' );
			$text  = Kit::rich( $item['text'] ?? '' );
			if ( '' === $title && '' === $text ) {
				continue;
			}
			++$n;
			$steps .= '<li class="' . esc_attr( Frontend::cls( 'steps__item' ) ) . '">'
				. '<span class="' . esc_attr( Frontend::cls( 'steps__number' ) ) . '" aria-hidden="true">' . $n . '</span>'
				. '<div class="' . esc_attr( Frontend::cls( 'steps__body' ) ) . '">'
				. ( '' !== $title ? '<h' . $level . ' class="' . esc_attr( Frontend::cls( 'steps__title' ) ) . '">' . $title . '</h' . $level . '>' : '' )
				. ( '' !== $text ? '<p class="' . esc_attr( Frontend::cls( 'steps__text' ) ) . '">' . $text . '</p>' : '' )
				. '</div></li>';
		}
		if ( '' === $steps ) {
			return '';
		}

		// An ordered list: screen readers announce "list, N items" and each position themselves.
		return '<ol class="' . esc_attr( Frontend::cls( 'steps', 'steps--' . $layout, 'accent--' . $accent ) ) . '">' . $steps . '</ol>';
	}

	/**
	 * `pbs/checklist`: items that are done or still to do, said in words as well as drawn.
	 *
	 * @param array<string, mixed> $attributes Attributes: items [{text, done}], accent.
	 * @return string
	 */
	public static function render_checklist( array $attributes ): string {
		$accent = Kit::choice( $attributes['accent'] ?? '', array( 'primary', 'text', 'secondary', 'contrast' ) );
		$lines  = '';
		foreach ( Kit::items( $attributes['items'] ?? array() ) as $item ) {
			$text = Kit::rich( $item['text'] ?? '' );
			if ( '' === $text ) {
				continue;
			}
			$done   = ! empty( $item['done'] );
			$lines .= '<li class="' . esc_attr( Frontend::cls( 'checklist__item', $done ? 'checklist__item--done' : 'checklist__item--todo' ) ) . '">'
				. '<span class="' . esc_attr( Frontend::cls( 'checklist__mark' ) ) . '">' . Kit::icon( '', $done ? 'check' : 'circle' ) . '</span>'
				. Kit::sr( $done ? esc_html__( 'Done:', 'page-builder-sandwich' ) : esc_html__( 'To do:', 'page-builder-sandwich' ) ) . ' '
				. '<span class="' . esc_attr( Frontend::cls( 'checklist__text' ) ) . '">' . $text . '</span></li>';
		}
		if ( '' === $lines ) {
			return '';
		}

		return '<ul class="' . esc_attr( Frontend::cls( 'checklist', 'accent--' . $accent ) ) . '">' . $lines . '</ul>';
	}

	/**
	 * `pbs/badge`: a short label ("New", "Best value").
	 *
	 * @param array<string, mixed> $attributes Attributes: content, variant, justify.
	 * @return string
	 */
	public static function render_badge( array $attributes ): string {
		$text = Kit::rich( $attributes['content'] ?? '' );
		if ( '' === $text ) {
			return '';
		}
		$variant = Kit::choice( $attributes['variant'] ?? '', array( 'neutral', 'info', 'success', 'warning', 'error', 'accent' ) );
		$justify = Kit::choice( $attributes['justify'] ?? '', array( 'start', 'center', 'end' ) );

		return '<p class="' . esc_attr( Frontend::cls( 'badge', 'badge--' . $justify ) ) . '"><span class="' . esc_attr( Frontend::cls( 'badge__label', 'badge__label--' . $variant ) ) . '">' . $text . '</span></p>';
	}

	/**
	 * `pbs/dual-heading`: one heading in two parts, the second one accented.
	 *
	 * @param array<string, mixed> $attributes Attributes: first, second, level, look, stacked, justify.
	 * @return string
	 */
	public static function render_dual_heading( array $attributes ): string {
		$first  = Kit::rich( $attributes['first'] ?? '' );
		$second = Kit::rich( $attributes['second'] ?? '' );
		if ( '' === $first && '' === $second ) {
			return '';
		}
		$level   = max( 1, min( 6, (int) ( is_numeric( $attributes['level'] ?? null ) ? $attributes['level'] : 2 ) ) );
		$look    = Kit::choice( $attributes['look'] ?? '', array( 'accent', 'gradient', 'outline', 'underline' ) );
		$justify = Kit::choice( $attributes['justify'] ?? '', array( 'start', 'center', 'end' ) );
		$names   = array( 'dual-heading', 'dual-heading--' . $look, 'dual-heading--' . $justify );
		if ( ! empty( $attributes['stacked'] ) ) {
			$names[] = 'dual-heading--stacked';
		}
		$parts = array();
		if ( '' !== $first ) {
			$parts[] = '<span class="' . esc_attr( Frontend::cls( 'dual-heading__first' ) ) . '">' . $first . '</span>';
		}
		if ( '' !== $second ) {
			$parts[] = '<span class="' . esc_attr( Frontend::cls( 'dual-heading__second' ) ) . '">' . $second . '</span>';
		}

		return '<h' . $level . ' class="' . esc_attr( Frontend::cls( ...$names ) ) . '">' . implode( ' ', $parts ) . '</h' . $level . '>';
	}

	/**
	 * `pbs/glossary`: terms and their definitions, optionally A–Z, each with its own anchor.
	 *
	 * @param array<string, mixed> $attributes Attributes: items [{term, definition}], sort, showIndex.
	 * @return string
	 */
	public static function render_glossary( array $attributes ): string {
		$entries = Glossary::entries( $attributes );
		if ( array() === $entries ) {
			return '';
		}
		$html = '';
		if ( ! empty( $attributes['showIndex'] ) ) {
			$letters = array();
			foreach ( $entries as $e ) {
				$letters[ $e['letter'] ] ??= $e['id'];
			}
			$nav = '';
			foreach ( $letters as $letter => $id ) {
				$nav .= '<li><a href="#' . esc_attr( $id ) . '">' . esc_html( $letter ) . '</a></li>';
			}
			$html .= '<nav class="' . esc_attr( Frontend::cls( 'glossary__index' ) ) . '" aria-label="' . esc_attr__( 'Glossary index', 'page-builder-sandwich' ) . '"><ul>' . $nav . '</ul></nav>';
		}
		$html .= '<dl class="' . esc_attr( Frontend::cls( 'glossary__list' ) ) . '">';
		foreach ( $entries as $e ) {
			$html .= '<div class="' . esc_attr( Frontend::cls( 'glossary__entry' ) ) . '" id="' . esc_attr( $e['id'] ) . '">'
				. '<dt class="' . esc_attr( Frontend::cls( 'glossary__term' ) ) . '">' . $e['term'] . '</dt>'
				. '<dd class="' . esc_attr( Frontend::cls( 'glossary__definition' ) ) . '">' . $e['definition'] . '</dd></div>';
		}

		return '<div class="' . esc_attr( Frontend::cls( 'glossary' ) ) . '">' . $html . '</dl></div>';
	}
}
