<?php
/**
 * Developer compatibility layer (feature pbs-dev5).
 *
 * Add-ons written for the classic Page Builder Sandwich editor described their shortcodes
 * through two APIs (legacy Shortcode-API.md): the PHP `pbs_shortcodes` filter and the JS
 * `window.pbsAddInspector()`. Both keep working: every definition becomes a variation of the
 * pbs/shortcode block, with the inspector controls it declared (src/core/dev-compat/).
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The `pbs_shortcodes` filter, the early editor stub, and the pbs/shortcode block.
 *
 * ⛔⛔ The block renders ONLY its own saved shortcode. Legacy's live preview executed a
 * base64-decoded POSTed shortcode for any user who could edit the page being viewed — a "run
 * any shortcode" primitive (docs/plugins-overhaul/10-audit-pbs.md §3,
 * class-render-shortcode.php). Here the render callback reads the block's saved inner content,
 * requires it to be exactly one shortcode of the block's own `tag`, and runs nothing else. The
 * REST block renderer passes attributes but no inner content, so through it nothing runs at all;
 * the editor shows the shortcode text instead of a server preview for the same reason.
 */
final class Dev_Compat {

	/** Editor script handle — wp-admin only, so it may carry the plugin's name. */
	public const HANDLE = 'pbsw-dev-compat';

	/** The legacy filter name, unchanged so existing add-ons keep working. */
	public const FILTER = 'pbs_shortcodes';

	/**
	 * The legacy editor script handle. Add-ons declared their inspector scripts as depending on
	 * it (class-page-builder-sandwich.php:1151 registered it as `__CLASS__ . '-builder'`), so it
	 * is kept as an alias of the compat bundle: such a script still loads, after the API exists.
	 */
	public const LEGACY_HANDLE = 'PageBuilderSandwich-builder';

	/** The legacy action add-ons enqueued their editor scripts on (…-sandwich.php:1124). */
	public const LEGACY_ENQUEUE_ACTION = 'pbs_enqueue_scripts';

	/** Shortcode tags this layer accepts. */
	private const TAG_RE = '/^[A-Za-z0-9_-]+$/';

