<?php
/**
 * Block registration.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the editor script and the fixture blocks.
 *
 * ⭐ The block metadata (`blocks/<name>/block.json`) carries NO `style` or `viewScript`: WordPress
 * would serve those from the plugin directory on the front end. Front-end CSS goes through
 * Assets instead, from a neutral path, and only when a block actually renders.
 */
final class Blocks {

	/** Editor script handle — wp-admin only, so it may carry the plugin's name. */
	public const EDITOR_HANDLE = 'pbsw-editor';

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'init', array( self::class, 'register_blocks' ) );
	}

	/**
	 * Register the editor script, then both blocks.
	 *
	 * @return void
	 */
	public static function register_blocks(): void {
		$asset_file = PBSW_DIR . 'build/editor.asset.php';
		if ( is_readable( $asset_file ) ) {
			$asset = require $asset_file;
			wp_register_script(
				self::EDITOR_HANDLE,
				PBSW_URL . 'build/editor.js',
				(array) ( $asset['dependencies'] ?? array() ),
				(string) ( $asset['version'] ?? PBSW_VERSION ),
				true
			);
			wp_set_script_translations( self::EDITOR_HANDLE, 'page-builder-sandwich', PBSW_DIR . 'languages' );
		}

		register_block_type(
			PBSW_DIR . 'blocks/fixture',
			array( 'render_callback' => array( Fixture::class, 'render_block' ) )
		);
		register_block_type(
			PBSW_DIR . 'blocks/fixture-switcher',
			array( 'render_callback' => array( Fixture::class, 'render_switcher_block' ) )
		);
	}
}
