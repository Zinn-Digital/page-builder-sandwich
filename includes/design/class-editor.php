<?php
/**
 * The design panel's editor script (pbs-p4).
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Design;

use ZinnDigital\PBS\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads build/design.js in the block editor and in Sandwich Studio (which fires the same
 * `enqueue_block_editor_assets`), printed in the HEAD so its `blocks.registerBlockType` filter is in
 * place before blocks registered by footer scripts.
 */
final class Editor {

	/** Script handle (wp-admin only, so it may name the plugin). */
	public const HANDLE = 'pbsw-design-editor';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'init', array( self::class, 'register_script' ) );
		add_action( 'enqueue_block_editor_assets', array( self::class, 'enqueue' ), 1 );
		add_action( 'enqueue_block_assets', array( self::class, 'canvas_styles' ) );
	}

	/**
	 * `enqueue_block_assets` in wp-admin: the canvas styles (the visual grid editor) inside the
	 * editor iframe, which loads what this action enqueues. Never on the front end.
	 *
	 * @return void
	 */
	public static function canvas_styles(): void {
		if ( ! is_admin() || ! is_readable( PBSW_DIR . 'build/design.css' ) ) {
			return;
		}
		wp_enqueue_style( 'pbsw-design-canvas', PBSW_URL . 'build/design.css', array(), PBSW_VERSION );
		wp_style_add_data( 'pbsw-design-canvas', 'rtl', 'replace' );
	}

	/**
	 * Register the bundle.
	 *
	 * @return void
	 */
	public static function register_script(): void {
		$asset_file = PBSW_DIR . 'build/design.asset.php';
		if ( ! is_readable( $asset_file ) ) {
			return;
		}
		$asset = require $asset_file;
		wp_register_script(
			self::HANDLE,
			PBSW_URL . 'build/design.js',
			(array) ( $asset['dependencies'] ?? array() ),
			(string) ( $asset['version'] ?? PBSW_VERSION ),
			false
		);
		wp_set_script_translations( self::HANDLE, 'page-builder-sandwich', PBSW_DIR . 'languages' );
		$css = 'build/style-design.css'; // The Style tab (the admin document); build/design.css is the canvas.
		if ( is_readable( PBSW_DIR . $css ) ) {
			wp_register_style( self::HANDLE, PBSW_URL . $css, array( 'wp-components' ), (string) ( $asset['version'] ?? PBSW_VERSION ) );
			wp_style_add_data( self::HANDLE, 'rtl', 'replace' );
		}
	}

	/**
	 * `enqueue_block_editor_assets`.
	 *
	 * @return void
	 */
	public static function enqueue(): void {
		if ( ! wp_script_is( self::HANDLE, 'registered' ) ) {
			return;
		}
		wp_enqueue_script( self::HANDLE );
		if ( wp_style_is( self::HANDLE, 'registered' ) ) {
			wp_enqueue_style( self::HANDLE );
		}
		wp_add_inline_script(
			self::HANDLE,
			'window.pbswDesign = ' . wp_json_encode( self::data(), JSON_HEX_TAG | JSON_UNESCAPED_SLASHES ) . ';',
			'before'
		);
	}

	/**
	 * What the editor needs: the prefix, the breakpoints in output order, and the Pro tokens.
	 *
	 * @return array<string, mixed>
	 */
	public static function data(): array {
		$tokens = get_option( 'pbsw_tokens', array() );

		return array(
			'prefix'        => Settings::prefix(),
			'breakpoints'   => Breakpoints::media_list(),
			'customAllowed' => Breakpoints::custom_allowed(),
			'tokens'        => is_array( $tokens ) ? $tokens : array(),
			'elementCss'    => (bool) apply_filters( 'pbsw_design_element_css', false ) && current_user_can( 'unfiltered_html' ),
			'settingsUrl'   => admin_url( 'admin.php?page=page-builder-sandwich' ),
		);
	}
}
