<?php
/**
 * The static (saved) HTML of the core blocks, in PHP.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Byte-for-byte the output of each block's JavaScript save() (src/core/blocks/*), so the
 * converter (Migrate\Converter) can write block markup the editor will validate.
 *
 * ⭐ This is the footprint-free FALLBACK: what a visitor reads if the plugin is ever turned off.
 * No class attributes, inline styles only, nothing that names the plugin.
 *
 * ⛔ The two sides are held together by fixtures, not by care: the vitest suite asserts the JS
 * save() output equals wp/tests/footprint/page-builder-sandwich/blocks/*.html, and the PHPUnit
 * suite asserts this class produces the same bytes from the same attributes. Change one side and
 * a test goes red.
 *
 * Escaping mirrors @wordpress/escape-html's escapeAttribute(): `&` that is not an entity,
 * `"`, `<` and `>`. Inner HTML (button text, the SVG) is written as given, as RichText.Content and
 * RawHTML do.
 */
final class Fallback {

	/** Row base style. */
	public const ROW_STYLE = 'display:flex;flex-wrap:wrap';

	/** Column base style. */
	public const COLUMN_STYLE = 'flex:1 1 0;min-width:0';

	/**
	 * `pbs/row` saved HTML.
	 *
	 * @param array<string, mixed> $attrs Attributes.
	 * @param string               $inner Saved inner blocks (serialized, with their delimiters).
	 * @return string
	 */
	public static function row( array $attrs, string $inner ): string {
		return '<div style="' . self::attr( self::join( self::ROW_STYLE, (string) ( $attrs['style'] ?? '' ) ) ) . '">' . $inner . '</div>';
	}

	/**
	 * `pbs/column` saved HTML.
	 *
	 * @param array<string, mixed> $attrs Attributes.
	 * @param string               $inner Saved inner blocks.
	 * @return string
	 */
	public static function column( array $attrs, string $inner ): string {
		return '<div style="' . self::attr( self::join( self::COLUMN_STYLE, (string) ( $attrs['style'] ?? '' ) ) ) . '">' . $inner . '</div>';
	}

	/**
	 * `pbs/button` saved HTML.
	 *
	 * @param array<string, mixed> $attrs Attributes.
	 * @return string
	 */
	public static function button( array $attrs ): string {
		$a     = '<a';
		$order = array(
			'href'   => 'url',
			'style'  => 'style',
			'target' => 'target',
			'rel'    => 'rel',
		);
		foreach ( $order as $html => $key ) {
			$value = (string) ( $attrs[ $key ] ?? '' );
			if ( '' !== $value ) {
				$a .= ' ' . $html . '="' . self::attr( $value ) . '"';
			}
		}
		$a    .= '>' . (string) ( $attrs['text'] ?? '' ) . '</a>';
		$align = (string) ( $attrs['align'] ?? '' );
		$wrap  = implode( ';', array_filter( array( '' === $align ? '' : 'text-align:' . $align, (string) ( $attrs['wrapStyle'] ?? '' ) ) ) );

		if ( '' === $wrap ) {
			return empty( $attrs['wrapped'] ) ? $a : '<p>' . $a . '</p>';
		}

		return '<p style="' . self::attr( $wrap ) . '">' . $a . '</p>';
	}

	/**
	 * `pbs/icon` saved HTML.
	 *
	 * @param array<string, mixed> $attrs Attributes.
	 * @return string
	 */
	public static function icon( array $attrs ): string {
		$out   = '<span';
		$style = (string) ( $attrs['style'] ?? '' );
		if ( '' !== $style ) {
			$out .= ' style="' . self::attr( $style ) . '"';
		}
		$label = (string) ( $attrs['label'] ?? '' );
		$out  .= '' !== $label ? ' role="img" aria-label="' . self::attr( $label ) . '"' : ' aria-hidden="true"';

		return $out . '>' . (string) ( $attrs['svg'] ?? '' ) . '</span>';
	}

	/**
	 * Base style plus the author's declarations.
	 *
	 * @param string $base  Base.
	 * @param string $extra Author style.
	 * @return string
	 */
	private static function join( string $base, string $extra ): string {
		$extra = trim( $extra );

		return '' === $extra ? $base : $base . ';' . $extra;
	}

