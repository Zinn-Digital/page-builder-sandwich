<?php
/**
 * Global colours and fonts, shared with the theme (pbs-d4).
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Design\Globals;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads the site's colours and fonts from `wp_get_global_settings()` and writes them to the
 * user's `wp_global_styles` post — the store the Site Editor itself reads and writes — so the
 * builder and the theme can never disagree about what "primary" is.
 *
 * ⛔ There is no copy. A second store would be a second description of one palette, and the day
 * somebody edited the other one the two would drift. Every read goes to WordPress's merged
 * global settings; every write goes through the same post the Site Editor saves, in the same
 * origin-keyed shape (`palette.theme` for an edited theme colour, `palette.custom` for the
 * site's own), then the theme.json caches are cleared so the next read in the same request
 * already sees it. This works for classic themes too: WordPress merges the user origin into
 * the global settings whatever the theme type.
 */
final class Theme_Globals {

	/** The classic-theme store (WordPress before 7.0 keeps no user styles for a classic theme). */
	public const CLASSIC_OPTION = 'pbsw_classic_theme_json';

	/** The font reference format theme.json uses in styles. */
	private const FONT_REF = 'var:preset|font-family|';

	/**
	 * Everything the Site design screen shows about colours and fonts.
	 *
	 * @return array<string, mixed>
	 */
	public static function read(): array {
		// Another writer (the Site Editor, WP-CLI, a sync) may have saved since this request
		// cached the merged data; the panel must never show a value WordPress no longer has.
		wp_clean_theme_json_cache();
		$settings = self::merged_settings();
		$styles   = self::merged_styles();
		$palette  = (array) ( $settings['color']['palette'] ?? array() );
		$families = (array) ( $settings['typography']['fontFamilies'] ?? array() );

		return array(
			'colors'      => array(
				'default' => self::colors_from( (array) ( $palette['default'] ?? array() ) ),
				'theme'   => self::colors_from( (array) ( $palette['theme'] ?? array() ) ),
				'custom'  => self::colors_from( (array) ( $palette['custom'] ?? array() ) ),
			),
			'fonts'       => array(
				'theme'  => self::fonts_from( (array) ( $families['theme'] ?? array() ) ),
				'custom' => self::fonts_from( (array) ( $families['custom'] ?? array() ) ),
			),
			'bodyFont'    => self::font_slug( $styles['typography']['fontFamily'] ?? '' ),
			'headingFont' => self::font_slug( $styles['elements']['heading']['typography']['fontFamily'] ?? '' ),
			'blockTheme'  => wp_is_block_theme(),
		);
	}

