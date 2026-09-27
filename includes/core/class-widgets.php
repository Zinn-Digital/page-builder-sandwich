<?php
/**
 * Widget and sidebar output for pbs/widget, pbs/sidebar and their legacy shortcodes.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders a registered widget or a registered sidebar.
 *
 * ⛔ Only a class registered with the widget factory can be rendered: the legacy shortcode
 * accepted any `class_exists()` name, so any autoloadable class could be instantiated from post
 * content. And the instance is author input, so it goes through the widget's OWN update() —
 * the same sanitising WordPress applies when the widget is saved in the Customizer — with
 * `unfiltered_html` refused for the duration, because the user viewing the page (perhaps an
 * administrator) is not the user who wrote it.
 */
final class Widgets {

	/**
	 * Counter for unique widget ids on a page.
	 *
	 * @var int
	 */
	private static int $seq = 0;

	/**
	 * The registered widget object for a class name, or null.
	 *
	 * @param string $class_name Widget class.
	 * @return \WP_Widget|null
	 */
	public static function registered( string $class_name ): ?\WP_Widget {
		global $wp_widget_factory;
		if ( '' === $class_name || ! is_object( $wp_widget_factory ) || ! isset( $wp_widget_factory->widgets[ $class_name ] ) ) {
			return null;
		}
		$widget = $wp_widget_factory->widgets[ $class_name ];

		return $widget instanceof \WP_Widget ? $widget : null;
	}

	/**
	 * A widget's HTML (not wrapped).
	 *
	 * @param string               $class_name Widget class.
	 * @param array<string, mixed> $instance   Untrusted instance values.
	 * @return string
	 */
	public static function widget( string $class_name, array $instance ): string {
		$widget = self::registered( $class_name );
		if ( null === $widget ) {
			return '';
		}

		$deny = static function ( $caps, $cap ) {
			return 'unfiltered_html' === $cap ? array( 'do_not_allow' ) : $caps;
		};
		add_filter( 'map_meta_cap', $deny, 10, 2 );
		try {
			$clean = $widget->update( self::scalars( $instance ), array() );
		} finally {
			remove_filter( 'map_meta_cap', $deny, 10 );
		}
		if ( ! is_array( $clean ) ) {
			return '';
		}

		++self::$seq;
		$classname = (string) ( $widget->widget_options['classname'] ?? '' );
		ob_start();
		the_widget(
			$class_name,
			$clean,
			array(
				'widget_id'     => \ZinnDigital\PBS\Settings::prefix() . '-w-' . self::$seq,
				// The block's own class sits on the widget's wrapper: one element, no extra <div> (pbs-p9).
				'before_widget' => '<div class="' . esc_attr( trim( 'widget ' . $classname . ' ' . \ZinnDigital\PBS\Frontend::cls( 'widget' ) ) ) . '">',
				'after_widget'  => '</div>',
				'before_title'  => '<h2 class="widget-title">',
				'after_title'   => '</h2>',
			)
		);

		return (string) ob_get_clean();
	}

	/**
	 * A registered, active sidebar's HTML (not wrapped).
	 *
	 * @param string $id Sidebar id.
	 * @return string
	 */
	public static function sidebar( string $id ): string {
		$id = sanitize_key( $id );
		if ( '' === $id || ! is_registered_sidebar( $id ) || ! is_active_sidebar( $id ) ) {
			return '';
		}
		ob_start();
		dynamic_sidebar( $id );

		return (string) ob_get_clean();
	}

	/**
	 * Keep scalar (and one level of scalar-list) values only: a widget instance is a flat form.
	 *
	 * @param array<string, mixed> $instance Instance.
	 * @return array<string, mixed>
	 */
	private static function scalars( array $instance ): array {
		$out = array();
		foreach ( $instance as $key => $value ) {
			if ( ! is_string( $key ) ) {
				continue;
			}
			if ( is_scalar( $value ) ) {
				$out[ $key ] = $value;
			} elseif ( is_array( $value ) ) {
				$out[ $key ] = array_filter( $value, 'is_scalar' );
			}
		}

		return $out;
	}
}
