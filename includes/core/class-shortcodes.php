<?php
/**
 * The legacy shortcodes, so unconverted content keeps rendering.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Core;

use ZinnDigital\PBS\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * `[pbs_widget]`, `[pbs_sidebar]` and `[pbs_button]`, rendering exactly what the matching block
 * renders.
 *
 * Attribute shapes are the legacy 5.1.0 ones:
 * - `[pbs_widget widget="WP_Widget_Text" title="…" text="…"]` — every attribute except `widget`
 *   is the widget instance (class-shortcodes.php `widget()`);
 * - `[pbs_sidebar id="sidebar-1"]`;
 * - `[pbs_button url="…" label="…" align="center" target="true"]` (pre-4.0 content, converted by
 *   class-migration.php `convert_pbs_button_to_link()`).
 *
 * ⛔ The legacy button conversion printed `label` raw into post content — a stored-XSS path
 * (docs/plugins-overhaul/10-audit-pbs.md §3, "sitewide bulk content rewrite"). Here the label
 * is escaped as text and the URL is scheme-checked and escaped.
 */
final class Shortcodes {

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'init', array( self::class, 'add' ) );
		// Before wptexturize (10) and do_shortcode (11), so the tag is a registered one by then.
		foreach ( array( 'the_content', 'widget_text', 'widget_text_content', 'widget_block_content' ) as $filter ) {
			add_filter( $filter, array( self::class, 'translate_saved_tags' ), 7 );
		}
	}

	/**
	 * Register the shortcodes.
	 *
	 * @return void
	 */
	public static function add(): void {
		add_shortcode( 'pbsw_widget', array( self::class, 'widget' ) );
		add_shortcode( 'pbsw_sidebar', array( self::class, 'sidebar' ) );
		add_shortcode( 'pbsw_button', array( self::class, 'button' ) );
	}

	/**
	 * Read tags saved by earlier versions under their current names. Only the output changes: the
	 * stored post is left exactly as it was, so a rollback still renders it.
	 *
	 * An escaped `[[pbs_button]]` is documentation of the tag: it stays text, with one bracket
	 * pair removed, which is what WordPress did while the old tag was registered.
	 *
	 * @param mixed $content Content being rendered.
	 * @return mixed
	 */
	public static function translate_saved_tags( $content ) {
		if ( ! is_string( $content ) || ! str_contains( $content, 'pbs_' ) ) {
			return $content;
		}
		$out = preg_replace_callback(
			'/\[\[(\/?pbs_(?:widget|sidebar|button)(?![\w-])[^\[\]]*)\]\]|\[(\/?)pbs_(widget|sidebar|button)(?![\w-])(?=[^\[\]]*\])/',
			static function ( array $m ): string {
				return '' !== $m[1] ? '[' . $m[1] . ']' : '[' . $m[2] . 'pbsw_' . $m[3];
			},
			$content
		);
		return null === $out ? $content : $out;
	}

	/**
	 * Whether content uses the button shortcode, under its current or its earlier name.
	 *
	 * @param string $content Stored content.
	 * @return bool
	 */
	public static function uses_button( string $content ): bool {
		return 1 === preg_match( '/(?<!\[)\[pbsw?_button(?![\w-])/', $content );
	}

	/**
	 * `[pbs_widget]`.
	 *
	 * @param array<string, mixed>|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function widget( $atts ): string {
		$atts  = is_array( $atts ) ? $atts : array();
		$class = (string) ( $atts['widget'] ?? 'WP_Widget_Text' );
		unset( $atts['widget'] );

		return Widgets::widget( $class, $atts ); // One wrapper, carrying the block class (minimal DOM).
	}

	/**
	 * `[pbs_sidebar]`.
	 *
	 * @param array<string, mixed>|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function sidebar( $atts ): string {
		$atts = shortcode_atts( array( 'id' => '' ), is_array( $atts ) ? $atts : array(), 'pbsw_sidebar' );

		return Render::container( 'sidebar', Widgets::sidebar( (string) $atts['id'] ), Settings::prefix() );
	}

	/**
	 * `[pbs_button]`.
	 *
	 * @param array<string, mixed>|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function button( $atts ): string {
		return Render::button( self::button_attrs( is_array( $atts ) ? $atts : array() ), Settings::prefix() );
	}

	/**
	 * Map legacy `[pbs_button]` attributes onto pbsw/button attributes. The label is plain text.
	 *
	 * @param array<string, mixed> $atts Legacy attributes.
	 * @return array<string, string>
	 */
	public static function button_attrs( array $atts ): array {
		return array(
			'url'    => (string) ( $atts['url'] ?? '' ),
			'text'   => esc_html( (string) ( $atts['label'] ?? '' ) ),
			'align'  => (string) ( $atts['align'] ?? '' ),
			'target' => 'true' === (string) ( $atts['target'] ?? '' ) ? '_blank' : '',
		);
	}
}
