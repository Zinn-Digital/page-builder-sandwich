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

\ZinnDigital\PBS\Cloud\Kits::register();
\ZinnDigital\PBS\Cloud\Patterns::register();
