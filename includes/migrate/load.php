<?php
/**
 * Loads the migrate layer (lane L08). Each file in this directory is required here and registered
 * from here, so the plugin bootstrap has exactly one line per layer.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-serializer.php';
require_once __DIR__ . '/class-legacy.php';
require_once __DIR__ . '/class-backup.php';
require_once __DIR__ . '/class-converter.php';
require_once __DIR__ . '/class-media.php';

// Background migration, first-open conversion, REST and WP-CLI (L08 worker W3). Action Scheduler
// loads through its own version-resolution loader, so a newer copy bundled by another plugin
// (WooCommerce, …) wins and ours simply registers as a candidate — never a redeclare.
require_once PBSW_DIR . 'vendor/woocommerce/action-scheduler/action-scheduler.php';
require_once __DIR__ . '/class-run.php';
require_once __DIR__ . '/class-batch.php';
require_once __DIR__ . '/class-first-open.php';
require_once __DIR__ . '/class-rest-routes.php';
require_once __DIR__ . '/class-cli.php';
\ZinnDigital\PBS\Migrate\Batch::register();
\ZinnDigital\PBS\Migrate\First_Open::register();
\ZinnDigital\PBS\Migrate\Rest_Routes::register();
\ZinnDigital\PBS\Migrate\Cli::register();
