<?php
/**
 * SEO + performance (P13), free part: SEO plugins' content analysis sees the rendered page
 * (pbs-p10). The Pro part lives in includes/pro__premium_only/seo/.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-analysis.php';

\ZinnDigital\PBS\Seo\Analysis::register();
