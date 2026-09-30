<?php
/**
 * Templates & kits: the kit library, the section patterns (free), and the hooks the premium cloud
 * library builds on.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-importer.php';
require_once __DIR__ . '/class-kits.php';
require_once __DIR__ . '/class-patterns.php';

\ZinnDigital\PBS\Cloud\Kits::register();
\ZinnDigital\PBS\Cloud\Patterns::register();
