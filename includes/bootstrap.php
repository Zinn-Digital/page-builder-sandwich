<?php
/**
 * Loads the plugin: every class, the boot call, the premium layer, and uninstall cleanup.
 *
 * Kept out of the main file on purpose: the licensing service re-prints the main file, so it
 * holds only the headers and the SDK init, and everything with logic lives here in WPCS style.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-settings.php';
require_once __DIR__ . '/class-assets.php';
require_once __DIR__ . '/class-frontend.php';
require_once __DIR__ . '/class-fixture.php';
require_once __DIR__ . '/class-blocks.php';
require_once __DIR__ . '/class-licensing.php';
require_once __DIR__ . '/class-freemius-i18n.php';
require_once __DIR__ . '/class-rest.php';
require_once __DIR__ . '/class-admin.php';
require_once __DIR__ . '/class-plugin.php';
require_once __DIR__ . '/core/load.php';
require_once __DIR__ . '/blocks/load.php';
require_once __DIR__ . '/design/icons/load.php';
require_once __DIR__ . '/migrate/load.php';
require_once __DIR__ . '/design/load.php';
require_once __DIR__ . '/assets/load.php';
require_once __DIR__ . '/design/globals/load.php';
require_once __DIR__ . '/workflow/load.php';

\ZinnDigital\PBS\Plugin::boot();

/*
 * The shared AI core (wp/packages/zinn-ai-core, rendered into ai-core/ by wp/bin/build-ai-core.php).
 * If another plugin on the site carries its own copy, the copies agree on one settings screen and
 * one set of keys by themselves; see ai-core/class-core.php.
 */
require_once __DIR__ . '/ai-core/load.php';
\ZinnDigital\PBS\AiCore\Core::boot(
	array(
		'slug'     => 'page-builder-sandwich',
		'name'     => 'Page Builder Sandwich',
		'pro'      => static fn(): bool => pbsw_fs()->can_use_premium_code(),
		// Every AI request this plugin sends passes through `pbsw_ai_messages` ($messages, $context:
		// task, purpose, provider, model, user); the premium brand kit adds the brand (pbs-d10).
		'messages' => static fn( array $messages, array $context ): array => (array) apply_filters( 'pbsw_ai_messages', $messages, $context ),
	)
);

/*
 * ⛔⛔ THE PREMIUM LAYER LOADS ONLY WHEN ITS FILE IS PRESENT AND THE LICENCE ALLOWS IT.
 *
 * The free package (house and licensing-service alike) drops the whole premium-only directory,
 * so the first test fails there and nothing else runs. The directory name is assembled from two
 * halves so the premium-only token never appears in a file that ships in the free package: that
 * file must reach the free zip byte-for-byte unchanged (CONTRACT §3, docs/adr/0032).
 */
$pbsw_premium_entry = __DIR__ . '/pro_' . '_premium_only/class-pro.php'; // phpcs:ignore Generic.Strings.UnnecessaryStringConcat.Found -- deliberate split, see above.
if ( is_readable( $pbsw_premium_entry ) && pbsw_fs()->can_use_premium_code() ) {
	require_once $pbsw_premium_entry;
	\ZinnDigital\PBS\Pro\Pro::boot();
}
unset( $pbsw_premium_entry );

/**
 * Uninstall cleanup, run by the licensing SDK's `after_uninstall` action.
 *
 * ⛔ There is no uninstall.php: Freemius refuses an upload that contains one (HTTP 400
 * `uninstall_script`), because WordPress runs that file INSTEAD of the SDK's own uninstall hook.
 *
 * @return void
 */
function pbsw_uninstall(): void {
	// Leaves the shared AI keys alone while another plugin that uses them is still installed.
	\ZinnDigital\PBS\AiCore\Core::uninstall( 'page-builder-sandwich' );
	\ZinnDigital\PBS\Assets::remove_all();
	delete_option( \ZinnDigital\PBS\Settings::OPTION );
}
