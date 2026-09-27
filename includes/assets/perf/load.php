<?php
/**
 * Loads the P2 performance engine (lane L08, worker W7). Required from ../load.php.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

foreach ( array( 'files', 'perf', 'block-assets', 'page-css', 'modules', 'layout-guard', 'lcp', 'fonts', 'fonts-command', 'cache-purge' ) as $pbsw_perf_file ) {
	require_once __DIR__ . '/class-' . $pbsw_perf_file . '.php';
}
unset( $pbsw_perf_file );

\ZinnDigital\PBS\Assets\Perf\Perf::register();
