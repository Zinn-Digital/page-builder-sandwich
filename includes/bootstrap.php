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

// The SDK would otherwise show a site-wide trial notice on the free package (guideline 11).
pbsw_fs()->add_filter( 'show_trial', '__return_false' );

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
 * The WordPress.org zip ships the same SDK (vendor/freemius), which registers its uninstall hook
 * when the plugin is deactivated (Freemius::_deactivate_plugin_hook), so this runs there too.
 *
 * @return void
 */
function pbsw_uninstall(): void {
	// Leaves the shared AI keys alone while another plugin that uses them is still installed.
	\ZinnDigital\PBS\AiCore\Core::uninstall( 'page-builder-sandwich' );
	\ZinnDigital\PBS\Assets::remove_all();
	delete_option( \ZinnDigital\PBS\Settings::OPTION );
	delete_option( \ZinnDigital\PBS\Settings::DROP_WRAP_OPTION );
	delete_option( \ZinnDigital\PBS\Cloud\Kits::OPT_IN );
	// Every other option this plugin writes, named one by one (wp-zip-check Z014 holds the list
	// to the code that writes them). The ai-core host key is this plugin's own; the shared key
	// belongs to whichever Zinn plugin is still installed.
	foreach (
		array(
			\ZinnDigital\PBS\AiCore\Crypto::HOST_KEY_OPTION,
			\ZinnDigital\PBS\Assets\Legacy_Era::OPTION,
			\ZinnDigital\PBS\Dev\Debug::OPTION,
			\ZinnDigital\PBS\Design\Breakpoints::OPTION,
			\ZinnDigital\PBS\Design\Globals\Theme_Globals::CLASSIC_OPTION,
			\ZinnDigital\PBS\AdminKit\Connection::OPTION,
			\ZinnDigital\PBS\AdminKit\Support::CONSENT,
			\ZinnDigital\PBS\AdminKit\Crypto::KEY_OPTION,
			'pbsw_kit_wizard',
			'pbsw_kit_language_packs',
			'pbsw_kit_language_packs_lock',
			'pbsw_kit_language_packs_version',
		) as $option
	) {
		delete_option( $option );
	}
	wp_clear_scheduled_hook( 'pbsw_kit_language_packs_sync' );
	wp_clear_scheduled_hook( \ZinnDigital\PBS\AdminKit\Legacy_Support_Users::CRON );
	// The kit preferences key is shared with the other kit plugins in a self-hosted build (uninstall-kit-prefs.php).
	require_once __DIR__ . '/uninstall-kit-prefs.php';
	pbsw_uninstall_kit_prefs( \ZinnDigital\PBS\AdminKit\Prefs::META, plugin_basename( PBSW_FILE ) );
	pbsw_uninstall_transients();
	// dotorg-omit:begin
	// A self-hosted build keeps Pro's own options for a reinstall; only this sweep is skipped.
	return;
	// phpcs:disable Squiz.PHP.NonExecutableCode.Unreachable -- the sweep below is reached in the WordPress.org build, where this dotorg-omit block is removed.
	// dotorg-omit:end
	pbsw_uninstall_sweep( plugin_basename( PBSW_FILE ) );
}
// phpcs:enable Squiz.PHP.NonExecutableCode.Unreachable

/**
 * The WordPress.org build's full sweep (review round 6): every option starting `pbsw_` (including
 * the ones only Pro writes and this build reads, such as pbsw_tokens, pbsw_classes and
 * pbsw_safe_mode), every `_pbsw_` post meta key, and the per-user notice dismissal. Skipped while
 * another copy of the plugin (Pro) is still installed, because that copy shares the data.
 *
 * @param string $own_basename This plugin's basename.
 * @return void
 */
function pbsw_uninstall_sweep( string $own_basename ): void {
	global $wpdb;
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	foreach ( array_keys( get_plugins() ) as $basename ) {
		if ( $basename !== $own_basename && str_starts_with( dirname( (string) $basename ), 'page-builder-sandwich' ) ) {
			return;
		}
	}
	$options = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- a one-off sweep on uninstall.
		$wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( 'pbsw_' ) . '%' )
	);
	foreach ( (array) $options as $option ) {
		delete_option( (string) $option );
	}
	$keys = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- a one-off sweep on uninstall.
		$wpdb->prepare( "SELECT DISTINCT meta_key FROM {$wpdb->postmeta} WHERE meta_key LIKE %s", $wpdb->esc_like( '_pbsw_' ) . '%' )
	);
	foreach ( (array) $keys as $key ) {
		delete_post_meta_by_key( (string) $key );
	}
	delete_metadata( 'user', 0, 'pbsw_waiting_dismissed', '', true ); // Migrate\Waiting::DISMISSED_META.
}

/**
 * Drop this plugin's transients: every key starts `pbsw_` (asset publishing lock, kit catalogue,
 * SEO analysis cache, unlock throttle) or is the per-user AI notice. A transient also lives in
 * the object cache when one is installed, so each one found is deleted through the API.
 *
 * @return void
 */
function pbsw_uninstall_transients(): void {
	global $wpdb;
	$names = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- a one-off sweep on uninstall.
		$wpdb->prepare(
			"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_pbsw_' ) . '%',
			$wpdb->esc_like( '_transient_zinn_ai_notice_' ) . '%'
		)
	);
	foreach ( (array) $names as $name ) {
		delete_transient( substr( (string) $name, strlen( '_transient_' ) ) );
	}
}
