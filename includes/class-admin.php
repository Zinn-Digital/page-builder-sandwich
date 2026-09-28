<?php
/**
 * The wp-admin screen.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The branded admin screen: settings, beta updates, and About/credits.
 *
 * The screen is a React app (`src/admin/`, built by @wordpress/scripts into `build/`). Its
 * strings are translated through `wp_set_script_translations()` from the JSON files that
 * `wp i18n make-json` writes into `languages/`.
 */
final class Admin {

	/** Menu slug, shared with the licensing SDK's menu integration. */
	public const SLUG = 'page-builder-sandwich';

	/** Script handle. */
	public const HANDLE = 'pbsw-admin';

	/**
	 * The page hook suffix returned by add_menu_page().
	 *
	 * @var string
	 */
	private static string $hook = '';

	/**
	 * Hook the menu and assets.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
	}

	/**
	 * Add the top-level menu.
	 *
	 * @return void
	 */
	public static function menu(): void {
		self::$hook = (string) add_menu_page(
			__( 'Page Builder Sandwich', 'page-builder-sandwich' ),
			__( 'Page Builder Sandwich', 'page-builder-sandwich' ),
			'manage_options',
			self::SLUG,
			array( self::class, 'render' ),
			'dashicons-layout',
			58
		);
	}

	/**
	 * Print the mount point.
	 *
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'page-builder-sandwich' ), 403 );
		}

		echo '<div class="wrap"><h1>' . esc_html__( 'Page Builder Sandwich', 'page-builder-sandwich' ) . '</h1>';
		if ( ! is_readable( PBSW_DIR . 'build/settings.asset.php' ) ) {
			// A source checkout that was never built. Say so rather than show an empty page.
			echo '<div class="notice notice-error"><p>' . esc_html__( 'The admin screen\'s scripts are missing from this copy of the plugin. Reinstall it from a released package.', 'page-builder-sandwich' ) . '</p></div>';
		}
		echo '<div id="pbs-admin-root"></div>';
		echo '<noscript><p>' . esc_html__( 'This screen needs JavaScript.', 'page-builder-sandwich' ) . '</p></noscript></div>';
	}

	/**
	 * Enqueue the app on this screen only.
	 *
	 * @param string $hook_suffix The current admin page.
	 * @return void
	 */
	public static function enqueue( string $hook_suffix ): void {
		if ( '' === self::$hook || $hook_suffix !== self::$hook ) {
			return;
		}
		$asset_file = PBSW_DIR . 'build/settings.asset.php';
		if ( ! is_readable( $asset_file ) ) {
			return;
		}
		$asset = require $asset_file;

		wp_enqueue_script(
			self::HANDLE,
			PBSW_URL . 'build/settings.js',
			(array) ( $asset['dependencies'] ?? array() ),
			(string) ( $asset['version'] ?? PBSW_VERSION ),
			true
		);
		wp_set_script_translations( self::HANDLE, 'page-builder-sandwich', PBSW_DIR . 'languages' );
		wp_add_inline_script( self::HANDLE, 'window.pbsAdmin = ' . wp_json_encode( self::data() ) . ';', 'before' );

		/**
		 * Fires after the admin screen's script is enqueued, so a module can enqueue a script that
		 * adds a panel through the `pbs.admin.panels` JavaScript filter (it must depend on this
		 * handle; the screen renders on DOM ready, after every footer script has run).
		 *
		 * @param string $handle The admin screen's script handle.
		 */
		do_action( 'pbsw_admin_enqueue', self::HANDLE );

		if ( is_readable( PBSW_DIR . 'build/settings.css' ) ) {
			wp_enqueue_style( self::HANDLE, PBSW_URL . 'build/settings.css', array( 'wp-components' ), (string) ( $asset['version'] ?? PBSW_VERSION ) );
			wp_style_add_data( self::HANDLE, 'rtl', 'replace' );
		}
	}

	/**
	 * Data the app boots with.
	 *
	 * @return array<string, mixed>
	 */
	public static function data(): array {
		/**
		 * Filters the data the admin app boots with. The premium layer uses it to report its
		 * edition; nothing sensitive belongs here, because it is printed into the page.
		 *
		 * @param array<string, mixed> $data Boot data.
		 */
		return (array) apply_filters(
			'pbsw_admin_data',
			array(
				'version'    => PBSW_VERSION,
				'edition'    => 'free',
				'restPath'   => '/' . Rest::NAMESPACE . '/settings',
				'upgradeUrl' => Licensing::upgrade_url(),
				'productUrl' => 'https://pagebuildersandwich.com',
				'companyUrl' => 'https://zinndigital.com',
			)
		);
	}
}
