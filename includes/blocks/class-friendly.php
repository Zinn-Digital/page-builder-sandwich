<?php
/**
 * Friendly settings for the Widget and Shortcode blocks (pbs-b3).
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Blocks;

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- the DOM API's property names.

use ZinnDigital\PBS\Core\Widgets;
use ZinnDigital\PBS\Rest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * - `GET pbs/v1/widgets/fields?widget=<class>`: a widget's settings as real controls. Every
 *   classic widget already describes its settings in its own form() (the admin Widgets screen
 *   draws it); that HTML is read here into a field list — name, kind, label, choices, default —
 *   so ANY widget, core or a plugin's, gets proper inputs, checkboxes and dropdowns. A widget
 *   whose form cannot be read gets the generic key/value editor instead.
 * - `GET pbs/v1/shortcodes`: the shortcode tags registered on this site, for the Shortcode
 *   block's tag picker.
 */
final class Friendly {

	/** The number a widget's field names carry while its form is read. */
	private const NUMBER = 9999;

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
	}

	/**
	 * Routes (both need `edit_posts`: they describe, they change nothing).
	 *
	 * @return void
	 */
	public static function register_routes(): void {
		register_rest_route(
			Rest::NAMESPACE,
			'/widgets/fields',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'permission_callback' => static fn(): bool => current_user_can( 'edit_posts' ),
				'args'                => array(
					'widget' => array(
						'type'     => 'string',
						'required' => true,
						'pattern'  => '^[A-Za-z_][A-Za-z0-9_\\\\]{0,190}$',
					),
				),
				'callback'            => static function ( \WP_REST_Request $request ) {
					$widget = Widgets::registered( (string) $request['widget'] );
					if ( null === $widget ) {
						return new \WP_Error( 'pbsw_no_widget', __( 'That widget is not available on this site.', 'page-builder-sandwich' ), array( 'status' => 404 ) );
					}
					return new \WP_REST_Response( array( 'fields' => self::widget_fields( $widget ) ) );
				},
			)
		);
		register_rest_route(
			Rest::NAMESPACE,
			'/shortcodes',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'permission_callback' => static fn(): bool => current_user_can( 'edit_posts' ),
				'callback'            => static function () {
					global $shortcode_tags;
					$tags = array_keys( is_array( $shortcode_tags ) ? $shortcode_tags : array() );
					sort( $tags );
					return new \WP_REST_Response( array_values( array_filter( $tags, static fn( $t ): bool => 1 === preg_match( '/^[A-Za-z0-9_-]{1,64}$/', (string) $t ) ) ) );
				},
			)
		);
	}

	/**
	 * A widget's fields, read from its own form().
	 *
	 * @param \WP_Widget $widget Widget.
	 * @return array<int, array<string, mixed>>
	 */
	public static function widget_fields( \WP_Widget $widget ): array {
		$clone = clone $widget;
		$clone->_set( self::NUMBER );
		ob_start();
		$clone->form( array() );
		$html = (string) ob_get_clean();

		return self::parse_form( $html, $clone->id_base );
	}

	/**
	 * Parse a widget form into fields. Pure.
	 *
	 * @param string $html    The form's HTML.
	 * @param string $id_base The widget's id base.
	 * @return array<int, array{name: string, kind: string, label: string, default: string, choices?: array<int, array{value: string, label: string}>}>
	 */
	public static function parse_form( string $html, string $id_base ): array {
		if ( '' === trim( $html ) ) {
			return array();
		}
		$doc = new \DOMDocument();
		libxml_use_internal_errors( true );
		$doc->loadHTML( '<?xml encoding="UTF-8"><body>' . $html . '</body>', LIBXML_NOERROR | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();
		$xp     = new \DOMXPath( $doc );
		$prefix = 'widget-' . $id_base . '[' . self::NUMBER . '][';
		$labels = array();
		foreach ( $xp->query( '//label[@for]' ) as $label ) {
			$labels[ $label->getAttribute( 'for' ) ] = trim( (string) preg_replace( '/\s+/', ' ', $label->textContent ) );
		}
		$fields = array();
		foreach ( $xp->query( '//input | //select | //textarea' ) as $el ) {
			$full = $el->getAttribute( 'name' );
			if ( ! str_starts_with( $full, $prefix ) || ! str_ends_with( $full, ']' ) ) {
				continue;
			}
			$name = substr( $full, strlen( $prefix ), -1 );
			if ( 1 !== preg_match( '/^[a-z0-9_-]{1,64}$/i', $name ) || isset( $fields[ $name ] ) ) {
				continue;
			}
			$tag   = $el->localName; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API.
			$type  = strtolower( $el->getAttribute( 'type' ) );
			$label = $labels[ $el->getAttribute( 'id' ) ] ?? '';
			if ( '' === $label ) {
				$wrap  = $xp->query( 'ancestor::label', $el )->item( 0 );
				$label = $wrap ? trim( (string) preg_replace( '/\s+/', ' ', $wrap->textContent ) ) : '';
			}
			$label = rtrim( $label, ': ' );
			$field = array(
				'name'    => $name,
				'kind'    => 'text',
				'label'   => '' === $label ? $name : $label,
				'default' => '',
			);
			if ( 'select' === $tag ) {
				$field['kind']    = 'select';
				$field['choices'] = array();
				foreach ( $xp->query( './/option', $el ) as $opt ) {
					$field['choices'][] = array(
						'value' => $opt->getAttribute( 'value' ),
						'label' => trim( $opt->textContent ),
					);
					if ( $opt->hasAttribute( 'selected' ) ) {
						$field['default'] = $opt->getAttribute( 'value' );
					}
				}
			} elseif ( 'textarea' === $tag ) {
				$field['kind']    = 'textarea';
				$field['default'] = $el->textContent;
			} elseif ( in_array( $type, array( 'checkbox', 'radio' ), true ) ) {
				if ( 'radio' === $type ) {
					continue; // Radio groups are rare in widget forms; the generic editor handles them.
				}
				$field['kind']    = 'checkbox';
				$field['value']   = '' === $el->getAttribute( 'value' ) ? 'on' : $el->getAttribute( 'value' );
				$field['default'] = $el->hasAttribute( 'checked' ) ? $field['value'] : '';
			} elseif ( in_array( $type, array( 'hidden', 'submit', 'button' ), true ) ) {
				continue;
			} else {
				$field['kind']    = in_array( $type, array( 'number', 'url', 'email' ), true ) ? $type : 'text';
				$field['default'] = $el->getAttribute( 'value' );
			}
			$fields[ $name ] = $field;
		}

		return array_values( $fields );
	}
}
