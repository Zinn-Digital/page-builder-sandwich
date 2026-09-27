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

require_once __DIR__ . '/class-css.php';
require_once __DIR__ . '/class-svg.php';
require_once __DIR__ . '/class-access.php';
require_once __DIR__ . '/class-fallback.php';
require_once __DIR__ . '/class-render.php';
require_once __DIR__ . '/class-widgets.php';
require_once __DIR__ . '/class-blocks.php';
require_once __DIR__ . '/class-shortcodes.php';
require_once __DIR__ . '/class-templates.php';

\ZinnDigital\PBS\Core\Blocks::register();
\ZinnDigital\PBS\Core\Shortcodes::register();
\ZinnDigital\PBS\Core\Templates::register();

require_once __DIR__ . '/class-dev-compat.php';
\ZinnDigital\PBS\Core\Dev_Compat::register();

require_once __DIR__ . '/class-safe-mode.php';
require_once __DIR__ . '/class-studio.php';
\ZinnDigital\PBS\Core\Safe_Mode::register();
\ZinnDigital\PBS\Core\Studio::register();
