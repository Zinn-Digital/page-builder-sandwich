<?php
/**
 * Loads the design system (lane L09, P4): props, breakpoints, compiler, render filter, editor.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

foreach ( array( 'choice', 'length', 'color', 'sides', 'template', 'number', 'span', 'shadow' ) as $pbsw_kind ) {
	require_once __DIR__ . '/kinds/class-' . $pbsw_kind . '-prop.php';
}
unset( $pbsw_kind );
spl_autoload_register( array( \ZinnDigital\PBS\Design\Props::class, 'autoload' ) );

\ZinnDigital\PBS\Design\Styles::register();
\ZinnDigital\PBS\Design\Rest::register();
\ZinnDigital\PBS\Design\Editor::register();

add_filter(
	'pbsw_admin_data',
	static function ( array $data ): array {
		$data['breakpointsPath'] = '/' . \ZinnDigital\PBS\Design\Rest::NAMESPACE . '/design/breakpoints';
		return $data;
	}
);
