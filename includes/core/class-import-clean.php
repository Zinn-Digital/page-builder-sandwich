<?php
/**
 * Cleans markup that arrives from outside this site: a kit, or a template export file.
 *
 * Import is never a way to add code, whoever runs it. `unfiltered_html` lets a person type their
 * own script into the editor; it does not extend to a file or a service they are importing.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Import sanitising.
 */
final class Import_Clean {

	/**
	 * The markup with scripts, style elements and event handlers removed, and without per-element
	 * custom CSS.
	 *
	 * @param string $markup Unslashed block markup.
	 * @return string
	 */
	public static function content( string $markup ): string {
		// Whole elements first: kses alone removes the tags and leaves the code as visible text.
		$without = preg_replace( '#<(script|style)\b[^>]*>.*?(?:</\1\s*>|$)#is', '', $markup );
		$clean   = (string) wp_kses_post( null === $without ? $markup : $without );
		if ( ! str_contains( $clean, '"css"' ) ) {
			return $clean;
		}

		/**
		 * Filters whether imported blocks keep their per-element custom CSS.
		 *
		 * @param bool $keep False: the CSS is removed.
		 */
		if ( true === apply_filters( 'pbsw_import_keeps_element_css', false ) ) {
			return $clean;
		}

		$changed = false;
		$blocks  = self::without_css( parse_blocks( $clean ), $changed );

		return $changed ? serialize_blocks( $blocks ) : $clean;
	}

	/**
	 * Remove `pbs.css` from a block tree.
	 *
	 * @param array<int, array<string, mixed>> $blocks  Parsed blocks.
	 * @param bool                             $changed Set to true when a value was removed.
	 * @return array<int, array<string, mixed>>
	 */
	private static function without_css( array $blocks, bool &$changed ): array {
		foreach ( $blocks as $i => $block ) {
			if ( isset( $block['attrs']['pbs'] ) && is_array( $block['attrs']['pbs'] ) && array_key_exists( 'css', $block['attrs']['pbs'] ) ) {
				unset( $blocks[ $i ]['attrs']['pbs']['css'] );
				$changed = true;
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$blocks[ $i ]['innerBlocks'] = self::without_css( $block['innerBlocks'], $changed );
			}
		}

		return $blocks;
	}
}
