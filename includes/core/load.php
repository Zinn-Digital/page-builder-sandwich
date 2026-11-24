<?php
/**
 * Loads the core layer (lane L08). Each file in this directory is required here and registered
 * from here, so the plugin bootstrap has exactly one line per layer.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

\ZinnDigital\PBS\Core\Block_Names::register();
\ZinnDigital\PBS\Core\Blocks::register();
\ZinnDigital\PBS\Core\Shortcodes::register();
\ZinnDigital\PBS\Core\Templates::register();

\ZinnDigital\PBS\Core\Dev_Compat::register();
\ZinnDigital\PBS\Core\Widgets::register();

\ZinnDigital\PBS\Core\Studio::register();

// Safe mode is not part of the free plugin. Without its stored key, a must-use file left by an
// earlier version does nothing, so no file is touched here.
add_action(
	'admin_init',
	static function (): void {
		if ( ! class_exists( '\ZinnDigital\PBS\Core\Safe_Mode', false ) && false !== get_option( 'pbsw_safe_mode' ) ) {
			delete_option( 'pbsw_safe_mode' );
		}
	}
);
