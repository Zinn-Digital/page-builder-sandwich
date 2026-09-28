<?php
/**
 * The Site design screen: a sidebar in the block editor and in Sandwich Studio, and a page under
 * Appearance. wp-admin only; nothing here reaches the front end.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Design\Globals;

use ZinnDigital\PBS\Rest;
use ZinnDigital\PBS\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueues the Site design bundles and adds the admin page.
 *
 * Two bundles share one component tree (`src/design/site-design/`): `site-design` registers the
 * editor sidebar (block editor and Studio), `site-design-page` mounts the same panel on the
 * admin page. The premium layer adds its sections through the `pbsw.siteDesign.sections` JS
 * filter, from a script it makes a dependency of both (`pbsw_site_design_deps`).
 */
final class Site_Design {

	/** Admin page slug. */
	public const PAGE = 'pbs-site-design';

	/** Editor script handle. */
	public const EDITOR_HANDLE = 'pbsw-site-design';

	/** Admin page script handle. */
	public const ADMIN_HANDLE = 'pbsw-site-design-admin';

	/**
	 * The admin page hook suffix.
	 *
	 * @var string
	 */
	private static string $hook = '';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ), 20 );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_admin' ) );
		add_action( 'enqueue_block_editor_assets', array( self::class, 'enqueue_editor' ) );
	}

	/**
	 * The page under Appearance.
	 *
	 * @return void
	 */
	public static function menu(): void {
		// Under Appearance, next to the theme's own design screens (and where the licensing
		// SDK, which manages the plugin's own menu before a customer opts in, cannot hide it).
		self::$hook = (string) add_theme_page(
			__( 'Site design', 'page-builder-sandwich' ),
			__( 'Site design', 'page-builder-sandwich' ),
			'edit_theme_options',
			self::PAGE,
			array( self::class, 'render' )
		);
	}

	/**
	 * The admin page's mount point.
	 *
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'page-builder-sandwich' ), 403 );
		}
		echo '<div class="wrap"><h1>' . esc_html__( 'Site design', 'page-builder-sandwich' ) . '</h1>';
		echo '<p>' . esc_html__( 'Colours, fonts and styles shared by every page of the site and by your theme.', 'page-builder-sandwich' ) . '</p>';
		if ( ! is_readable( PBSW_DIR . 'build/site-design-page.asset.php' ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'The admin screen\'s scripts are missing from this copy of the plugin. Reinstall it from a released package.', 'page-builder-sandwich' ) . '</p></div>';
		}
		echo '<div id="pbsw-site-design-root"></div>';
		echo '<noscript><p>' . esc_html__( 'This screen needs JavaScript.', 'page-builder-sandwich' ) . '</p></noscript></div>';
	}

	/**
	 * Enqueue the admin page bundle, on that page only.
	 *
	 * @param string $hook_suffix Current admin page.
	 * @return void
	 */
	public static function enqueue_admin( string $hook_suffix ): void {
		if ( '' === self::$hook || $hook_suffix !== self::$hook ) {
			return;
		}
		wp_enqueue_media();
		self::enqueue( self::ADMIN_HANDLE, 'site-design-page' );
	}

	/**
	 * Enqueue the editor bundle in the block editor and in Studio (which fires the same action).
	 *
	 * @return void
	 */
	public static function enqueue_editor(): void {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return; // The sidebar edits site-wide settings; an author who cannot does not get it.
		}
		self::enqueue( self::EDITOR_HANDLE, 'site-design' );
	}

	/**
	 * Enqueue one bundle with its boot data and translations.
	 *
	 * @param string $handle Handle.
	 * @param string $entry  Build entry name.
	 * @return void
	 */
	private static function enqueue( string $handle, string $entry ): void {
		$asset_file = PBSW_DIR . 'build/' . $entry . '.asset.php';
		if ( ! is_readable( $asset_file ) ) {
			return;
		}
		$asset = require $asset_file;
		/**
		 * Filters the dependencies of the Site design bundles. The premium layer adds its own
		 * script here, so its sections are registered before the panel first renders.
		 *
		 * @param array<int, string> $deps   Dependencies.
		 * @param string             $handle The bundle's handle.
		 */
		$deps = (array) apply_filters( 'pbsw_site_design_deps', (array) ( $asset['dependencies'] ?? array() ), $handle );

		wp_enqueue_script( $handle, PBSW_URL . 'build/' . $entry . '.js', $deps, (string) ( $asset['version'] ?? PBSW_VERSION ), true );
		wp_set_script_translations( $handle, 'page-builder-sandwich', PBSW_DIR . 'languages' );
		wp_add_inline_script( $handle, 'window.pbswSiteDesign = ' . wp_json_encode( self::data(), JSON_HEX_TAG | JSON_UNESCAPED_SLASHES ) . ';', 'before' );
		if ( is_readable( PBSW_DIR . 'build/' . $entry . '.css' ) ) {
			wp_enqueue_style( $handle, PBSW_URL . 'build/' . $entry . '.css', array( 'wp-components' ), (string) ( $asset['version'] ?? PBSW_VERSION ) );
			wp_style_add_data( $handle, 'rtl', 'replace' );
		}
	}

	/**
	 * Boot data (printed into wp-admin; nothing secret).
	 *
	 * @return array<string, mixed>
	 */
	public static function data(): array {
		/**
		 * Filters the Site design boot data. The premium layer reports its edition and adds the
		 * data its sections need.
		 *
		 * @param array<string, mixed> $data Boot data.
		 */
		return (array) apply_filters(
			'pbsw_site_design_data',
			array(
				'edition'      => 'free',
				'namespace'    => Rest::NAMESPACE,
				'base'         => Design_Rest::BASE,
				'prefix'       => Settings::prefix(),
				'canWriteCss'  => Design_Rest::can_write_css(),
				'upgradeUrl'   => \ZinnDigital\PBS\Licensing::upgrade_url(),
				'siteEditUrl'  => wp_is_block_theme() ? admin_url( 'site-editor.php?p=%2Fstyles' ) : '',
				'adminPageUrl' => admin_url( 'themes.php?page=' . self::PAGE ),
			)
		);
	}
}
