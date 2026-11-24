<?php
/**
 * Reads pages saved before the block namespace moved from `pbs/` to `pbsw/`.
 *
 * Stored posts are never rewritten here, so rolling the plugin back stays safe. The old name is
 * translated wherever content is read: the block parser (front end and every PHP reader) and the
 * REST responses the editor loads. No block is registered under the old name.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The old-to-new block name translation.
 */
final class Block_Names {

	/** The namespace saved content may still carry. */
	private const OLD = 'pbs/';

	/** The current namespace. */
	private const NEW = 'pbsw/';

	/** An opening or closing block comment under the old namespace. */
	private const DELIMITER = '#<!--(\s+/?)wp:pbs/(?=[a-z])#';

	/**
	 * Hook the readers.
	 */
	public static function register(): void {
		add_filter( 'block_parser_class', array( self::class, 'parser_class' ) );
		add_filter( 'render_block_data', array( self::class, 'render_data' ) );
		add_filter( 'rest_request_after_callbacks', array( self::class, 'rest_response' ), 10, 3 );
	}

	/**
	 * Use the translating parser, unless another plugin has chosen its own (`render_data` then
	 * still covers rendering).
	 *
	 * @param string|mixed $class_name The parser class.
	 * @return string|mixed
	 */
	public static function parser_class( $class_name ) {
		return 'WP_Block_Parser' === $class_name ? Block_Parser::class : $class_name;
	}

	/**
	 * Translate one parsed block about to render.
	 *
	 * @param array<string, mixed>|mixed $block The parsed block.
	 * @return array<string, mixed>|mixed
	 */
	public static function render_data( $block ) {
		if ( ! is_array( $block ) ) {
			return $block;
		}

		// With inner blocks: core builds them from this array before their own filter runs.
		return self::upgrade_blocks( array( $block ) )[0];
	}

	/**
	 * The current name for a block name.
	 *
	 * @param string $name A block name.
	 * @return string
	 */
	public static function name( string $name ): string {
		return str_starts_with( $name, self::OLD ) ? self::NEW . substr( $name, strlen( self::OLD ) ) : $name;
	}

	/**
	 * Translate parsed blocks, inner blocks included.
	 *
	 * @param array<int, array<string, mixed>> $blocks Parsed blocks.
	 * @return array<int, array<string, mixed>>
	 */
	public static function upgrade_blocks( array $blocks ): array {
		foreach ( $blocks as $i => $block ) {
			if ( is_string( $block['blockName'] ?? null ) ) {
				$blocks[ $i ]['blockName'] = self::name( $block['blockName'] );
			}
			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$blocks[ $i ]['innerBlocks'] = self::upgrade_blocks( $block['innerBlocks'] );
			}
		}

		return $blocks;
	}

	/**
	 * Translate the block comments in serialized markup.
	 *
	 * @param string $markup Block markup.
	 * @return string
	 */
	public static function upgrade_markup( string $markup ): string {
		if ( ! str_contains( $markup, 'wp:' . self::OLD ) ) {
			return $markup;
		}

		return (string) preg_replace( self::DELIMITER, '<!--$1wp:' . self::NEW, $markup );
	}

	/**
	 * Whether raw content holds one of our blocks, under either namespace.
	 *
	 * @param string $content Raw content.
	 * @param string $prefix  Start of the block's slug; empty for any of our blocks.
	 * @return bool
	 */
	public static function in_content( string $content, string $prefix = '' ): bool {
		return str_contains( $content, 'wp:' . self::NEW . $prefix ) || str_contains( $content, 'wp:' . self::OLD . $prefix );
	}

	/**
	 * `has_block()` for one of our blocks, under either namespace.
	 *
	 * @param string            $slug The block's slug, without a namespace.
	 * @param int|string|object $post What `has_block()` accepts.
	 * @return bool
	 */
	public static function has_block( string $slug, $post = null ): bool {
		return has_block( self::NEW . $slug, $post ) || has_block( self::OLD . $slug, $post );
	}

	/**
	 * Give the editor current names in the content it loads, for every post type, revision,
	 * autosave, template, pattern and block widget. Only edit-context reads change.
	 *
	 * @param mixed            $response The response.
	 * @param mixed            $handler  The route handler.
	 * @param \WP_REST_Request $request  The request.
	 * @return mixed
	 */
	public static function rest_response( $response, $handler, $request ) {
		unset( $handler );
		if ( ! $response instanceof \WP_REST_Response || ! $request instanceof \WP_REST_Request || 'edit' !== $request['context'] ) {
			return $response;
		}
		$data = $response->get_data();
		if ( ! is_array( $data ) ) {
			return $response;
		}
		if ( array_is_list( $data ) ) {
			foreach ( $data as $i => $item ) {
				$data[ $i ] = is_array( $item ) ? self::upgrade_item( $item ) : $item;
			}
		} else {
			$data = self::upgrade_item( $data );
		}
		$response->set_data( $data );

		return $response;
	}

	/**
	 * Translate the content fields of one REST item.
	 *
	 * @param array<string, mixed> $item The item.
	 * @return array<string, mixed>
	 */
	private static function upgrade_item( array $item ): array {
		if ( is_string( $item['content']['raw'] ?? null ) ) {
			$item['content']['raw'] = self::upgrade_markup( $item['content']['raw'] );
		} elseif ( is_string( $item['content'] ?? null ) ) {
			$item['content'] = self::upgrade_markup( $item['content'] );
		}
		if ( is_string( $item['instance']['raw']['content'] ?? null ) ) {
			$item['instance']['raw']['content'] = self::upgrade_markup( $item['instance']['raw']['content'] );
		}

		return $item;
	}
}
