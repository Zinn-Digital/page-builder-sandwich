<?php
/**
 * The developer platform (P16, free, pbs-dev1): what add-ons register with Page Builder Sandwich.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Dev;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One registry for everything an add-on adds: blocks, dynamic data sources, display conditions and
 * form actions. The public functions in api.php (`pbsw_register_*`) are the documented way in; this
 * class validates, keeps the list (for `pbsw_get_extensions()`, the `pbs/list-extensions` ability
 * and the developer screen) and wires each kind into the module that uses it, through that module's
 * own filters. Dynamic sources, conditions and form actions take effect where the Pro modules run
 * (dynamic data, the theme builder and element conditions, the form builder); registering them on a
 * free site is harmless and they switch on with a licence.
 *
 * Names are namespaced like block names (`acme/stock-level`), so two add-ons never collide with each
 * other or with ours.
 */
final class Registry {

	/** A valid extension name. */
	public const NAME = '/^[a-z0-9][a-z0-9-]*\/[a-z0-9][a-z0-9-]*$/';

	/**
	 * Registered extensions by kind and name.
	 *
	 * @var array<string, array<string, array<string, mixed>>>
	 */
	private static array $items = array(
		'blocks'     => array(),
		'sources'    => array(),
		'conditions' => array(),
		'actions'    => array(),
		'controls'   => array(),
	);

	/**
	 * Hook the registry into the modules that use it.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'pbsw_dynamic_sources', array( self::class, 'source_labels' ) );
		add_filter( 'pbsw_dynamic_value', array( self::class, 'source_value' ), 10, 4 );
		add_filter( 'pbsw_dynamic_editor_sources', array( self::class, 'editor_sources' ) );
		add_filter( 'pbsw_design_props', array( self::class, 'design_props' ) );
		add_action( 'enqueue_block_editor_assets', array( self::class, 'enqueue' ) );
	}

	/**
	 * `pbsw_design_props`: registered style controls join the design engine (after ours).
	 *
	 * @param array<string, \ZinnDigital\PBS\Design\Prop> $props Props.
	 * @return array<string, \ZinnDigital\PBS\Design\Prop>
	 */
	public static function design_props( $props ): array {
		$props = (array) $props;
		foreach ( self::$items['controls'] as $def ) {
			if ( ! isset( $props[ $def['key'] ] ) ) {
				$props[ $def['key'] ] = new Style_Control( $def );
			}
		}

		return $props;
	}

	/**
	 * The editor half of the style controls: their definitions, for build/dev.js to register in
	 * the editor's design registry (so an add-on writes no JavaScript for a control).
	 *
	 * @return void
	 */
	public static function enqueue(): void {
		$asset_file = PBSW_DIR . 'build/dev.asset.php';
		if ( array() === self::$items['controls'] || ! is_readable( $asset_file ) ) {
			return;
		}
		$asset    = require $asset_file;
		$controls = array();
		foreach ( self::$items['controls'] as $def ) {
			$controls[] = array_intersect_key( $def, array_flip( array( 'key', 'kind', 'property', 'group', 'choices', 'options', 'blocks', 'label' ) ) );
		}
		wp_enqueue_script( 'pbsw-dev', PBSW_URL . 'build/dev.js', (array) ( $asset['dependencies'] ?? array() ), (string) ( $asset['version'] ?? PBSW_VERSION ), true );
		wp_add_inline_script( 'pbsw-dev', 'window.pbswStyleControls = ' . wp_json_encode( $controls ) . ';', 'before' );
	}

	/**
	 * Is this name valid and free in that kind?
	 *
	 * @param string $kind Kind.
	 * @param string $name Name.
	 * @return true|\WP_Error
	 */
	private static function claim( string $kind, string $name ) {
		if ( 1 !== preg_match( self::NAME, $name ) ) {
			return new \WP_Error( 'pbsw_dev_name', sprintf( 'Page Builder Sandwich: "%s" is not a valid name. Use "vendor/name" in lowercase letters, digits and hyphens.', $name ) );
		}
		if ( str_starts_with( $name, 'pbs/' ) || str_starts_with( $name, 'pbsw/' ) ) {
			return new \WP_Error( 'pbsw_dev_reserved', sprintf( 'Page Builder Sandwich: "%s" uses a reserved namespace (pbs/, pbsw/).', $name ) );
		}
		if ( isset( self::$items[ $kind ][ $name ] ) ) {
			return new \WP_Error( 'pbsw_dev_taken', sprintf( 'Page Builder Sandwich: "%s" is already registered.', $name ) );
		}

		return true;
	}

