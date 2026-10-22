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

\ZinnDigital\PBS\Core\Blocks::register();
\ZinnDigital\PBS\Core\Shortcodes::register();
\ZinnDigital\PBS\Core\Templates::register();

\ZinnDigital\PBS\Core\Dev_Compat::register();

\ZinnDigital\PBS\Core\Safe_Mode::register();
\ZinnDigital\PBS\Core\Studio::register();
