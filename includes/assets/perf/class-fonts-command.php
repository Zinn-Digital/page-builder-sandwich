<?php
/**
 * WP-CLI: local Google Fonts.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Assets\Perf;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Download, list and remove locally hosted Google Fonts.
 */
final class Fonts_Command {

	/**
	 * Download a Google Fonts family to this site.
	 *
	 * ## OPTIONS
	 *
	 * <family>
	 * : Family name, e.g. "Open Sans".
	 *
	 * [--weights=<weights>]
	 * : Comma-separated weights.
	 * ---
	 * default: 400,700
	 * ---
	 *
	 * [--italic]
	 * : Also download the italic faces.
	 *
	 * @param array<int, string>    $args  Positional arguments.
	 * @param array<string, string> $assoc Options.
	 * @return void
	 */
	public function download( array $args, array $assoc ): void {
		$weights = array_map( 'intval', explode( ',', (string) ( $assoc['weights'] ?? '400,700' ) ) );
		$result  = Fonts::download( (string) ( $args[0] ?? '' ), $weights, isset( $assoc['italic'] ) );
		if ( is_wp_error( $result ) ) {
			\WP_CLI::error( $result->get_error_message() );
		}
		/* translators: 1: font family, 2: number of files, 3: stylesheet URL. */
		\WP_CLI::success( sprintf( __( '%1$s: %2$d font files stored, stylesheet %3$s', 'page-builder-sandwich' ), $result['family'], count( $result['files'] ), $result['url'] ) );
	}

	/**
	 * List the stored families.
	 *
	 * @return void
	 */
	public function list(): void {
		foreach ( Fonts::stored() as $family => $entry ) {
			\WP_CLI::line( $family . "\t" . implode( ',', (array) $entry['weights'] ) . "\t" . (string) $entry['url'] );
		}
	}

	/**
	 * Remove a stored family and its files.
	 *
	 * ## OPTIONS
	 *
	 * <family>
	 * : Family name.
	 *
	 * @param array<int, string> $args Positional arguments.
	 * @return void
	 */
	public function remove( array $args ): void {
		if ( ! Fonts::remove( (string) ( $args[0] ?? '' ) ) ) {
			\WP_CLI::error( __( 'That family is not stored.', 'page-builder-sandwich' ) );
		}
		\WP_CLI::success( __( 'Removed.', 'page-builder-sandwich' ) );
	}
}
