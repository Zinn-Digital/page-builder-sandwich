<?php
/**
 * Loads the design-system blocks layer (lane L09): the registry, which registers every block
 * listed in it (includes/blocks/class-registry.php). Required from the bootstrap BEFORE the
 * assets layer, whose per-block stylesheet map reads the same list.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-registry.php';
\ZinnDigital\PBS\Blocks\Registry::register();