	/**
	 * Hooks: the classic-theme store (see classic_user_data()).
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'wp_theme_json_data_user', array( self::class, 'classic_user_data' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'classic_font_css' ), 20 );
	}

	/**
	 * A classic theme gets theme.json PRESETS (the custom properties) but not theme.json STYLES,
	 * so a heading or body font chosen here would change nothing. For those themes the two rules
	 * are added to WordPress's own global-styles stylesheet.
	 *
	 * @return void
	 */
	public static function classic_font_css(): void {
		if ( wp_theme_has_theme_json() ) {
			return;
		}
		$styles = self::merged_styles();
		$css    = self::font_rules(
			self::font_slug( $styles['typography']['fontFamily'] ?? '' ),
			self::font_slug( $styles['elements']['heading']['typography']['fontFamily'] ?? '' )
		);
		if ( '' === $css ) {
			return;
		}
		// Its own neutral handle: where core prints global styles for a classic theme (head or
		// footer) differs between releases, and the rule only needs the preset variable to exist.
		$handle = \ZinnDigital\PBS\Settings::prefix() . '-gf';
		wp_register_style( $handle, false, array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- inline only.
		wp_enqueue_style( $handle );
		wp_add_inline_style( $handle, $css );
	}

	/**
	 * The merged settings, user origin included. wp_get_global_settings() leaves the user origin
	 * out for a classic theme (it asks for the `theme` origin when the theme has no theme.json),
	 * so the screen and the classic front end read the resolver directly.
	 *
	 * @return array<string, mixed>
	 */
	private static function merged_settings(): array {
		return (array) \WP_Theme_JSON_Resolver::get_merged_data( 'custom' )->get_settings();
	}

	/**
	 * The merged styles, user origin included (see merged_settings()).
	 *
	 * @return array<string, mixed>
	 */
	private static function merged_styles(): array {
		return (array) ( \WP_Theme_JSON_Resolver::get_merged_data( 'custom' )->get_raw_data()['styles'] ?? array() );
	}

	/**
	 * The body and heading font rules. Pure.
	 *
	 * @param string $body    Body font slug ('' for none).
	 * @param string $heading Heading font slug ('' for none).
	 * @return string
	 */
	public static function font_rules( string $body, string $heading ): string {
		$css = '';
		if ( self::is_slug( $body ) ) {
			$css .= 'body{font-family:var(--wp--preset--font-family--' . $body . ')}';
		}
		if ( self::is_slug( $heading ) ) {
			$css .= 'h1,h2,h3,h4,h5,h6{font-family:var(--wp--preset--font-family--' . $heading . ')}';
		}

		return $css;
	}

	/**
	 * The user's global styles, decoded: `[ post id, config ]`, the post created when missing.
	 * Post id 0 means the classic-theme store (the option), used where WordPress keeps no user
	 * global styles for the active theme.
	 *
	 * @return array{0: int, 1: array<string, mixed>}|\WP_Error
	 */
	public static function user_config() {
		$post    = \WP_Theme_JSON_Resolver::get_user_data_from_wp_global_styles( wp_get_theme(), true );
		$classic = get_option( self::CLASSIC_OPTION, array() );
		$classic = is_array( $classic ) ? $classic : array();
		if ( empty( $post['ID'] ) ) {
			if ( ! wp_theme_has_theme_json() ) {
				return array( 0, $classic );
			}
			return new \WP_Error( 'pbsw_no_global_styles', __( 'The site\'s global styles could not be opened.', 'page-builder-sandwich' ), array( 'status' => 500 ) );
		}
		$config = json_decode( (string) ( $post['post_content'] ?? '' ), true );
		$config = is_array( $config ) ? $config : array();
		if ( array() !== $classic ) {
			// WordPress now keeps user styles for this theme (an update to 7.0+): move ours in once.
			$config = array_replace_recursive( $classic, $config );
			delete_option( self::CLASSIC_OPTION );
			self::write( (int) $post['ID'], $config );
		}

		return array( (int) $post['ID'], $config );
	}

	/**
	 * `wp_theme_json_data_user`: before WordPress 7.0 a classic theme (no theme.json) has no user
	 * global styles at all — the resolver returns nothing and the Site Editor does not exist for
	 * it — so our colours and fonts are kept in an option and merged in here, as the user origin,
	 * exactly where WordPress would have merged the post. Nothing is added for a theme WordPress
	 * already keeps user styles for.
	 *
	 * @param \WP_Theme_JSON_Data|mixed $data User data.
	 * @return \WP_Theme_JSON_Data|mixed
	 */
	public static function classic_user_data( $data ) {
		$config = get_option( self::CLASSIC_OPTION, array() );
		if ( ! is_array( $config ) || array() === $config || ! $data instanceof \WP_Theme_JSON_Data || wp_theme_has_theme_json() ) {
			return $data;
		}
		$config['version'] = \WP_Theme_JSON::LATEST_SCHEMA;

		return $data->update_with( $config );
	}

	/**
	 * Write changes to the user's global styles post. Keys left out are left alone.
	 *
	 * @param array<string, mixed> $changes `colors` {theme?, custom?}, `fonts` {theme?, custom?}, `bodyFont`, `headingFont`.
	 * @return true|\WP_Error
	 */
	public static function save( array $changes ) {
		$opened = self::user_config();
		if ( is_wp_error( $opened ) ) {
			return $opened;
		}
		list( $post_id, $config ) = $opened;

		$next = self::apply_changes( $config, $changes );
		if ( is_wp_error( $next ) ) {
			return $next;
		}

		return self::write( $post_id, $next, $changes );
	}

	/**
	 * Write a whole config to the user's global styles post, as the Site Editor's REST
	 * controller does (version + the user-data flag, slashed JSON), and clear the caches.
	 *
	 * @param int                  $post_id Post id.
	 * @param array<string, mixed> $config  Config.
	 * @param array<string, mixed> $changes What changed (for the action).
	 * @return true|\WP_Error
	 */
	public static function write( int $post_id, array $config, array $changes = array() ) {
		if ( 0 === $post_id ) {
			unset( $config['isGlobalStylesUserThemeJSON'] );
			update_option( self::CLASSIC_OPTION, $config, true );
			wp_clean_theme_json_cache();
			/** This action is documented below. */
			do_action( 'pbsw_theme_globals_saved', $changes );
			return true;
		}
		$config['version']                     = \WP_Theme_JSON::LATEST_SCHEMA;
		$config['isGlobalStylesUserThemeJSON'] = true;

		$updated = wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => wp_slash( (string) wp_json_encode( $config ) ),
			),
			true
		);
		if ( is_wp_error( $updated ) ) {
			return $updated;
		}
		wp_clean_theme_json_cache();

