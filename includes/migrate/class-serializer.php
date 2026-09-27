<?php
/**
 * Serialized block markup, written the way the block editor writes it.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Migrate;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds `<!-- wp:name {json} -->…<!-- /wp:name -->` markup.
 *
 * ⭐ Written locally rather than through core's `serialize_block()` for two reasons: the unit
 * suite runs without WordPress, and the converter must produce the SAME bytes the editor's own
 * JavaScript serializer produces (a newline inside the delimiters, inner blocks joined by a blank
 * line, attributes JSON-escaped with the same five replacements), so a converted post that is
 * opened and saved again without an edit does not change. Core's PHP serializer writes neither
 * newline.
 */
final class Serializer {

	/**
	 * One block.
	 *
	 * @param string   $name   Block name; the `core/` namespace is dropped as the editor does.
	 * @param array    $attrs  Comment attributes (empty array ⇒ none written).
	 * @param string   $open   Saved HTML before the inner blocks (or the whole saved HTML).
	 * @param string[] $inner  Already-serialized inner blocks.
	 * @param string   $close  Saved HTML after the inner blocks.
	 * @return string
	 */
	public static function block( string $name, array $attrs = array(), string $open = '', array $inner = array(), string $close = '' ): string {
		$name    = str_starts_with( $name, 'core/' ) ? substr( $name, 5 ) : $name;
		$json    = array() === $attrs ? '' : self::attributes( $attrs ) . ' ';
		$content = $open . implode( "\n\n", $inner ) . $close;
		if ( '' === $content ) {
			return '<!-- wp:' . $name . ' ' . $json . '/-->';
		}

		return '<!-- wp:' . $name . ' ' . $json . "-->\n" . $content . "\n<!-- /wp:" . $name . ' -->';
	}

	/**
	 * The comment-attribute JSON, escaped exactly as `serializeAttributes()` in
	 * `@wordpress/blocks` (and core's `serialize_block_attributes()`) escape it, so the result
	 * can never close the comment or be mistaken for HTML.
	 *
	 * @param array $attrs Attributes.
	 * @return string
	 */
	public static function attributes( array $attrs ): string {
		$json = (string) json_encode( $attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- the exact flags matter, and the unit suite runs without WordPress.

		return str_replace(
			array( '--', '<', '>', '&', '\\"' ),
			array( '\\u002d\\u002d', '\\u003c', '\\u003e', '\\u0026', '\\u0022' ),
			$json
		);
	}
}
