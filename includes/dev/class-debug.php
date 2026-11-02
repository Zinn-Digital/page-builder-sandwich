<?php
/**
 * Debug mode (row 71a; rival: Beaver Builder Pro 2.11.2): every block this plugin and its
 * add-ons registered, with its VERSION and whether something else OVERRIDES it.
 *
 * "Overridden" is measured, never guessed: a block is overridden when the code that renders it is
 * not the code that registered it — its render callback, or a callback on its own
 * `render_block_{name}` filter, is defined in a file outside the module's own folder. Each
 * override names where it comes from (a plugin's folder, the theme, a must-use plugin) so a
 * support ticket can say "the theme replaces Accordion" instead of "the accordion looks wrong".
 *
 * Shown on the plugin's settings screen only while debug mode is on (an option, off by default);
 * read and switched by administrators only.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Dev;

use ZinnDigital\PBS\Rest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * `GET|POST pbs/v1/debug`.
 */
final class Debug {

	/** The option that holds the switch. */
	public const OPTION = 'pbsw_debug_mode';

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
		add_filter( 'pbsw_admin_data', array( self::class, 'admin_data' ) );
	}

	/**
	 * The settings screen learns where debug mode lives.
	 *
	 * @param mixed $data Boot data.
	 * @return array<string, mixed>
	 */
	public static function admin_data( $data ): array {
		$data              = is_array( $data ) ? $data : array();
		$data['debugPath'] = '/' . Rest::NAMESPACE . '/debug';

		return $data;
	}

	/**
	 * REST routes.
	 *
	 * @return void
	 */
	public static function routes(): void {
		register_rest_route(
			Rest::NAMESPACE,
			'/debug',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => static fn() => new \WP_REST_Response( self::report() ),
					'permission_callback' => static fn(): bool => current_user_can( 'manage_options' ),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( self::class, 'save' ),
					'permission_callback' => static fn(): bool => current_user_can( 'manage_options' ),
					'args'                => array(
						'enabled' => array(
							'type'     => 'boolean',
							'required' => true,
						),
					),
				),
			)
		);
	}

	/**
	 * Switch debug mode on or off.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function save( \WP_REST_Request $request ): \WP_REST_Response {
		update_option( self::OPTION, (bool) $request->get_param( 'enabled' ) ? 1 : 0, false );

		return new \WP_REST_Response( self::report() );
	}

	/**
	 * Whether debug mode is on.
	 *
	 * @return bool
	 */
	public static function enabled(): bool {
		return (bool) get_option( self::OPTION, false );
	}

	/**
	 * The switch, and the module list while it is on.
	 *
	 * @return array{enabled: bool, modules: array<int, array<string, mixed>>}
	 */
	public static function report(): array {
		$enabled = self::enabled();

		return array(
			'enabled' => $enabled,
			'modules' => $enabled ? self::modules() : array(),
		);
	}

	/**
	 * Every block of this plugin (`pbs/*`) and of its add-ons (pbsw_register_block), sorted by
	 * name: name, title, where it comes from, its version, and what overrides it.
	 *
	 * @return array<int, array{name: string, title: string, source: string, version: string, overridden: bool, overrides: array<int, array{hook: string, by: string}>}>
	 */
	public static function modules(): array {
		$addons = array();
		foreach ( Registry::of( 'blocks' ) as $name => $def ) {
			$addons[ (string) $name ] = $def;
		}
		$out = array();
		foreach ( \WP_Block_Type_Registry::get_instance()->get_all_registered() as $name => $type ) {
			$name  = (string) $name;
			$own   = str_starts_with( $name, 'pbs/' );
			$addon = $addons[ $name ] ?? null;
			if ( ! $own && null === $addon ) {
				continue;
			}
			if ( $own ) {
				$file   = self::block_file( $name );
				$folder = PBSW_DIR;
				$source = '' !== $file && PBSW_DIR . 'blocks/' . substr( $name, 4 ) . '/block.json' !== $file ? 'pro' : 'free';
				$meta   = self::block_meta( $file );
				$ver    = '' !== (string) ( $meta['version'] ?? '' ) ? (string) $meta['version'] : PBSW_VERSION;
			} else {
				$folder = self::root( (string) ( $addon['dir'] ?? '' ) );
				$source = 'addon';
				$ver    = (string) ( $addon['version'] ?? '' );
				$ver    = '' !== $ver ? $ver : self::plugin_version( $folder );
			}
			$overrides = self::overrides( $name, $type, $folder );
			$out[]     = array(
				'name'       => $name,
				'title'      => (string) ( $type->title ?? '' ) !== '' ? (string) $type->title : $name,
				'source'     => $source,
				'plugin'     => null === $addon ? '' : (string) ( $addon['plugin'] ?? '' ),
				'version'    => $ver,
				'overridden' => array() !== $overrides,
				'overrides'  => $overrides,
			);
		}
		usort( $out, static fn( array $a, array $b ): int => strcmp( $a['name'], $b['name'] ) );

		return $out;
	}

	/**
	 * What overrides a block: its render callback, or a callback on its own render filter,
	 * defined in a file outside the module's own folder. A module with no known folder (an add-on
	 * block defined in PHP) is judged against nothing and so never reported.
	 *
	 * @param string         $name   Block name.
	 * @param \WP_Block_Type $type   Block type.
	 * @param string         $folder The module's own folder.
	 * @return array<int, array{hook: string, by: string}>
	 */
	public static function overrides( string $name, \WP_Block_Type $type, string $folder ): array {
		if ( '' === $folder ) {
			return array();
		}
		$out  = array();
		$file = null === $type->render_callback ? '' : self::file_of( $type->render_callback );
		if ( '' !== $file && ! self::inside( $file, $folder ) ) {
			$out[] = array(
				'hook' => 'render_callback',
				'by'   => self::origin( $file ),
			);
		}
		$hook = 'render_block_' . $name;
		global $wp_filter;
		if ( isset( $wp_filter[ $hook ] ) && $wp_filter[ $hook ] instanceof \WP_Hook ) {
			foreach ( $wp_filter[ $hook ]->callbacks as $callbacks ) {
				foreach ( (array) $callbacks as $cb ) {
					$file = self::file_of( $cb['function'] ?? null );
					if ( '' !== $file && ! self::inside( $file, $folder ) ) {
						$out[] = array(
							'hook' => $hook,
							'by'   => self::origin( $file ),
						);
					}
				}
			}
		}

		return $out;
	}

	/**
	 * The file a callback is defined in, or empty when it cannot be told (an internal function).
	 *
	 * @param mixed $callback Callback.
	 * @return string
	 */
	public static function file_of( $callback ): string {
		try {
			if ( is_string( $callback ) && str_contains( $callback, '::' ) ) {
				$callback = explode( '::', $callback, 2 );
			}
			if ( is_array( $callback ) && 2 === count( $callback ) ) {
				$ref = new \ReflectionMethod( $callback[0], (string) $callback[1] );
			} elseif ( $callback instanceof \Closure || is_string( $callback ) ) {
				$ref = new \ReflectionFunction( $callback );
			} elseif ( is_object( $callback ) && method_exists( $callback, '__invoke' ) ) {
				$ref = new \ReflectionMethod( $callback, '__invoke' );
			} else {
				return '';
			}
		} catch ( \ReflectionException $e ) {
			return '';
		}
		$file = $ref->getFileName();

		return false === $file ? '' : wp_normalize_path( $file );
	}

	/**
	 * The folder a module's code lives in: for a block inside a plugin, the whole plugin's
	 * folder (an add-on's render callback is usually in its main file, not beside block.json).
	 *
	 * @param string $dir The block's folder.
	 * @return string
	 */
	public static function root( string $dir ): string {
		if ( '' === $dir ) {
			return '';
		}
		$dir     = wp_normalize_path( $dir );
		$plugins = defined( 'WP_PLUGIN_DIR' ) ? trailingslashit( wp_normalize_path( WP_PLUGIN_DIR ) ) : '';
		if ( '' !== $plugins && str_starts_with( $dir, $plugins ) ) {
			return $plugins . strtok( substr( $dir, strlen( $plugins ) ), '/' );
		}

		return $dir;
	}

	/**
	 * The version in the header of the plugin whose folder this is, or empty.
	 *
	 * @param string $folder Plugin folder.
	 * @return string
	 */
	private static function plugin_version( string $folder ): string {
		if ( '' === $folder ) {
			return '';
		}
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$slug = basename( $folder );
		foreach ( get_plugins() as $file => $data ) {
			if ( dirname( (string) $file ) === $slug ) {
				return (string) ( $data['Version'] ?? '' );
			}
		}

		return '';
	}

	/**
	 * Whether a file is inside a folder.
	 *
	 * @param string $file   File.
	 * @param string $folder Folder.
	 * @return bool
	 */
	private static function inside( string $file, string $folder ): bool {
		return str_starts_with( wp_normalize_path( $file ), trailingslashit( wp_normalize_path( $folder ) ) );
	}

	/**
	 * Where a file comes from, in words a site owner can act on: `plugin: <folder>`, `theme:
	 * <folder>`, `must-use plugin: <file>`, or the path below the site's content folder. Never
	 * the server's absolute path.
	 *
	 * @param string $file File.
	 * @return string
	 */
	public static function origin( string $file ): string {
		$file  = wp_normalize_path( $file );
		$roots = array(
			'plugin'          => defined( 'WP_PLUGIN_DIR' ) ? WP_PLUGIN_DIR : '',
			'must-use plugin' => defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR : '',
			'theme'           => function_exists( 'get_theme_root' ) ? get_theme_root() : '',
		);
		foreach ( $roots as $kind => $root ) {
			$root = '' === $root ? '' : trailingslashit( wp_normalize_path( (string) $root ) );
			if ( '' !== $root && str_starts_with( $file, $root ) ) {
				$rest = substr( $file, strlen( $root ) );
				return $kind . ': ' . ( 'must-use plugin' === $kind ? $rest : strtok( $rest, '/' ) );
			}
		}
		$content = defined( 'WP_CONTENT_DIR' ) ? trailingslashit( wp_normalize_path( WP_CONTENT_DIR ) ) : '';
		if ( '' !== $content && str_starts_with( $file, $content ) ) {
			return 'wp-content/' . substr( $file, strlen( $content ) );
		}

		return basename( $file );
	}

	/**
	 * A `pbs/*` block's own block.json: in `blocks/<slug>/` for a free block, one folder deeper
	 * (the edition's segment) for a block of the paid edition.
	 *
	 * @param string $name Block name.
	 * @return string Empty when the block has no block.json in this plugin.
	 */
	private static function block_file( string $name ): string {
		$slug = substr( $name, 4 );
		$free = PBSW_DIR . 'blocks/' . $slug . '/block.json';
		if ( is_readable( $free ) ) {
			return $free;
		}
		$found = glob( PBSW_DIR . 'blocks/*/' . $slug . '/block.json' );

		return is_array( $found ) && isset( $found[0] ) ? (string) $found[0] : '';
	}

	/**
	 * A block.json, decoded.
	 *
	 * @param string $file File.
	 * @return array<string, mixed>
	 */
	private static function block_meta( string $file ): array {
		if ( '' === $file || ! is_readable( $file ) ) {
			return array();
		}
		$meta = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- the plugin's own block.json.

		return is_array( $meta ) ? $meta : array();
	}
}
