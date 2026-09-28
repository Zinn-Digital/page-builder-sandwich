<?php
/**
 * The Accessibility checker (pbs-a1, free): its editor bundle, in the block editor and Studio.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Design\A11y;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueues build/a11y-checker.js wherever the block editor runs (Studio fires the same action).
 * The checks run in the author's browser on the live canvas; nothing reaches the front end.
 */
final class Checker {

	/** Script handle. */
	public const HANDLE = 'pbsw-a11y-checker';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'enqueue_block_editor_assets', array( self::class, 'enqueue' ) );
	}

	/**
	 * Enqueue the checker.
	 *
	 * @return void
	 */
	public static function enqueue(): void {
		$asset_file = PBSW_DIR . 'build/a11y-checker.asset.php';
		if ( ! is_readable( $asset_file ) || ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		$asset = require $asset_file;
		wp_enqueue_script( self::HANDLE, PBSW_URL . 'build/a11y-checker.js', (array) ( $asset['dependencies'] ?? array() ), (string) ( $asset['version'] ?? PBSW_VERSION ), true );
		wp_set_script_translations( self::HANDLE, 'page-builder-sandwich', PBSW_DIR . 'languages' );
		if ( is_readable( PBSW_DIR . 'build/a11y-checker.css' ) ) {
			wp_enqueue_style( self::HANDLE, PBSW_URL . 'build/a11y-checker.css', array( 'wp-components' ), (string) ( $asset['version'] ?? PBSW_VERSION ) );
			wp_style_add_data( self::HANDLE, 'rtl', 'replace' );
		}
	}
}
