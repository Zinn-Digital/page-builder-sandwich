<?php
/**
 * Render callbacks of the "Data" group of design-system blocks (lane L09).
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Blocks;

use ZinnDigital\PBS\Assets;
use ZinnDigital\PBS\Assets\Perf\Modules;
use ZinnDigital\PBS\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One public static `render_<slug>()` per block of the group (Registry::callback()). Every
 * callback REBUILDS the live HTML from the attributes, prints classes only through
 * Frontend::cls(), escapes every value it prints, and translates every string it adds.
 */
final class Data {

	/** A five-pointed star, 24×24. */
	private const STAR = 'M12 2.5l2.9 6.1 6.6.8-4.9 4.6 1.3 6.6L12 17.3l-5.9 3.3 1.3-6.6-4.9-4.6 6.6-.8z';

	/**
	 * `pbs/star-rating`: a rating shown as stars, in half-star steps.
	 *
	 * One image to assistive technology (`role="img"` with "Rated 4.5 out of 5"); the stars are
	 * decoration. Each star is drawn full, half or empty — no inline style, and a half star is
	 * mirrored in right-to-left so it fills from the reading start. This is a display, not a
	 * review: it prints no review structured data (owner ruling: none for self-served reviews).
	 *
	 * @param array<string, mixed> $attributes Attributes: rating, max, showValue.
	 * @return string
	 */
	public static function render_star_rating( array $attributes ): string {
		$max    = max( 1, min( 10, (int) ( $attributes['max'] ?? 5 ) ) );
		$rating = round( max( 0.0, min( (float) $max, (float) ( $attributes['rating'] ?? 0 ) ) ) * 2 ) / 2;
		$shown  = number_format_i18n( $rating, fmod( $rating, 1.0 ) > 0 ? 1 : 0 );
		/* translators: 1: the rating, e.g. 4.5; 2: the highest possible rating, e.g. 5. */
		$label = sprintf( __( 'Rated %1$s out of %2$s', 'page-builder-sandwich' ), $shown, number_format_i18n( $max ) );

		$stars = '';
		for ( $i = 1; $i <= $max; $i++ ) {
			$state  = $rating >= $i ? 'full' : ( $rating >= $i - 0.5 ? 'half' : 'empty' );
			$stars .= '<svg class="' . esc_attr( Frontend::cls( 'star-rating__star', 'star-rating__star--' . $state ) ) . '" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" focusable="false">'
				. ( 'half' === $state
					? '<path class="' . esc_attr( Frontend::cls( 'star-rating__empty' ) ) . '" d="' . self::STAR . '"/><path class="' . esc_attr( Frontend::cls( 'star-rating__fill' ) ) . '" d="M12 2.5v14.8l-5.9 3.3 1.3-6.6-4.9-4.6 6.6-.8z"/>'
					: '<path class="' . esc_attr( Frontend::cls( 'full' === $state ? 'star-rating__fill' : 'star-rating__empty' ) ) . '" d="' . self::STAR . '"/>' )
				. '</svg>';
		}

		return '<div class="' . esc_attr( Frontend::cls( 'star-rating' ) ) . '" role="img" aria-label="' . esc_attr( $label ) . '">'
			. '<span class="' . esc_attr( Frontend::cls( 'star-rating__stars' ) ) . '">' . $stars . '</span>'
			. ( ! empty( $attributes['showValue'] ) ? '<span class="' . esc_attr( Frontend::cls( 'star-rating__value' ) ) . '">' . esc_html( $shown . ' / ' . number_format_i18n( $max ) ) . '</span>' : '' )
			. '</div>';
	}

	/**
	 * `pbs/progress-bar`: a labelled `<progress>` element — the progressbar role, value and range
	 * come from the element itself, so every assistive technology reads it; no inline style. The
	 * fill grows in when the page loads unless the visitor prefers reduced motion (CSS only).
	 *
	 * @param array<string, mixed> $attributes Attributes: label, value, max, showValue.
	 * @return string
	 */
	public static function render_progress_bar( array $attributes ): string {
		$label = trim( wp_kses( (string) ( $attributes['label'] ?? '' ), array() ) );
		$max   = max( 1, (int) ( $attributes['max'] ?? 100 ) );
		$value = max( 0, min( $max, (int) ( $attributes['value'] ?? 0 ) ) );
		if ( '' === $label ) {
			$label = __( 'Progress', 'page-builder-sandwich' );
		}
		$id      = Markup::id( 'progress' );
		$percent = (int) round( $value / $max * 100 );
		/* translators: %s: a percentage number, e.g. 70. */
		$text = sprintf( __( '%s%%', 'page-builder-sandwich' ), number_format_i18n( $percent ) );

		return '<div class="' . esc_attr( Frontend::cls( 'progress-bar' ) ) . '">'
			. '<div class="' . esc_attr( Frontend::cls( 'progress-bar__head' ) ) . '">'
			. '<label class="' . esc_attr( Frontend::cls( 'progress-bar__label' ) ) . '" for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>'
			. ( ! isset( $attributes['showValue'] ) || ! empty( $attributes['showValue'] ) ? '<span class="' . esc_attr( Frontend::cls( 'progress-bar__value' ) ) . '" aria-hidden="true">' . esc_html( $text ) . '</span>' : '' )
			. '</div>'
			. '<progress class="' . esc_attr( Frontend::cls( 'progress-bar__bar' ) ) . '" id="' . esc_attr( $id ) . '" max="' . esc_attr( (string) $max ) . '" value="' . esc_attr( (string) $value ) . '">' . esc_html( $text ) . '</progress>'
			. '</div>';
	}

