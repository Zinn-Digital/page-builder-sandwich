<?php
/**
 * Icon library (pbs-b2, pbs-r12): editor data for the IconPicker and uploaded SVG icons.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-icons.php';

\ZinnDigital\PBS\Design\Icons\Icons::register();
