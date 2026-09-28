<?php
/**
 * Adds the workflow panels to the plugin's admin screen: the free bundle (import and export)
 * and, when the premium layer is loaded, its bundle (enqueued by the Pro module itself).
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Workflow;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin screen integration.
 */
final class Workflow_Admin {

	/** Script handle. */
	public const HANDLE = 'pbsw-workflow';

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'pbsw_admin_enqueue', array( self::class, 'enqueue' ) );
		add_filter( 'block_editor_settings_all', array( self::class, 'editor_settings' ) );
	}

	/**
	 * Give editor scripts (the block editor and Studio) the current user's builder permissions as
	 * the block editor setting `pbsPermissions`, so a panel can hide what the user may not use.
	 * The server enforces the same permissions on save; hiding is a courtesy, not the control.
	 *
	 * @param array<string, mixed> $settings Block editor settings.
	 * @return array<string, mixed>
	 */
	public static function editor_settings( array $settings ): array {
		$settings['pbsPermissions'] = Permissions::map();

		return $settings;
	}

	/**
	 * Enqueue the free workflow bundle after the admin screen's own script.
	 *
	 * @param string $after The admin screen's script handle.
	 * @return void
	 */
	public static function enqueue( string $after ): void {
		self::enqueue_bundle( self::HANDLE, 'workflow', $after );
	}

	/**
	 * Enqueue one built bundle with its generated dependencies plus the parent handle.
	 *
	 * @param string $handle Script handle.
	 * @param string $entry  Webpack entry name (build/<entry>.js).
	 * @param string $after Handle it must load after.
	 * @return bool Whether the bundle exists.
	 */
	public static function enqueue_bundle( string $handle, string $entry, string $after ): bool {
		$asset_file = PBSW_DIR . 'build/' . $entry . '.asset.php';
		if ( ! is_readable( $asset_file ) ) {
			return false;
		}
		$asset = require $asset_file;
		wp_enqueue_script(
			$handle,
			PBSW_URL . 'build/' . $entry . '.js',
			array_merge( (array) ( $asset['dependencies'] ?? array() ), array( $after ) ),
			(string) ( $asset['version'] ?? PBSW_VERSION ),
			true
		);
		wp_set_script_translations( $handle, 'page-builder-sandwich', PBSW_DIR . 'languages' );

		return true;
	}
}
