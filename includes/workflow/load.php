<?php
/**
 * Workflow module, free part (P14): import and export (pbs-g5), and the admin screen's
 * workflow panels. The Pro and Agency parts (roles and client mode, maintenance mode, find and
 * replace, white label, client review, multisite) live in includes/pro__premium_only/workflow/.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-permissions.php';
require_once __DIR__ . '/class-transfer.php';
require_once __DIR__ . '/class-transfer-routes.php';
require_once __DIR__ . '/class-workflow-admin.php';

\ZinnDigital\PBS\Workflow\Transfer_Routes::register();
\ZinnDigital\PBS\Workflow\Workflow_Admin::register();