	/** Languages the bundled highlight.js knows (its "common" set), id → display name. */
	public const LANGUAGES = array(
		'bash'       => 'Bash',
		'c'          => 'C',
		'cpp'        => 'C++',
		'csharp'     => 'C#',
		'css'        => 'CSS',
		'diff'       => 'Diff',
		'go'         => 'Go',
		'graphql'    => 'GraphQL',
		'ini'        => 'INI / TOML',
		'java'       => 'Java',
		'javascript' => 'JavaScript',
		'json'       => 'JSON',
		'kotlin'     => 'Kotlin',
		'less'       => 'Less',
		'lua'        => 'Lua',
		'makefile'   => 'Makefile',
		'markdown'   => 'Markdown',
		'objectivec' => 'Objective-C',
		'perl'       => 'Perl',
		'php'        => 'PHP',
		'python'     => 'Python',
		'r'          => 'R',
		'ruby'       => 'Ruby',
		'rust'       => 'Rust',
		'scss'       => 'SCSS',
		'shell'      => 'Shell session',
		'sql'        => 'SQL',
		'swift'      => 'Swift',
		'typescript' => 'TypeScript',
		'vbnet'      => 'VB.NET',
		'xml'        => 'HTML / XML',
		'yaml'       => 'YAML',
	);

	/**
	 * `pbs/code`: a code listing. The code is printed escaped by the server (readable with no
	 * script at all); highlight.js — bundled, published through the neutral path, loaded only on
	 * pages with a code block — colours it in the browser with neutral class names.
	 *
	 * @param array<string, mixed> $attributes Attributes: code, language, filename, lineNumbers, copy, wrap.
	 * @return string
	 */
	public static function render_code( array $attributes ): string {
		$code = (string) ( $attributes['code'] ?? '' );
		if ( '' === trim( $code ) ) {
			return '';
		}
		$code     = str_replace( array( "\r\n", "\r" ), "\n", rtrim( $code, "\n" ) );
		$language = (string) ( $attributes['language'] ?? 'auto' );
		// "Plain text" by any of its usual names means NO colouring. It used to fall through to
		// detection, which compiles every bundled grammar on first use: one 38-character URL on
		// docs.pagebuildersandwich.com/mcp/ cost a 400 ms task and a mobile score of 83.
		if ( in_array( strtolower( $language ), array( 'plaintext', 'plain', 'text', 'txt' ), true ) ) {
			$language = 'none';
		}
		if ( 'auto' !== $language && 'none' !== $language && ! isset( self::LANGUAGES[ $language ] ) ) {
			$language = 'auto';
		}
		$filename = trim( sanitize_text_field( (string) ( $attributes['filename'] ?? '' ) ) );
		$numbers  = ! empty( $attributes['lineNumbers'] );
		$copy     = ! isset( $attributes['copy'] ) || ! empty( $attributes['copy'] );
		$c        = static fn( string ...$n ): string => esc_attr( Frontend::cls( ...$n ) );

		$interactive = 'none' !== $language || $copy;
		$root        = '<figure class="' . $c( 'code', ! empty( $attributes['wrap'] ) ? 'code--wrap' : '' ) . '"';
		if ( $interactive ) {
			Modules::enqueue( 'code' );
			if ( 'none' !== $language ) {
				Assets::enqueue_script( 'vendor/highlight.js' );
			}
			$root .= ' data-wp-interactive="' . esc_attr( Modules::store() ) . '"'
				. " data-wp-context='" . esc_attr(
					(string) wp_json_encode(
						array(
							'ready'  => false,
							'copied' => false,
						)
					)
				) . "' data-wp-init=\"callbacks.codeReady\"";
		}
		$html = $root . '>';

		$label = '' !== $filename ? $filename : ( self::LANGUAGES[ $language ] ?? '' );
		if ( '' !== $label || $copy ) {
			$html .= '<figcaption class="' . $c( 'code__bar' ) . '">'
				. ( '' !== $label ? '<span class="' . $c( 'code__file' ) . '">' . esc_html( $label ) . '</span>' : '' );
			if ( $copy ) {
				$html .= '<button type="button" class="' . $c( 'code__copy' ) . '" hidden data-wp-bind--hidden="!context.ready" data-wp-on--click="actions.codeCopy">'
					. '<span data-wp-bind--hidden="context.copied">' . esc_html__( 'Copy code', 'page-builder-sandwich' ) . '</span>'
					. '<span hidden data-wp-bind--hidden="!context.copied">' . esc_html__( 'Copied', 'page-builder-sandwich' ) . '</span>'
					. '</button>';
			}
			$html .= '</figcaption>';
		}

		$html .= '<div class="' . $c( 'code__body' ) . '">';
		if ( $numbers ) {
			$count = substr_count( $code, "\n" ) + 1;
			$html .= '<span class="' . $c( 'code__lines' ) . '" aria-hidden="true">' . implode( "\n", range( 1, $count ) ) . '</span>';
		}
		// A scrollable region must be reachable by keyboard (tabindex) and named.
		$html .= '<pre class="' . $c( 'code__pre' ) . '" tabindex="0"'
			. ( '' !== $label ? ' aria-label="' . esc_attr( $label ) . '"' : '' ) . '>'
			. '<code class="' . $c( 'code__code' ) . '"' . ( 'none' !== $language ? ' data-lang="' . esc_attr( $language ) . '"' : '' ) . '>'
			. esc_html( $code ) . '</code></pre></div>';

		return $html . '</figure>';
	}
}
