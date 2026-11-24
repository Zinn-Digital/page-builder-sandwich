<?php
/**
 * Developer compatibility layer (feature pbs-dev5).
 *
 * Add-ons written for the classic Page Builder Sandwich editor described their shortcodes
 * through two APIs (legacy Shortcode-API.md): the PHP `pbsw_shortcodes` filter and the JS
 * `window.pbswAddInspector()`. Both keep working: every definition becomes a variation of the
 * pbsw/shortcode block, with the inspector controls it declared (src/core/dev-compat/).
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The `pbsw_shortcodes` filter, the early editor stub, and the pbsw/shortcode block.
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
	public const FILTER = 'pbsw_shortcodes';

	/**
	 * The legacy editor script handle. Add-ons declared their inspector scripts as depending on
	 * it (class-page-builder-sandwich.php:1151 registered it as `__CLASS__ . '-builder'`), so it
	 * is kept as an alias of the compat bundle: such a script still loads, after the API exists.
	 */
	public const LEGACY_HANDLE = 'PageBuilderSandwich-builder';

	/** The legacy action add-ons enqueued their editor scripts on (…-sandwich.php:1124). */
	public const LEGACY_ENQUEUE_ACTION = 'pbsw_enqueue_scripts';

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
		 * Legacy action: enqueue scripts that call window.pbswAddInspector().
		 */
		do_action( 'pbsw_enqueue_scripts' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- the legacy public API's own name; add-ons hook it as written.
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
		// The definitions as data, then the stub as a FILE, both blocking and at the top of the head,
		// so they still run before any add-on script. The stub is never printed inline (WordPress.org
		// review P0TDX382477HGN: plugin code in an inline script is an unescaped sink).
		wp_print_inline_script_tag( self::data_js( self::definitions() ) );
		wp_print_script_tag(
			array(
				'src' => add_query_arg( 'ver', PBSW_VERSION, PBSW_URL . 'includes/core/dev-compat-stub.js' ),
				'id'  => 'pbsw-dev-compat-stub-js',
			)
		);
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
		return self::normalise( apply_filters( 'pbsw_shortcodes', array() ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- the legacy public API's own name; add-ons hook it as written.
	}

	/**
	 * The inline data script: the definitions, JSON-encoded (JSON_HEX_TAG: a `</script>` inside an
	 * add-on's label cannot end the tag early).
	 *
	 * @param array<string, array<string, mixed>> $definitions From definitions().
	 * @return string
	 */
	public static function data_js( array $definitions ): string {
		return 'window.pbswDevCompat = ' . wp_json_encode( array( 'shortcodes' => (object) $definitions ), JSON_HEX_TAG | JSON_HEX_AMP ) . ';';
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
			// A block template runs do_shortcode() before do_blocks(), so a block the template itself holds arrives already expanded.
			$trust = Widgets::template_trust( 'pbsw/shortcode', static fn( array $attrs ): bool => ( $attrs['tag'] ?? '' ) === $tag );
			if ( null === $trust ) {
				return '';
			}

			// Filtered whatever the template's trust: this callback is the one printing it. Only a trusted
			// template's scripts (a form's settings) move to a registered handle; an untrusted one's are dropped.
			return self::filter_output( $content, true === $trust );
		}

		return self::shortcode_for_output( $text );
	}

	/**
	 * Hand one validated shortcode to WordPress, or run it here when WordPress will not.
	 *
	 * Inside the_content and widget_block_content, core runs do_shortcode() after do_blocks(), so the shortcode text is
	 * returned (filtered like every other path) and core prints the owning plugin's output exactly as it does for a
	 * shortcode in any post. Form plugins depend on this: Fluent Forms keeps its settings in an inline script and WPForms
	 * hides its honeypot with one. Anywhere else (a block template runs do_shortcode() BEFORE do_blocks()) the shortcode
	 * runs here and its output is filtered, because this callback is then the one printing it. Every return is filtered.
	 *
	 * @param string $shortcode One complete shortcode, already validated by the caller.
	 * @return string
	 */
	public static function shortcode_for_output( string $shortcode ): string {
		global $wp_filter;

		$filter = (string) current_filter();
		if ( isset( $wp_filter[ $filter ] ) ) {
			if ( self::core_runs_shortcodes_later( $filter, has_filter( $filter, 'do_shortcode' ), $wp_filter[ $filter ]->current_priority() ) ) {
				return self::filter_output( $shortcode );
			}
		}

		// A pbsw/form in a block template is judged by that template's people: an untrusted template's form prints,
		// but its scripts are dropped, never moved to a handle (wp.org round 5, finding 8).
		$held = Widgets::template_trust(
			'pbsw/form',
			static fn( array $attrs ): bool => \ZinnDigital\PBS\Blocks\Forms::shortcode( (string) ( $attrs['provider'] ?? '' ), (string) ( $attrs['formId'] ?? '' ) ) === $shortcode
		);

		return self::filter_output( do_shortcode( $shortcode ), false !== $held );
	}

	/**
	 * Whether core's do_shortcode is still to run on the filter now running, so a block may hand it the shortcode.
	 *
	 * @param string   $filter               Current filter.
	 * @param int|bool $do_shortcode_priority has_filter() result for do_shortcode.
	 * @param int|bool $current_priority     Priority now running.
	 * @return bool
	 */
	public static function core_runs_shortcodes_later( string $filter, $do_shortcode_priority, $current_priority ): bool {
		return in_array( $filter, array( 'the_content', 'widget_block_content' ), true )
			&& is_int( $do_shortcode_priority )
			&& is_int( $current_priority )
			&& $current_priority < $do_shortcode_priority;
	}

	/**
	 * Filter third-party shortcode or widget output before our callback returns it. `script` and `style` elements go
	 * with their contents, because wp_kses() alone would print their source as visible text.
	 *
	 * With $move_scripts, a script the owning plugin printed (Fluent Forms' settings, WPForms' honeypot) is not
	 * dropped but moved out of the markup into a registered pbsw- handle, so a form in a block template keeps working
	 * and no inline script sits in the block's HTML.
	 *
	 * @param string $html         Shortcode output.
	 * @param bool   $move_scripts Whether the output came from the owning plugin, so its scripts are moved.
	 * @return string
	 */
	public static function filter_output( string $html, bool $move_scripts = false ): string {
		/**
		 * Filters the HTML a pbs/shortcode block may print.
		 *
		 * @param array<string, array<string, bool>> $allowed Tags and attributes, as for wp_kses().
		 */
		$allowed = (array) apply_filters( 'pbsw_shortcode_allowed_html', self::allowed_html() );
		if ( $move_scripts && ! isset( $allowed['script'] ) ) {
			$html = (string) preg_replace_callback( '@<script\b([^>]*)>(.*?)</script\s*>@si', array( self::class, 'move_script' ), $html );
		}
		foreach ( array( 'script', 'style' ) as $element ) {
			if ( ! isset( $allowed[ $element ] ) ) {
				$html = (string) preg_replace( '@<' . $element . '\b[^>]*>.*?</' . $element . '\s*>@si', '', $html );
			}
		}

		return wp_kses( $html, $allowed );
	}

	/**
	 * Move one script element out of the markup into a registered handle: an external one is enqueued by its
	 * address, an inline JavaScript one becomes wp_add_inline_script() data on its own pbsw- handle (named by its
	 * content, so a form printed twice registers it once). Any other type (JSON data, modules) is dropped.
	 *
	 * @param array<int, string> $m Match: [0] the element, [1] its attributes, [2] its body.
	 * @return string Always '' — nothing stays in the markup.
	 */
	public static function move_script( array $m ): string {
		$attrs = (string) ( $m[1] ?? '' );
		$body  = (string) ( $m[2] ?? '' );
		$type  = preg_match( '@\btype\s*=\s*(["\']?)([^"\'\s>]*)\1@i', $attrs, $t ) ? strtolower( $t[2] ) : '';
		if ( ! in_array( $type, array( '', 'text/javascript', 'application/javascript' ), true ) ) {
			return '';
		}
		$version = defined( 'PBSW_VERSION' ) ? PBSW_VERSION : false;
		if ( preg_match( '@\bsrc\s*=\s*(["\'])(https?://[^"\'<>\s]+)\1@i', $attrs, $src ) ) {
			$url = html_entity_decode( $src[2], ENT_QUOTES );
			wp_enqueue_script( 'pbsw-shortcode-' . substr( md5( $url ), 0, 12 ), $url, array(), null, false ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- the owning plugin's address already carries its version.
			return '';
		}
		$body = trim( $body );
		if ( '' === $body || preg_match( '@\bsrc\s*=@i', $attrs ) ) {
			return '';
		}
		$handle = 'pbsw-shortcode-inline-' . substr( md5( $body ), 0, 12 );
		if ( ! wp_script_is( $handle, 'registered' ) ) {
			wp_register_script( $handle, false, array(), $version, false );
			wp_add_inline_script( $handle, self::inline_js( $body ) );
		}
		wp_enqueue_script( $handle );
		return '';
	}

	/**
	 * Escape a moved script body late, at the sink: WordPress prints it inside its own `<script>` element, so any
	 * `</script` or `<!--` in it could close or comment out that element. `<\/` and `<\!--` are the same characters
	 * to JavaScript inside a string, regex or comment, and neither can appear anywhere else in valid code, so the
	 * script runs exactly as its owning plugin wrote it (WordPress.org review P0TDX382477HGN: no unescaped sink).
	 *
	 * @param string $body Script source the owning plugin printed.
	 * @return string
	 */
	public static function inline_js( string $body ): string {
		return (string) preg_replace( array( '@</(script)@i', '@<!--@' ), array( '<\\/$1', '<\\!--' ), $body );
	}

	/**
	 * Post HTML plus what shortcodes commonly print: form controls, embeds and data attributes.
	 *
	 * @return array<string, array<string, bool>>
	 */
	private static function allowed_html(): array {
		$allowed = wp_kses_allowed_html( 'post' );
		$common  = (array) ( $allowed['span'] ?? array() ); // WordPress's global attributes.
		$extra   = array(
			'form'     => array(
				'action'       => true,
				'method'       => true,
				'enctype'      => true,
				'name'         => true,
				'novalidate'   => true,
				'target'       => true,
				'autocomplete' => true,
			),
			'input'    => array(
				'type'         => true,
				'name'         => true,
				'value'        => true,
				'placeholder'  => true,
				'checked'      => true,
				'disabled'     => true,
				'readonly'     => true,
				'required'     => true,
				'min'          => true,
				'max'          => true,
				'step'         => true,
				'maxlength'    => true,
				'minlength'    => true,
				'pattern'      => true,
				'size'         => true,
				'multiple'     => true,
				'accept'       => true,
				'autocomplete' => true,
			),
			'select'   => array(
				'name'     => true,
				'disabled' => true,
				'required' => true,
				'multiple' => true,
				'size'     => true,
			),
			'option'   => array(
				'value'    => true,
				'selected' => true,
				'disabled' => true,
				'label'    => true,
			),
			'optgroup' => array(
				'label'    => true,
				'disabled' => true,
			),
			'textarea' => array(
				'name'        => true,
				'rows'        => true,
				'cols'        => true,
				'placeholder' => true,
				'disabled'    => true,
				'readonly'    => true,
				'required'    => true,
				'maxlength'   => true,
			),
			'button'   => array(
				'type'     => true,
				'name'     => true,
				'value'    => true,
				'disabled' => true,
			),
			'label'    => array( 'for' => true ),
			'a'        => array(
				'hreflang' => true,
				'rel'      => true,
				'target'   => true,
				'download' => true,
			),
			'iframe'   => array(
				'src'             => true,
				'width'           => true,
				'height'          => true,
				'name'            => true,
				'loading'         => true,
				'allow'           => true,
				'allowfullscreen' => true,
				'referrerpolicy'  => true,
				'sandbox'         => true,
			),
		);
		foreach ( $extra as $tag => $attributes ) {
			$allowed[ $tag ] = array_merge( (array) ( $allowed[ $tag ] ?? array() ), $common, $attributes );
		}

		return $allowed;
	}
}
