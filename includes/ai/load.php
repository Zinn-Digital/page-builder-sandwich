<?php
/**
 * AI inside the builder (P12), free part: write and rewrite text (pbs-ai1), a section from a
 * sentence (pbs-ai2), and the SEO and alt text helper (pbs-ai8). The Pro part (page and site
 * builder, the assistant chat that edits the page, custom blocks, design from a screenshot or
 * link, image generation, the CSS helper and accessibility fixes) lives in
 * includes/pro__premium_only/ai/.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

\ZinnDigital\PBS\Ai\Ai_Routes::register();
