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
	}

	/**
	 * Register the shortcodes.
	 *
	 * @return void
	 */
	public static function add(): void {
		add_shortcode( 'pbs_widget', array( self::class, 'widget' ) );
		add_shortcode( 'pbs_sidebar', array( self::class, 'sidebar' ) );
		add_shortcode( 'pbs_button', array( self::class, 'button' ) );
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
		$atts = shortcode_atts( array( 'id' => '' ), is_array( $atts ) ? $atts : array(), 'pbs_sidebar' );

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
	 * Map legacy `[pbs_button]` attributes onto pbs/button attributes. The label is plain text.
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
