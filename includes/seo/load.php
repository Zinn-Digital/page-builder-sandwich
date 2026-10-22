<?php
/**
 * SEO + performance (P13), free part: SEO plugins' content analysis sees the rendered page
 * (pbs-p10), and working as one with our own Zinn® SEO. The Pro part lives in
 * includes/pro__premium_only/seo/.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

\ZinnDigital\PBS\Seo\Zinn_Seo::register();
\ZinnDigital\PBS\Seo\Analysis::register();
