<?php
/**
 * Front-end interactivity: Interactivity API view modules through the neutral path (pbs-p3).
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Assets\Perf;

use ZinnDigital\PBS\Assets;
use ZinnDigital\PBS\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The base every interactive block (tabs, accordions, sliders — lane L09) plugs into.
 *
 * ⛔ No front-end script from this plugin may depend on jQuery. Interactive blocks use
 * WordPress's Interactivity API: a view SCRIPT MODULE that imports `@wordpress/interactivity`,
 * plus `data-wp-*` directives rendered server-side, so the block works before any script runs.
 *
 * ⛔ And block.json's `viewScriptModule` cannot be used, for the same reason `style`/`viewScript`
 * are banned (docs/adr/0033): WordPress would print the module from the plugin directory
 * (`/wp-content/plugins/page-builder-sandwich/…`) with an id derived from the block name
 * (`pbs-…-view-script-module`). So a block registers its module here instead:
 *
 *     Modules::add( 'tabs', 'assets/modules/tabs.js' );      // at load
 *     Modules::enqueue( 'tabs' );                            // in the block's render callback
 *     '<div data-wp-interactive="' . Modules::store() . '">' // the store namespace
 *
 * The module file is published like every front-end asset — `__PREFIX__` replaced, content
 * hashed, copied to `uploads/<prefix>-assets/` — and registered with a neutral id
 * (`<prefix>-<hash>`, printed as `<prefix>-<hash>-js-module`). Its static
 * `import … from '@wordpress/interactivity'` resolves through WordPress's own import map, which
 * maps that bare specifier to wp-includes; so NO import-map override is needed, and none of our
 * paths ever appears in it (only a dependency's id is printed there, never our module's).
 *
 * When the published copy is missing (a source added after the last publish), the module is written
 * to the same neutral folder on first use. When uploads cannot be written at all, the module is
 * loaded from the plugin folder with the prefix in its URL (Assets::plugin_url(); each module reads
 * `pfx` from `import.meta.url` when its token was not replaced). It is never printed inline:
 * plugin code in an inline script is an unescaped sink (WordPress.org review P0TDX382477HGN).
 */
final class Modules {

	/** The Interactivity API runtime's module id. */
	public const RUNTIME = '@wordpress/interactivity';

	/**
	 * Registered module keys → published source key.
	 *
	 * @var array<string, string>
	 */
	private static array $modules = array();

	/**
	 * Hooks (none yet; kept for symmetry and so later hooks have a home).
	 *
	 * @return void
	 */
	public static function register(): void {
	}

	/**
	 * Register a view module (a file inside this plugin, `__PREFIX__` tokens allowed).
	 *
	 * @param string $name     Module name, e.g. `tabs`.
	 * @param string $relative Path relative to the plugin directory.
	 * @return void
	 */
	public static function add( string $name, string $relative ): void {
		$key                    = 'module/' . sanitize_key( $name ) . '.js';
		self::$modules[ $name ] = $key;
		Assets::add_source( $key, $relative );
	}

	/**
	 * The Interactivity store namespace for this site: the neutral prefix.
	 *
	 * @return string
	 */
	public static function store(): string {
		return Settings::prefix();
	}

	/**
	 * Enqueue a registered view module. Returns its neutral id, or null when unknown.
	 *
	 * @param string $name Module name.
	 * @return string|null
	 */
	public static function enqueue( string $name ): ?string {
		$key = self::$modules[ $name ] ?? null;
		if ( null === $key || ! function_exists( 'wp_enqueue_script_module' ) ) {
			return null;
		}
		$body = Assets::source( $key, Settings::prefix() );
		if ( null === $body ) {
			return null;
		}
		$id  = self::id( $body, Settings::prefix() );
		$src = Assets::published_url( $key ) ?? ( Files::write( $body, 'js' )['url'] ?? null ) ?? Assets::plugin_url( $key );
		if ( null === $src ) {
			return null;
		}
		wp_register_script_module( $id, $src, array( self::RUNTIME ), null );
		wp_enqueue_script_module( $id );

		return $id;
	}

	/**
	 * Neutral module id: prefix + content hash.
	 *
	 * @param string $body   Module source.
	 * @param string $prefix Prefix.
	 * @return string
	 */
	public static function id( string $body, string $prefix ): string {
		return $prefix . '-' . substr( sha1( $body ), 0, 8 );
	}

	/**
	 * Every front-end classic script this plugin enqueued on the current request, with the
	 * recursive dependencies of each — the "zero jQuery" check reads this.
	 *
	 * @param \WP_Scripts        $scripts Scripts registry.
	 * @param array<int, string> $handles Handles to expand.
	 * @return array<int, string> Handles, dependencies included.
	 */
	public static function expand_dependencies( \WP_Scripts $scripts, array $handles ): array {
		$seen  = array();
		$stack = $handles;
		while ( array() !== $stack ) {
			$handle = (string) array_pop( $stack );
			if ( isset( $seen[ $handle ] ) ) {
				continue;
			}
			$seen[ $handle ] = true;
			$dep             = $scripts->registered[ $handle ] ?? null;
			foreach ( $dep instanceof \_WP_Dependency ? $dep->deps : array() as $child ) {
				$stack[] = (string) $child;
			}
		}

		return array_keys( $seen );
	}
}
