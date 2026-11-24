<?php
/**
 * Tabs that other code adds to the plugin's admin screen.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Extra tabs on the Page Builder Sandwich screen, registered from PHP.
 *
 * An add-on, a theme or a site's own code returns its tabs from the `pbsw_admin_tabs` filter.
 * A tab either prints its own content (a `callback`, called like an add_menu_page() callback) or
 * opens an address (a `url`, for a screen that lives elsewhere). The content is printed by PHP
 * into a hidden holder below the app, so a tab keeps WordPress's own forms, nonces and scripts;
 * the app only moves that node into view while the tab is open (src/admin/tabs.js).
 *
 * A tab whose entry is not valid is left out rather than half drawn: no id, an id the screen
 * already uses, no label, neither a callback nor an address, or a capability the user lacks.
 */
final class Admin_Tabs {

	/**
	 * Route ids the screen already uses (the admin kit's own screens and the plugin's routes).
	 * A tab cannot take one: it would hide the screen it collides with.
	 *
	 * @var list<string>
	 */
	public const RESERVED = array( 'overview', 'settings', 'templates', 'setup', 'plans', 'addons', 'help', 'feedback', 'marketing' );

	/** The option the site's own tabs (Pro) are stored in. */
	public const CUSTOM_OPTION = 'pbsw_custom_tabs';

	/** The id of each tab's hidden content holder starts with this. */
	public const PANEL_PREFIX = 'pbsw-admin-tab-';

	/** Longest label kept, in characters: the label is a button in the screen's tab bar. */
	public const LABEL_MAX = 40;

	/**
	 * The tabs the current user may see, valid ones only, in order.
	 *
	 * @return list<array{id:string, label:string, url:string, callback:callable|null, position:int}>
	 */
	public static function tabs(): array {
		/**
		 * Filters the tabs added to the Page Builder Sandwich admin screen. Each entry is an array:
		 * `id` (lowercase letters, digits, `-` or `_`, starting with a letter), `label`, and either
		 * `callback` (prints the tab's content; escape what it prints) or `url` (an http(s) address
		 * the tab opens). Optional: `capability` (default `manage_options`) and `position` (lower
		 * comes first, default 10). Page Builder Sandwich Pro adds the site's own tabs here.
		 *
		 * @param array<int, array<string, mixed>> $tabs Tabs.
		 */
		$raw = apply_filters( 'pbsw_admin_tabs', array() );
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$tabs  = array();
		$taken = array_fill_keys( self::RESERVED, true );
		$order = 0;
		foreach ( $raw as $entry ) {
			$tab = is_array( $entry ) ? self::normalise( $entry ) : null;
			if ( null === $tab || isset( $taken[ $tab['id'] ] ) ) {
				continue;
			}
			$capability = is_string( $entry['capability'] ?? null ) && '' !== $entry['capability'] ? $entry['capability'] : 'manage_options';
			if ( ! current_user_can( $capability ) ) {
				continue;
			}
			$taken[ $tab['id'] ] = true;
			$tab['order']        = $order++;
			$tabs[]              = $tab;
		}

		// Stable: equal positions keep the order they were added in.
		usort(
			$tabs,
			static fn( array $a, array $b ): int => array( $a['position'], $a['order'] ) <=> array( $b['position'], $b['order'] )
		);

		return array_map(
			static function ( array $tab ): array {
				unset( $tab['order'] );
				return $tab;
			},
			$tabs
		);
	}

	/**
	 * One entry, checked; null when it cannot be drawn.
	 *
	 * @param array<string, mixed> $entry Raw entry.
	 * @return array{id:string, label:string, url:string, callback:callable|null, position:int}|null
	 */
	public static function normalise( array $entry ): ?array {
		$id = is_string( $entry['id'] ?? null ) ? $entry['id'] : '';
		if ( 1 !== preg_match( '/^[a-z][a-z0-9_-]{0,39}$/', $id ) ) {
			return null;
		}
		$label = is_string( $entry['label'] ?? null ) ? trim( sanitize_text_field( $entry['label'] ) ) : '';
		if ( '' === $label ) {
			return null;
		}
		if ( function_exists( 'mb_substr' ) ) {
			$label = mb_substr( $label, 0, self::LABEL_MAX );
		}

		$callback = isset( $entry['callback'] ) && is_callable( $entry['callback'] ) ? $entry['callback'] : null;
		$url      = '';
		if ( null === $callback ) {
			$url = is_string( $entry['url'] ?? null ) ? self::safe_url( $entry['url'] ) : '';
			if ( '' === $url ) {
				return null;
			}
		}

		return array(
			'id'       => $id,
			'label'    => $label,
			'url'      => $url,
			'callback' => $callback,
			'position' => is_numeric( $entry['position'] ?? null ) ? (int) $entry['position'] : 10,
		);
	}

	/**
	 * An http(s) address, or '' (never `javascript:` or `data:`).
	 *
	 * @param string $url Raw address.
	 * @return string
	 */
	public static function safe_url( string $url ): string {
		$url    = trim( $url );
		$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return '';
		}

		return (string) esc_url_raw( $url, array( 'http', 'https' ) );
	}

	/**
	 * What the app needs to draw the tabs (no callbacks: they run in print_panels()).
	 *
	 * @param list<array{id:string, label:string, url:string, callback:callable|null, position:int}>|null $tabs Tabs, or null for self::tabs().
	 * @return list<array{id:string, label:string, url:string}>
	 */
	public static function boot_data( ?array $tabs = null ): array {
		return array_map(
			static fn( array $tab ): array => array(
				'id'    => $tab['id'],
				'label' => $tab['label'],
				'url'   => $tab['url'],
			),
			$tabs ?? self::tabs()
		);
	}

	/**
	 * Print each content tab into its hidden holder. A callback that throws prints a short
	 * notice in its own tab instead of breaking the screen.
	 *
	 * @param list<array{id:string, label:string, url:string, callback:callable|null, position:int}>|null $tabs Tabs, or null for self::tabs().
	 * @return void
	 */
	public static function print_panels( ?array $tabs = null ): void {
		$tabs = array_filter( $tabs ?? self::tabs(), static fn( array $tab ): bool => null !== $tab['callback'] );
		if ( array() === $tabs ) {
			return;
		}
		echo '<div class="pbsw-admin-tab-holder" hidden>';
		foreach ( $tabs as $tab ) {
			echo '<div class="pbsw-admin-tab-panel" id="' . esc_attr( self::PANEL_PREFIX . $tab['id'] ) . '">';
			try {
				call_user_func( $tab['callback'] );
			} catch ( \Throwable $error ) {
				unset( $error );
				echo '<div class="notice notice-error inline"><p>' . esc_html__( 'This tab could not be shown: the code that adds it stopped with an error.', 'page-builder-sandwich' ) . '</p></div>';
			}
			echo '</div>';
		}
		echo '</div>';
	}
}