		/**
		 * Fires after Page Builder Sandwich wrote the site's colours or fonts.
		 *
		 * @param array<string, mixed> $changes What was written.
		 */
		do_action( 'pbsw_theme_globals_saved', $changes );

		return true;
	}

	// ── The pure half ────────────────────────────────────────────────────────────────────────

	/**
	 * Apply changes to a decoded user theme.json (the post's JSON). Pure.
	 *
	 * @param array<string, mixed> $config  Decoded user config.
	 * @param array<string, mixed> $changes Changes.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function apply_changes( array $config, array $changes ) {
		foreach ( array( 'theme', 'custom' ) as $origin ) {
			if ( isset( $changes['colors'] ) && is_array( $changes['colors'] ) && array_key_exists( $origin, $changes['colors'] ) ) {
				$colors = self::clean_colors( (array) $changes['colors'][ $origin ] );
				if ( is_wp_error( $colors ) ) {
					return $colors;
				}
				$config = self::set_preset( $config, array( 'settings', 'color', 'palette' ), $origin, $colors );
			}
			if ( isset( $changes['fonts'] ) && is_array( $changes['fonts'] ) && array_key_exists( $origin, $changes['fonts'] ) ) {
				$existing = (array) ( self::preset( $config, array( 'settings', 'typography', 'fontFamilies' ) )[ $origin ] ?? array() );
				$fonts    = self::clean_fonts( (array) $changes['fonts'][ $origin ], $existing );
				if ( is_wp_error( $fonts ) ) {
					return $fonts;
				}
				$config = self::set_preset( $config, array( 'settings', 'typography', 'fontFamilies' ), $origin, $fonts );
			}
		}

		foreach ( array(
			'bodyFont'    => array( 'styles', 'typography', 'fontFamily' ),
			'headingFont' => array( 'styles', 'elements', 'heading', 'typography', 'fontFamily' ),
		) as $key => $path ) {
			if ( ! array_key_exists( $key, $changes ) ) {
				continue;
			}
			$slug = (string) $changes[ $key ];
			if ( '' !== $slug && ! self::is_slug( $slug ) ) {
				return new \WP_Error( 'pbsw_invalid_font', __( 'That font is not one of the site\'s fonts.', 'page-builder-sandwich' ), array( 'status' => 400 ) );
			}
			$config = self::set_path( $config, $path, '' === $slug ? null : self::FONT_REF . $slug );
		}

		return $config;
	}

	/**
	 * Add (or replace by slug) font families in the `custom` origin, WITH their font faces. Used
	 * only by server code that built the faces itself from the Font Library's own records, never
	 * with request data. Pure.
	 *
	 * @param array<string, mixed>             $config   Config.
	 * @param array<int, array<string, mixed>> $families Families (`slug`, `name`, `fontFamily`, optional `fontFace`).
	 * @return array<string, mixed>
	 */
	public static function add_font_families( array $config, array $families ): array {
		$path    = array( 'settings', 'typography', 'fontFamilies' );
		$current = (array) ( self::preset( $config, $path )['custom'] ?? array() );
		$by_slug = array();
		foreach ( $current as $family ) {
			if ( is_array( $family ) && isset( $family['slug'] ) ) {
				$by_slug[ (string) $family['slug'] ] = $family;
			}
		}
		foreach ( $families as $family ) {
			if ( isset( $family['slug'] ) && self::is_slug( (string) $family['slug'] ) && self::is_font_stack( (string) ( $family['fontFamily'] ?? '' ) ) ) {
				$by_slug[ (string) $family['slug'] ] = $family;
			}
		}

		return self::set_preset( $config, $path, 'custom', array_values( $by_slug ) );
	}

	/**
	 * Remove font families from the `custom` origin by slug. Pure.
	 *
	 * @param array<string, mixed> $config Config.
	 * @param array<int, string>   $slugs  Slugs.
	 * @return array<string, mixed>
	 */
	public static function remove_font_families( array $config, array $slugs ): array {
		$path    = array( 'settings', 'typography', 'fontFamilies' );
		$current = (array) ( self::preset( $config, $path )['custom'] ?? array() );
		$keep    = array_values(
			array_filter(
				$current,
				static fn( $family ): bool => ! ( is_array( $family ) && in_array( (string) ( $family['slug'] ?? '' ), $slugs, true ) )
			)
		);

		return self::set_preset( $config, $path, 'custom', $keep );
	}

	/**
	 * Validate a list of colours. Refuses (rather than drops) a bad entry, so the screen can say
	 * which one.
	 *
	 * @param array<int, mixed> $items Colours.
	 * @return array<int, array{slug: string, name: string, color: string}>|\WP_Error
	 */
	public static function clean_colors( array $items ) {
		$out  = array();
		$seen = array();
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$slug  = (string) ( $item['slug'] ?? '' );
			$color = trim( (string) ( $item['color'] ?? '' ) );
			if ( ! self::is_slug( $slug ) || isset( $seen[ $slug ] ) ) {
				return new \WP_Error( 'pbsw_invalid_slug', __( 'Each colour needs a unique name made of lowercase letters, digits and hyphens.', 'page-builder-sandwich' ), array( 'status' => 400 ) );
			}
			if ( ! self::is_color( $color ) ) {
				/* translators: %s: colour slug. */
				return new \WP_Error( 'pbsw_invalid_color', sprintf( __( 'The colour "%s" is not a valid CSS colour.', 'page-builder-sandwich' ), $slug ), array( 'status' => 400 ) );
			}
			$seen[ $slug ] = true;
			$out[]         = array(
				'slug'  => $slug,
				'name'  => self::label( (string) ( $item['name'] ?? $slug ) ),
				'color' => $color,
			);
		}

		return $out;
	}

	/**
	 * Validate a list of font families. `fontFace` is never taken from the request: it is kept
	 * from the stored entry of the same slug (the font files the Font Library installed), so a
	 * request cannot point the site at a font file somewhere else.
	 *
	 * @param array<int, mixed>                $items     Families.
	 * @param array<int, array<string, mixed>> $existing The stored families of the same origin.
	 * @return array<int, array<string, mixed>>|\WP_Error
	 */
	public static function clean_fonts( array $items, array $existing ) {
		$faces = array();
		foreach ( $existing as $family ) {
			if ( is_array( $family ) && isset( $family['slug'], $family['fontFace'] ) ) {
				$faces[ (string) $family['slug'] ] = $family['fontFace'];
			}
		}
		$out  = array();
		$seen = array();
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$slug   = (string) ( $item['slug'] ?? '' );
			$family = trim( (string) ( $item['fontFamily'] ?? '' ) );
			if ( ! self::is_slug( $slug ) || isset( $seen[ $slug ] ) ) {
				return new \WP_Error( 'pbsw_invalid_slug', __( 'Each font needs a unique name made of lowercase letters, digits and hyphens.', 'page-builder-sandwich' ), array( 'status' => 400 ) );
			}
			if ( ! self::is_font_stack( $family ) ) {
				/* translators: %s: font name. */
				return new \WP_Error( 'pbsw_invalid_font_stack', sprintf( __( 'The font stack for "%s" may contain only font names, quotes, commas and spaces.', 'page-builder-sandwich' ), $slug ), array( 'status' => 400 ) );
			}
			$seen[ $slug ] = true;
			$entry         = array(
				'slug'       => $slug,
				'name'       => self::label( (string) ( $item['name'] ?? $slug ) ),
				'fontFamily' => $family,
			);
			if ( isset( $faces[ $slug ] ) ) {
				$entry['fontFace'] = $faces[ $slug ];
			}
			$out[] = $entry;
		}

		return $out;
	}

	/**
	 * Is this a preset slug?
	 *
	 * @param string $slug Slug.
	 * @return bool
	 */
	public static function is_slug( string $slug ): bool {
		return 1 === preg_match( '/^[a-z0-9][a-z0-9-]{0,47}$/', $slug );
	}

	/**
	 * Is this a CSS colour we are willing to write into theme.json? Hex, the colour functions
	 * with numeric arguments, `transparent` and `currentcolor`.
	 *
	 * @param string $color Colour.
	 * @return bool
	 */
	public static function is_color( string $color ): bool {
		$color = strtolower( trim( $color ) );
		if ( 1 === preg_match( '/^#(?:[0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/', $color ) ) {
			return true;
		}
		if ( in_array( $color, array( 'transparent', 'currentcolor' ), true ) ) {
			return true;
		}

		return 1 === preg_match( '/^(?:rgba?|hsla?|hwb|lab|lch|oklab|oklch)\([0-9.%\s,\/+a-z-]{1,80}\)$/', $color )
			&& 1 !== preg_match( '/[a-z]{5,}/', substr( $color, (int) strpos( $color, '(' ) ) );
	}

	/**
	 * Is this a font stack: names, quotes, commas, spaces, hyphens and digits only.
	 *
	 * @param string $stack Stack.
	 * @return bool
	 */
	public static function is_font_stack( string $stack ): bool {
		return '' !== $stack && strlen( $stack ) <= 300 && 1 === preg_match( '/^[\p{L}\p{N} ,"\'._-]+$/u', $stack );
	}

	/**
	 * The slug named by a stored font-family style value, or ''.
	 *
	 * @param mixed $value `var:preset|font-family|x` or `var(--wp--preset--font-family--x)`.
	 * @return string
	 */
	public static function font_slug( $value ): string {
		$value = is_string( $value ) ? $value : '';
		if ( str_starts_with( $value, self::FONT_REF ) ) {
			return substr( $value, strlen( self::FONT_REF ) );
		}
		if ( 1 === preg_match( '/^var\(--wp--preset--font-family--([a-z0-9-]+)\)$/', $value, $m ) ) {
			return $m[1];
		}

		return '';
	}

	/**
	 * Colour entries as the screen wants them.
	 *
	 * @param array<int, mixed> $items Preset list.
	 * @return array<int, array{slug: string, name: string, color: string}>
	 */
	private static function colors_from( array $items ): array {
		$out = array();
		foreach ( $items as $item ) {
			if ( is_array( $item ) && isset( $item['slug'], $item['color'] ) ) {
				$out[] = array(
					'slug'  => (string) $item['slug'],
					'name'  => (string) ( $item['name'] ?? $item['slug'] ),
					'color' => (string) $item['color'],
				);
			}
		}

		return $out;
	}

	/**
	 * Font entries as the screen wants them.
	 *
	 * @param array<int, mixed> $items Preset list.
	 * @return array<int, array{slug: string, name: string, fontFamily: string, hasFiles: bool}>
	 */
	private static function fonts_from( array $items ): array {
		$out = array();
		foreach ( $items as $item ) {
			if ( is_array( $item ) && isset( $item['slug'], $item['fontFamily'] ) ) {
				$out[] = array(
					'slug'       => (string) $item['slug'],
					'name'       => (string) ( $item['name'] ?? $item['slug'] ),
					'fontFamily' => (string) $item['fontFamily'],
					'hasFiles'   => ! empty( $item['fontFace'] ),
				);
			}
		}

		return $out;
	}

	/**
	 * A preset read from a config (either shape), keyed by origin.
	 *
	 * @param array<string, mixed> $config Config.
	 * @param array<int, string>   $path   Path.
	 * @return array<string, array<int, mixed>>
	 */
	public static function preset( array $config, array $path ): array {
		$node = $config;
		foreach ( $path as $key ) {
			if ( ! is_array( $node ) || ! isset( $node[ $key ] ) ) {
				return array();
			}
			$node = $node[ $key ];
		}
		if ( ! is_array( $node ) ) {
			return array();
		}

		// A plain list is the `custom` origin (that is how WP_Theme_JSON reads user data).
		return array_is_list( $node ) ? array( 'custom' => $node ) : $node;
	}

	/**
	 * Set one origin of a preset, keeping the others; the result is always origin-keyed, which is
	 * the shape the Site Editor writes.
	 *
	 * @param array<string, mixed> $config Config.
	 * @param array<int, string>   $path   Path.
	 * @param string               $origin `theme` or `custom`.
	 * @param array<int, mixed>    $value  List (an empty list removes the origin).
	 * @return array<string, mixed>
	 */
	private static function set_preset( array $config, array $path, string $origin, array $value ): array {
		$preset = self::preset( $config, $path );
		if ( array() === $value ) {
			unset( $preset[ $origin ] );
		} else {
			$preset[ $origin ] = $value;
		}

		return self::set_path( $config, $path, array() === $preset ? null : $preset );
	}

	/**
	 * Set (or with null, remove) a nested key, pruning emptied parents.
	 *
	 * @param array<string, mixed> $config Config.
	 * @param array<int, string>   $path   Path.
	 * @param mixed                $value  Value or null.
	 * @return array<string, mixed>
	 */
	private static function set_path( array $config, array $path, $value ): array {
		$key = array_shift( $path );
		if ( null === $key ) {
			return $config;
		}
		if ( array() === $path ) {
			if ( null === $value ) {
				unset( $config[ $key ] );
			} else {
				$config[ $key ] = $value;
			}
			return $config;
		}
		$child = isset( $config[ $key ] ) && is_array( $config[ $key ] ) ? $config[ $key ] : array();
		$child = self::set_path( $child, $path, $value );
		if ( array() === $child ) {
			unset( $config[ $key ] );
		} else {
			$config[ $key ] = $child;
		}

		return $config;
	}

	/**
	 * A display name: plain text, at most 80 characters.
	 *
	 * @param string $name Name.
	 * @return string
	 */
	private static function label( string $name ): string {
		$name = trim( wp_strip_all_tags( $name ) );

		return function_exists( 'mb_substr' ) ? mb_substr( $name, 0, 80 ) : substr( $name, 0, 80 );
	}
}
