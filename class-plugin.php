<?php

/**
 * Plugin Name:       Page Builder Sandwich
 * Plugin URI:        https://zinndigital.com/wordpress-plugins/page-builder-sandwich
 * Description:       The foundation release of the rebuilt Page Builder Sandwich: a settings and About screen, a footprint-free front-end output layer, and one sample content block.
 * Version:           6.30.9
 * Requires at least: 6.8
 * Requires PHP:      8.2
 * Author:            Neil Lock — CEO, Zinn Digital® Ltd
 * Author URI:        https://zinndigital.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       page-builder-sandwich
 * Domain Path:       /languages
 *
 * @package ZinnDigital\PBS
 *
 * ⛔⛔ THIS FILE IS NAMED `class-plugin.php` ON PURPOSE AND MUST NEVER BE RENAMED. Every site running
 * the legacy plugin has `page-builder-sandwich/class-plugin.php` recorded as the active basename; WordPress
 * deactivates a plugin whose basename disappears during an update (plugins.json
 * `legacy_main_file`, CONTRACT §1 / G10).
 *
 * ⛔⛔ AND IT IS WRITTEN IN THE LICENSING SERVICE'S OWN PRINT, NOT IN WPCS STYLE. Freemius
 * re-prints the file that calls fs_dynamic_init() (four-space indent, no blank lines between
 * statements, `!$x`); every other file reaches its free package byte-for-byte. Writing this file
 * in that canonical form is what makes the house free zip and the Freemius free zip identical
 * (docs/adr/0031, docs/adr/0032). Keep it to headers, the SDK init and one require; everything
 * else lives in includes/ and follows WPCS.
 *
 * ⛔ No `Update URI` header and no secret key in this SOURCE, ever. Freemius adds the header to the premium download only (measured, docs/adr/0031). The SDK needs only the PUBLIC key below.
 */
defined( 'ABSPATH' ) || exit;
if ( function_exists( 'pbsw_fs' ) ) {
    pbsw_fs()->set_basename( false, __FILE__ );
    return;
}
define( 'PBSW_VERSION', '6.30.9' );
define( 'PBSW_FILE', __FILE__ );
define( 'PBSW_DIR', plugin_dir_path( __FILE__ ) );
define( 'PBSW_URL', plugin_dir_url( __FILE__ ) );
if ( !function_exists( 'pbsw_fs' ) ) {
    /**
     * The licensing SDK instance for this plugin. The SDK keys a site's connection on plugin 203 and its slug, not on this function's name, which is `pbsw_` because Plugin Check refuses any prefix under four letters (docs/adr/0033).
     *
     * @return Freemius
     */
    function pbsw_fs() {
        global $pbsw_fs;
        if ( !isset( $pbsw_fs ) ) {
            require_once __DIR__ . '/vendor/freemius/start.php';
            $pbsw_fs = fs_dynamic_init( array(
                'id'                             => '203',
                'slug'                           => 'page-builder-sandwich',
                'premium_slug'                   => 'page-builder-sandwich-premium',
                'type'                           => 'plugin',
                'public_key'                     => 'pk_24332b201c316345690967b25da99',
                'bundle_id'                      => '40216',
                'bundle_public_key'              => 'pk_1bcbfe8657c755d37d4b8a4c29f46',
                'bundle_license_auto_activation' => true,
                'is_premium'                     => false,
                'premium_suffix'                 => 'Pro',
                'has_addons'                     => false,
                'has_paid_plans'                 => true,
                'trial'                          => array(
                    'days'               => 14,
                    'is_require_payment' => false,
                ),
                'is_org_compliant'               => true,
                'menu'                           => array(
                    'slug'       => 'page-builder-sandwich',
                    'first-path' => 'admin.php?page=page-builder-sandwich',
                    'contact'    => false,
                    'support'    => false,
                ),
                'is_live'                        => true,
            ) );
        }
        return $pbsw_fs;
    }

    pbsw_fs();
    pbsw_fs()->add_action( 'after_uninstall', 'pbsw_uninstall' );
    do_action( 'pbsw_fs_loaded' );
}
require_once __DIR__ . '/includes/bootstrap.php';