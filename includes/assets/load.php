<?php
/**
 * Loads the assets layer (lane L08). Each file in this directory is required here and registered
 * from here, so the plugin bootstrap has exactly one line per layer.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Legacy front-end compatibility: class rewrite, neutral wrapper, compat stylesheet (L08 worker W3).
\ZinnDigital\PBS\Assets\Legacy::register();

// Legacy front-end behaviour: the script and its server-side halves (L08 worker W8).
\ZinnDigital\PBS\Assets\Legacy_Script::register();

// P2 performance engine: per-block assets, compiled page CSS, LCP/CLS, fonts, cache purge (L08 worker W7).
require_once __DIR__ . '/perf/load.php';
