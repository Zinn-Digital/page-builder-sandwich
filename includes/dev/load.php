<?php
/**
 * The developer platform (P16): the public PHP API (api.php: pbsw_register_block,
 * pbsw_register_dynamic_source, pbsw_register_condition, pbsw_register_form_action,
 * pbsw_register_style_control,
 * pbsw_get_extensions), its registry, and the extensions list (REST + MCP).
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-registry.php';
require_once __DIR__ . '/class-style-control.php';
require_once __DIR__ . '/api.php';
require_once __DIR__ . '/class-dev-routes.php';

\ZinnDigital\PBS\Dev\Registry::register();
\ZinnDigital\PBS\Dev\Dev_Routes::register();
