<?php
/**
 * Loads the plugin: the start-up decision (Cli_Start), the licensing icon and uninstall cleanup;
 * then starts it. The start itself — every class, the boot calls, the premium layer — is in
 * start.php, which Cli_Start runs at once, or in a WP-CLI command right after WordPress has
 * loaded (stage PERF3: WP-CLI loads WordPress under the 128 MB limit and lifts it only afterwards).
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

require_once __DIR__ . '/class-autoload.php';
\ZinnDigital\PBS\Autoload::register();
require_once __DIR__ . '/class-cli-start.php';
// The public PHP API for add-ons (pbsw_register_block() …) exists from the moment the plugin is
// read, however late it starts: an add-on may call it on `plugins_loaded` or `init`.
require_once __DIR__ . '/dev/api.php';

/*
 * The licensing SDK's screens show THIS icon (the WordPress.org one, wp/dotorg-assets/page-builder-sandwich).
 * Without a local icon the SDK downloads one from the licensing service on a local install, before
 * any consent (D28900; proven by wp/tests/e2e/pbs-release/no-http-before-consent.sh).
 */
pbsw_fs()->add_filter( 'plugin_icon', static fn(): string => PBSW_DIR . 'assets/icon-256x256.png' );

// ⭐ Anything that must run WHILE WordPress loads even when the start is deferred goes above
// this line; the rest is in start.php.
\ZinnDigital\PBS\Cli_Start::start(
	static function (): void {
		require_once __DIR__ . '/start.php';
	}
);

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
