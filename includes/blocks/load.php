<?php
/**
 * Loads the design-system blocks layer (lane L09): the registry, which registers every block
 * listed in it (includes/blocks/class-registry.php), and the helpers the render callbacks share.
 * Required from the bootstrap BEFORE the assets layer, whose per-block stylesheet map reads the
 * same list.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-registry.php';
require_once __DIR__ . '/class-markup.php';
require_once __DIR__ . '/interactive/class-tabs.php';
require_once __DIR__ . '/interactive/class-accordion.php';
require_once __DIR__ . '/interactive/class-dialog.php';
require_once __DIR__ . '/interactive/class-tooltip.php';
require_once __DIR__ . '/site/class-toc.php';
\ZinnDigital\PBS\Blocks\Registry::register();
// The inline "Tooltip" text format, and the anchors a table of contents links to.
\ZinnDigital\PBS\Blocks\Interactive\Tooltip::register();
\ZinnDigital\PBS\Blocks\Site\Toc::register();

// P5 group G-B: glossary auto-linking, and the format / style / variation extensions whose CSS
// joins the page sheet only when a page uses them (highlight, drop cap, pull quote).
require_once __DIR__ . '/class-kit.php';
require_once __DIR__ . '/class-glossary.php';
require_once __DIR__ . '/class-extensions.php';
\ZinnDigital\PBS\Blocks\Glossary::register();
\ZinnDigital\PBS\Blocks\Extensions::register();

// pbs/code: highlight.js 11.12.0 (BSD-3-Clause, assets/vendor/highlight/LICENSE), bundled locally and
// published through the neutral path like every front-end asset; loaded only on a page that
// renders a code block (Data::render_code()).
\ZinnDigital\PBS\Assets::add_source( 'vendor/highlight.js', 'assets/vendor/highlight/highlight-core.js' );

// pbs/protected: the password form's POST, no-store headers, and the editor's hashing route.
require_once __DIR__ . '/class-site.php';
\ZinnDigital\PBS\Blocks\Site::register();
require_once __DIR__ . '/class-forms.php';
require_once __DIR__ . '/class-friendly.php';
\ZinnDigital\PBS\Blocks\Forms::register(); // pbs-m6: the Form block's provider list.
\ZinnDigital\PBS\Blocks\Friendly::register(); // pbs-b3: Widget and Shortcode block settings.