	/**
	 * Register something; a mistake is reported to the developer (`_doing_it_wrong`), never fatal.
	 *
	 * @param string               $kind Kind (blocks, sources, conditions, actions).
	 * @param string               $name Name.
	 * @param array<string, mixed> $def  Definition.
	 * @param array<int, string>   $need Keys that must be given.
	 * @return bool
	 */
	public static function add( string $kind, string $name, array $def, array $need ): bool {
		$ok = self::claim( $kind, $name );
		if ( true === $ok ) {
			foreach ( $need as $key ) {
				if ( 'callback' === $key ? ! is_callable( $def['callback'] ?? null ) : '' === trim( (string) ( $def[ $key ] ?? '' ) ) ) {
					$ok = new \WP_Error( 'pbsw_dev_missing', sprintf( 'Page Builder Sandwich: "%1$s" needs "%2$s".', $name, $key ) );
					break;
				}
			}
		}
		if ( is_wp_error( $ok ) ) {
			_doing_it_wrong( esc_html( 'pbsw_register_' . rtrim( $kind, 's' ) ), esc_html( $ok->get_error_message() ), '6.32.0' );
			return false;
		}
		self::$items[ $kind ][ $name ] = $def + array( 'name' => $name );

		return true;
	}

	/**
	 * Everything registered (callbacks left out).
	 *
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	public static function all(): array {
		$out = array();
		foreach ( self::$items as $kind => $items ) {
			$out[ $kind ] = array();
			foreach ( $items as $name => $def ) {
				$out[ $kind ][] = array_filter(
					array(
						'name'   => $name,
						'label'  => (string) ( $def['label'] ?? '' ),
						'plugin' => (string) ( $def['plugin'] ?? '' ),
					)
				);
			}
		}

		return $out;
	}

	/**
	 * One registered item.
	 *
	 * @param string $kind Kind.
	 * @param string $name Name.
	 * @return array<string, mixed>|null
	 */
	public static function get( string $kind, string $name ): ?array {
		return self::$items[ $kind ][ $name ] ?? null;
	}

	/**
	 * Registered items of a kind.
	 *
	 * @param string $kind Kind.
	 * @return array<string, array<string, mixed>>
	 */
	public static function of( string $kind ): array {
		return self::$items[ $kind ] ?? array();
	}

	/**
	 * `pbsw_dynamic_sources`: registered sources join the binding sources.
	 *
	 * @param array<string, string> $labels Name → label.
	 * @return array<string, string>
	 */
	public static function source_labels( $labels ): array {
		$labels = (array) $labels;
		foreach ( self::$items['sources'] as $name => $def ) {
			$labels[ $name ] = (string) $def['label'];
		}

		return $labels;
	}

	/**
	 * `pbsw_dynamic_value`: a registered source's value.
	 *
	 * @param string|null          $value  Value so far.
	 * @param string               $source Source.
	 * @param array<string, mixed> $args   Binding args (`field`, …).
	 * @param array<string, mixed> $ctx    The item (postId, postType, …).
	 * @return string|null
	 */
	public static function source_value( $value, $source, $args, $ctx ) {
		$def = self::$items['sources'][ (string) $source ] ?? null;
		if ( null === $def ) {
			return $value;
		}
		try {
			$out = call_user_func( $def['callback'], (array) $args, (array) $ctx );
		} catch ( \Throwable $e ) {
			return null; // An add-on's error never breaks the page; the binding shows nothing.
		}

		return is_scalar( $out ) ? (string) $out : null;
	}

	/**
	 * `pbsw_dynamic_editor_sources`: registered sources in the editor's Dynamic content panel.
	 *
	 * @param array<int, array<string, mixed>> $sources Sources.
	 * @return array<int, array<string, mixed>>
	 */
	public static function editor_sources( $sources ): array {
		$sources = (array) $sources;
		foreach ( self::$items['sources'] as $name => $def ) {
			$fields = array();
			foreach ( (array) ( $def['fields'] ?? array() ) as $value => $label ) {
				$fields[] = array(
					'value' => (string) $value,
					'label' => (string) $label,
				);
			}
			$sources[] = array(
				'value'  => $name,
				'label'  => (string) $def['label'],
				'fields' => $fields,
			);
		}

		return $sources;
	}

	/**
	 * Does a registered condition match the current request?
	 *
	 * @param string               $name Condition.
	 * @param array<string, mixed> $rule The sanitised rule (`value` holds what the author typed).
	 * @return bool
	 */
	public static function condition_matches( string $name, array $rule ): bool {
		$def = self::$items['conditions'][ $name ] ?? null;
		if ( null === $def ) {
			return false;
		}
		try {
			return true === call_user_func( $def['callback'], $rule );
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/**
	 * Run a registered form action.
	 *
	 * @param string               $name       Action.
	 * @param array<string, mixed> $submission The submission (rows, values, fields, post, form).
	 * @param array<string, mixed> $settings   The action's settings in the form.
	 * @return array{ok: bool, error?: string}
	 */
	public static function run_action( string $name, array $submission, array $settings ): array {
		$def = self::$items['actions'][ $name ] ?? null;
		if ( null === $def ) {
			return array(
				'ok'    => false,
				'error' => 'not registered',
			);
		}
		try {
			$out = call_user_func( $def['callback'], $submission, $settings );
		} catch ( \Throwable $e ) {
			$out = new \WP_Error( 'pbsw_action_failed', $e->getMessage() );
		}

		return is_wp_error( $out ) ? array(
			'ok'    => false,
			'error' => $out->get_error_message(),
		) : array( 'ok' => false !== $out );
	}
}