	/**
	 * Escape an attribute value exactly as @wordpress/escape-html escapeAttribute() does.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	public static function attr( string $value ): string {
		$value = (string) preg_replace( '/&(?!([a-z0-9]+|#[0-9]+|#x[a-f0-9]+);)/i', '&amp;', $value );

		return str_replace( array( '"', '<', '>' ), array( '&quot;', '&lt;', '&gt;' ), $value );
	}

	/**
	 * `pbs/widget` / `pbs/sidebar` saved HTML: the `fallbackHtml` snapshot, as RawHTML writes it.
	 *
	 * @param array<string, mixed> $attrs Attributes.
	 * @return string
	 */
	public static function widget( array $attrs ): string {
		return (string) ( $attrs['fallbackHtml'] ?? '' );
	}

	/**
	 * A widget's or sidebar's rendered output made into its deactivation fallback: what a visitor
	 * reads if the plugin is switched off (a widget block is otherwise dynamic and would print
	 * NOTHING — measured on the legacy fixture's shortcode page).
	 *
	 * Readable and inert: comments, scripts, styles and forms go; every class, id, `data-*` and
	 * `aria-*` attribute goes (they name the theme's or core's widget markup — `wp-block-*` —
	 * and would print a footprint); what is left passes wp_kses_post().
	 *
	 * @param string $html Rendered widget or sidebar HTML.
	 * @return string
	 */
	public static function widget_html( string $html ): string {
		$html = (string) preg_replace( '/<!--.*?-->/s', '', $html );
		$html = (string) preg_replace( '@<(script|style|noscript|template|form|svg)\b[^>]*>.*?</\1\s*>@si', '', $html );
		$html = (string) preg_replace( '/\s(?:class|id|data-[\w-]+|aria-[\w-]+|role|for|name|form|tabindex)\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html );
		if ( function_exists( 'wp_kses_post' ) ) {
			$html = wp_kses_post( $html );
		}

		return trim( (string) preg_replace( '/>\s+</', '><', $html ) );
	}


	/**
	 * Give every `pbs/widget` and `pbs/sidebar` block in serialized content its fallback, freshly
	 * rendered: self-closing ones are expanded, existing snapshots replaced. The attributes JSON
	 * is kept byte-for-byte (the snapshot lives in the block's inner HTML, read as the raw
	 * `fallbackHtml` attribute), so nothing else about the block changes.
	 *
	 * @param string   $content Serialized block content.
	 * @param callable $render  (string $name, array $attrs): string — the rendered block HTML.
	 * @return string
	 */
	public static function fill_widgets( string $content, callable $render ): string {
		if ( ! str_contains( $content, 'wp:pbs/widget' ) && ! str_contains( $content, 'wp:pbs/sidebar' ) ) {
			return $content;
		}

		return (string) preg_replace_callback(
			'#<!-- wp:(pbs/(?:widget|sidebar))(\s+(\{.*?\}))?\s+(?:/-->|-->(.*?)<!-- /wp:\1 -->)#s',
			static function ( array $m ) use ( $render ): string {
				$name  = $m[1];
				$json  = $m[3] ?? '';
				$attrs = '' === $json ? array() : json_decode( $json, true );
				if ( ! is_array( $attrs ) ) {
					return $m[0];
				}
				$html = self::widget_html( (string) $render( $name, $attrs ) );
				$head = '<!-- wp:' . $name . ( '' === $json ? '' : ' ' . $json );

				return '' === $html ? $head . ' /-->' : $head . " -->\n" . $html . "\n<!-- /wp:" . $name . ' -->';
			},
			$content
		);
	}

	/**
	 * The live output of a widget or sidebar block (the renderer fill_widgets() uses on a site).
	 *
	 * @param string               $name  `pbs/widget` or `pbs/sidebar`.
	 * @param array<string, mixed> $attrs Attributes.
	 * @return string
	 */
	public static function render_widget_block( string $name, array $attrs ): string {
		if ( 'pbs/sidebar' === $name ) {
			return Widgets::sidebar( (string) ( $attrs['sidebar'] ?? '' ) );
		}
		$instance = is_array( $attrs['instance'] ?? null ) ? $attrs['instance'] : array();

		return Widgets::widget( (string) ( $attrs['widget'] ?? '' ), $instance );
	}
}
