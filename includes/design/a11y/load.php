<?php
/**
 * Loads accessibility (lane L09, P5 group G-D): the checker (pbs-a1). Required from ../../bootstrap.php.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

\ZinnDigital\PBS\Design\A11y\Checker::register();
