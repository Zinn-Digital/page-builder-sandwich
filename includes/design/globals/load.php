<?php
/**
 * Loads the site-wide design globals (lane L09, P4, worker W2). Required from ../../bootstrap.php.
 *
 * The free half: colours and fonts shared with the theme (pbs-d4), the Site design screen and
 * its REST routes. The premium half (classes, variables, tokens, custom CSS, dark mode, brand
 * kit, custom fonts) lives in includes/pro__premium_only/design/ and is booted by Pro::boot().
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-theme-globals.php';
require_once __DIR__ . '/class-design-rest.php';
require_once __DIR__ . '/class-site-design.php';

\ZinnDigital\PBS\Design\Globals\Theme_Globals::register();
\ZinnDigital\PBS\Design\Globals\Design_Rest::register();
\ZinnDigital\PBS\Design\Globals\Site_Design::register();
