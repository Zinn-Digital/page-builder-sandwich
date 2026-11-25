<?php
/**
 * Uninstall: the admin kit's per-user preferences (`Prefs::META`).
 *
 * ⚖️ Coordinator ruling 2026-11-24 (PBS wp.org review round 5): in a self-hosted build the meta key
 * is `zinn_kit_prefs`, ONE key shared by every plugin that carries the admin kit (Tranzly, Zinn® SEO,
 * PBS free and Premium), so deleting it while another of them is installed would wipe that plugin's
 * preferences too. It is deleted only when no other kit plugin is installed. The WordPress.org build
 * renames the kit's `zinn_*` names to PBS's own, so there the key is PBS's alone and is always
 * deleted: the check sits in a dotorg-omit region.
 *
 * Its own file so a unit test can load it without the plugin's start-up.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Delete every user's kit preferences, unless another kit plugin still uses the shared key.
 *
 * @param string $meta_key The preferences meta key (`Prefs::META`).
 * @param string $own_basename This plugin's basename, which does not count as "another" plugin.
 * @return bool Whether the key was deleted.
 */
function pbsw_uninstall_kit_prefs( string $meta_key, string $own_basename ): bool {
	// dotorg-omit:begin
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$installed = array_keys( (array) get_plugins() );
	foreach ( \ZinnDigital\PBS\AdminKit\Data::products() as $product ) {
		foreach ( (array) ( $product['main_files'] ?? array() ) as $file ) {
			if ( $own_basename !== $file && in_array( (string) $file, $installed, true ) ) {
				return false;
			}
		}
	}
	// dotorg-omit:end
	delete_metadata( 'user', 0, $meta_key, '', true );
	return true;
}
