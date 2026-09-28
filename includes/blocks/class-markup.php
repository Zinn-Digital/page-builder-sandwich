<?php
/**
 * Small markup helpers shared by the design-system blocks' render callbacks (lane L09).
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Blocks;

use ZinnDigital\PBS\Assets\Perf\Modules;
use ZinnDigital\PBS\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helpers.
 */
final class Markup {

	/**
	 * The rendered inner blocks of a block: each child rendered by WordPress (its own callback and
	 * filters run). Without a WP_Block (a direct call), the `$content` WordPress passed is used.
	 *
	 * @param mixed  $block   WP_Block or null.
	 * @param string $content Rendered content WordPress passed.
	 * @return string
	 */
	public static function inner( $block, string $content ): string {
		if ( ! $block instanceof \WP_Block ) {
			return $content;
		}
		$html = '';
		foreach ( $block->inner_blocks as $child ) {
			$html .= $child->render();
		}

		return $html;
	}

	/**
	 * The children of a block, as `[ WP_Block child, rendered HTML ]` pairs, in order.
	 *
	 * @param mixed $block WP_Block or null.
	 * @return array<int, array{0: \WP_Block, 1: string}>
	 */
	public static function children( $block ): array {
		$out = array();
		if ( $block instanceof \WP_Block ) {
			foreach ( $block->inner_blocks as $child ) {
				$out[] = array( $child, (string) $child->render() );
			}
		}

		return $out;
	}

	/**
	 * Add attributes to the FIRST tag of a fragment one of our own callbacks rendered (a child
	 * panel, an accordion item). Values are escaped here; `true` writes a bare attribute.
	 *
	 * @param string                         $html  Fragment starting with a tag.
	 * @param array<string, string|int|bool> $attrs Attribute → value.
	 * @return string
	 */
	public static function add_attributes( string $html, array $attrs ): string {
		$add = '';
		foreach ( $attrs as $name => $value ) {
			if ( false === $value || 1 !== preg_match( '/^[a-z][a-z0-9:_.-]*$/', (string) $name ) ) {
				continue;
			}
			$add .= true === $value ? ' ' . $name : ' ' . $name . '="' . esc_attr( (string) $value ) . '"';
		}

		return (string) preg_replace( '/^(\s*<[a-z][a-z0-9-]*)/i', '$1' . str_replace( array( '\\', '$' ), array( '\\\\', '\\$' ), $add ), $html, 1 );
	}

	/**
	 * A unique, neutral id for this request (`zd-tabs-3`).
	 *
	 * @param string $what What it names.
	 * @return string
	 */
	public static function id( string $what ): string {
		return wp_unique_id( Settings::prefix() . '-' . $what . '-' );
	}

	/**
	 * The attributes that make an element an Interactivity API region of ours (and enqueue the
	 * block's view module).
	 *
	 * @param string               $module  Registered module name.
	 * @param array<string, mixed> $context Initial context.
	 * @return string Attributes, each with its leading space.
	 */
	public static function interactive( string $module, array $context ): string {
		Modules::enqueue( $module );

		return ' data-wp-interactive="' . esc_attr( Modules::store() ) . '"'
			. " data-wp-context='" . esc_attr( (string) wp_json_encode( array() === $context ? new \stdClass() : $context ) ) . "'";
	}

	/**
	 * A context attribute for a nested element.
	 *
	 * @param array<string, mixed> $context Context.
	 * @return string
	 */
	public static function context( array $context ): string {
		return " data-wp-context='" . esc_attr( (string) wp_json_encode( $context ) ) . "'";
	}
}
