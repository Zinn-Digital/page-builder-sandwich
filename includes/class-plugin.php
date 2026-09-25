<?php
/**
 * Bootstrap.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires every part of the plugin to WordPress.
 */
final class Plugin {

	/**
	 * Register hooks. Called once from the main file.
	 *
	 * @return void
	 */
	public static function boot(): void {
		Assets::register();
		Blocks::register();
		Rest::register();
		Admin::register();
		Freemius_I18n::register();

		register_activation_hook( PBSW_FILE, array( self::class, 'activate' ) );
	}

	/**
	 * Publish the front-end assets to their neutral folder straight away, so the first page
	 * view after activation already uses a static copy.
	 *
	 * @return void
	 */
	public static function activate(): void {
		Assets::publish();
	}
}