	/** Scalar keys kept on a legacy option (everything else is dropped). */
	private const OPTION_KEYS = array( 'type', 'name', 'id', 'desc', 'placeholder', 'default', 'checked', 'unchecked', 'min', 'max', 'step', 'multiple' );

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'init', array( self::class, 'register_block' ) );
		// Priority 1 of admin_print_scripts runs before WordPress prints ANY head script
		// (print_head_scripts is at 20), so the stub precedes every add-on file.
		add_action( 'admin_print_scripts', array( self::class, 'print_stub' ), 1 );
		add_action( 'enqueue_block_editor_assets', array( self::class, 'enqueue_addons' ) );
	}

	/**
	 * In the block editor: load the compat bundle, keep the legacy handle, and fire the legacy
	 * enqueue action so add-ons load their inspector scripts here as they did in the old editor.
	 *
	 * @return void
	 */
	public static function enqueue_addons(): void {
		if ( ! wp_script_is( self::HANDLE, 'registered' ) ) {
			return;
		}
		wp_enqueue_script( self::HANDLE );
		if ( ! wp_script_is( self::LEGACY_HANDLE, 'registered' ) ) {
			wp_register_script( self::LEGACY_HANDLE, false, array( self::HANDLE ), PBSW_VERSION, true );
		}
		/**
		 * Legacy action: enqueue scripts that call window.pbsAddInspector().
		 */
		do_action( 'pbs_enqueue_scripts' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- the legacy public API's own name; add-ons hook it as written.
	}

	/**
	 * Register the editor bundle and the block.
	 *
	 * @return void
	 */
	public static function register_block(): void {
		$asset_file = PBSW_DIR . 'build/dev-compat.asset.php';
		if ( is_readable( $asset_file ) ) {
			$asset = require $asset_file;
			wp_register_script(
				self::HANDLE,
				PBSW_URL . 'build/dev-compat.js',
				(array) ( $asset['dependencies'] ?? array() ),
				(string) ( $asset['version'] ?? PBSW_VERSION ),
				true
			);
			wp_set_script_translations( self::HANDLE, 'page-builder-sandwich', PBSW_DIR . 'languages' );
		}

		register_block_type(
			PBSW_DIR . 'blocks/shortcode',
			array( 'render_callback' => array( self::class, 'render' ) )
		);
	}

	/**
	 * Print the stub and the filter's definitions at the top of a block editor screen.
	 *
	 * The filter is applied here, as late as the head allows, so an add-on that hooks it from
	 * `admin_init` or `current_screen` is still seen (legacy applied it at editor-script time).
	 *
	 * @return void
	 */
	public static function print_stub(): void {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || ! $screen->is_block_editor() ) {
			return;
		}
		wp_print_inline_script_tag( self::stub_js( self::definitions() ) );
	}

	/**
	 * The filter's definitions, normalised.
	 *
	 * @return array<string, array{label: string, desc: string, options: list<array<string, mixed>>}>
	 */
	public static function definitions(): array {
		/**
		 * Legacy filter: shortcode tag => {label, desc, options[]} (Shortcode-API.md).
		 *
		 * @param array $shortcodes Definitions.
		 */
		return self::normalise( apply_filters( 'pbs_shortcodes', array() ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- the legacy public API's own name; add-ons hook it as written.
	}

	/**
	 * The inline script: the definitions, then the stub.
	 *
	 * @param array<string, array<string, mixed>> $definitions From definitions().
	 * @return string
	 */
	public static function stub_js( array $definitions ): string {
		// JSON_HEX_TAG: a `</script>` inside an add-on's label cannot end the tag early.
		$data = (string) wp_json_encode(
			array( 'shortcodes' => (object) $definitions ),
			JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		);
		$stub = (string) file_get_contents( __DIR__ . '/dev-compat-stub.js' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a file shipped in this plugin, read locally.

		return 'window.pbswDevCompat = ' . $data . ";\n" . $stub;
	}

	/**
	 * Normalise whatever the filter returned.
	 *
	 * @param mixed $raw Filter output.
	 * @return array<string, array{label: string, desc: string, options: list<array<string, mixed>>}>
	 */
	public static function normalise( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$out = array();
		foreach ( $raw as $tag => $def ) {
			if ( ! is_string( $tag ) || 1 !== preg_match( self::TAG_RE, $tag ) || ! is_array( $def ) ) {
				continue;
			}
			$options = array();
			foreach ( is_array( $def['options'] ?? null ) ? $def['options'] : array() as $option ) {
				if ( is_array( $option ) ) {
					$options[] = self::normalise_option( $option );
				}
			}
			$label       = self::text( $def['label'] ?? '' );
			$out[ $tag ] = array(
				'label'   => '' !== $label ? $label : $tag,
				'desc'    => self::text( $def['desc'] ?? '' ),
				'options' => $options,
			);
		}

		return $out;
	}

	/**
	 * Keep the known scalar keys of one option, plus a select/multicheck `options` map.
	 *
	 * @param array<mixed> $option Legacy option.
	 * @return array<string, mixed>
	 */
	private static function normalise_option( array $option ): array {
		$out = array();
		foreach ( self::OPTION_KEYS as $key ) {
			if ( ! array_key_exists( $key, $option ) ) {
				continue;
			}
			$value = $option[ $key ];
			if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
				$out[ $key ] = $value;
			} elseif ( is_string( $value ) ) {
				$out[ $key ] = in_array( $key, array( 'name', 'desc', 'placeholder' ), true ) ? self::text( $value ) : $value;
			}
		}
		if ( is_array( $option['options'] ?? null ) ) {
			$choices = array();
			foreach ( $option['options'] as $value => $label ) {
				if ( is_scalar( $label ) ) {
					$choices[ (string) $value ] = self::text( (string) $label );
				}
			}
			// An object in JSON even when the keys are 0..n, so the editor reads value => label.
			$out['options'] = (object) $choices;
		}

		return $out;
	}

	/**
	 * Plain text: legacy printed descriptions as raw HTML; here markup is removed.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function text( $value ): string {
		return is_scalar( $value ) ? trim( (string) preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $value ) ) ) : '';
	}

	/**
	 * Render callback: run this block's own saved shortcode, and nothing else.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $content    The block's saved inner content (the shortcode text).
	 * @return string
	 */
	public static function render( array $attributes, string $content = '' ): string {
		$tag = is_string( $attributes['tag'] ?? null ) ? $attributes['tag'] : '';
		if ( 1 !== preg_match( self::TAG_RE, $tag ) || ! shortcode_exists( $tag ) ) {
			return '';
		}
		$text = trim( $content );
		if ( '' === $text ) {
			return '';
		}
		// Exactly one shortcode of THIS tag, spanning the whole saved text; `[[tag]]` (the
		// escaped, print-literally form) is refused as well.
		if ( 1 !== preg_match( '/^' . get_shortcode_regex( array( $tag ) ) . '$/s', $text, $m ) || ( '[' === $m[1] && ']' === $m[6] ) ) {
			return '';
		}

		return do_shortcode( $text );
	}
}
