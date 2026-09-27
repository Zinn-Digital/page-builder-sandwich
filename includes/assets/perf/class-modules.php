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
 * printed INLINE (inline()) — never from the plugin path, and not as a `data:` URL: WordPress's HTML
 * API passes a script `src` through esc_url(), which drops the `data:` scheme (measured on 7.1.2).
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
	 * Modules to print inline (uploads not writable), id → source.
	 *
	 * @var array<string, string>
	 */
	private static array $inline = array();

	/**
	 * The runtime's URL as WordPress prints it (pre-7.0 inline path).
	 *
	 * @var string|null
	 */
	private static ?string $runtime_src = null;

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
		$src = Assets::published_url( $key ) ?? ( Files::write( $body, 'js' )['url'] ?? null );
		if ( null === $src ) {
			self::inline( $id, $body );
			return $id;
		}
		wp_register_script_module( $id, $src, array( self::RUNTIME ), null );
		wp_enqueue_script_module( $id );

		return $id;
	}

	/**
	 * Uploads cannot be written: print the module INLINE (`<script type="module">`), after the
	 * import map, so the block still becomes interactive and nothing points at the plugin path.
	 *
	 * The inline module imports `@wordpress/interactivity` by its bare specifier, so the runtime
	 * must be in WordPress's import map. WordPress 7.0+ adds the `module_dependencies` of a CLASSIC
	 * script to the import map, so a src-less classic handle declares that dependency. Before 7.0
	 * the import map only lists dependencies of enqueued MODULES, so there the runtime itself is
	 * enqueued and the specifier is rewritten to the exact URL WordPress prints for it (the same
	 * URL string, so the same module instance as every other importer).
	 *
	 * @param string $id   Neutral module id.
	 * @param string $body Module source (prefix applied).
	 * @return void
	 */
	private static function inline( string $id, string $body ): void {
		if ( isset( self::$inline[ $id ] ) ) {
			return;
		}
		self::$inline[ $id ] = $body;
		if ( self::classic_module_dependencies() ) {
			wp_register_script( $id . '-deps', false, array(), null, array( 'in_footer' => true ) ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- no file.
			wp_script_add_data( $id . '-deps', 'module_dependencies', array( self::RUNTIME ) );
			wp_enqueue_script( $id . '-deps' );
		} else {
			wp_enqueue_script_module( self::RUNTIME );
			add_filter( 'script_module_loader_src', array( self::class, 'capture_runtime_src' ), 10, 2 );
		}
		if ( ! has_action( 'wp_footer', array( self::class, 'print_inline' ) ) ) {
			// After the import map (printed in wp_head by a block theme, in wp_footer by a classic one).
			add_action( 'wp_footer', array( self::class, 'print_inline' ), 1000 );
		}
	}

	/**
	 * Does this WordPress put a classic script's `module_dependencies` into the import map (7.0+)?
	 *
	 * @return bool
	 */
	public static function classic_module_dependencies(): bool {
		return version_compare( (string) get_bloginfo( 'version' ), '7.0-alpha', '>=' );
	}

	/**
	 * `script_module_loader_src`: remember the runtime's printed URL (pre-7.0 path).
	 *
	 * @param string|mixed $src Source.
	 * @param string|mixed $id  Module id.
	 * @return string|mixed
	 */
	public static function capture_runtime_src( $src, $id ) {
		if ( self::RUNTIME === $id && is_string( $src ) && '' !== $src ) {
			self::$runtime_src = $src;
		}

		return $src;
	}

	/**
	 * `wp_footer` (1000): print every inline module.
	 *
	 * @return void
	 */
	public static function print_inline(): void {
		foreach ( self::$inline as $id => $body ) {
			if ( ! self::classic_module_dependencies() && null !== self::$runtime_src ) {
				$body = self::rewrite_runtime( $body, self::$runtime_src );
			}
			wp_print_inline_script_tag(
				$body,
				array(
					'type' => 'module',
					'id'   => $id . '-js-module',
				)
			);
		}
		self::$inline = array();
	}

	/**
	 * Point `from '@wordpress/interactivity'` imports at a URL (the pure half of the pre-7.0 path).
	 *
	 * @param string $body Module source.
	 * @param string $url  Runtime URL.
	 * @return string
	 */
	public static function rewrite_runtime( string $body, string $url ): string {
		return (string) preg_replace( '/(\bfrom\s*|\bimport\s*\(\s*|\bimport\s+)([\'"])@wordpress\/interactivity\2/', '$1$2' . addcslashes( $url, '\\$' ) . '$2', $body );
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
