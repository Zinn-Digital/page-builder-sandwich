<?php
/**
 * `pbs/nav-menu` — a WordPress menu as a WAI-ARIA disclosure navigation with a mobile menu.
 *
 * ⚖️ Free since 6.36.22 (NAV1, owner ruling 2026-10-19 "Basic free, mega Pro"): the responsive
 * menu and every mobile-menu option are free. Mega menu panels (pbs/mega-panel), per-item icons,
 * badges, descriptions and visibility, and the site header are Pro. When Pro is not running
 * (a trial ended unpaid, §2.62) a mega panel is not registered, so its item simply shows its
 * ordinary submenu and the Pro item options are not applied: the menu never breaks.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Blocks\Site;

use ZinnDigital\PBS\Assets\Perf\Modules;
use ZinnDigital\PBS\Design\Breakpoints;
use ZinnDigital\PBS\Frontend;
use ZinnDigital\PBS\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Navigation menu.
 *
 * Each item with a submenu (or a mega panel) gets a button that opens it; Escape closes it and
 * returns focus. Below the chosen width the list moves into a mobile menu opened by a Menu
 * button: a side drawer or a full-screen overlay (a modal dialog with a focus trap and the page
 * scroll locked), or a dropdown under the button (a disclosure). Without script, submenus open on
 * hover and focus, and the whole list stays in the page.
 */
final class Nav {

	/** When the menu folds into the Menu button. */
	public const COLLAPSE = array( 'mobile', 'tablet', 'always', 'never', 'custom' );

	/** The mobile menu's shape. */
	public const STYLES = array( 'drawer', 'fullscreen', 'dropdown' );

	/** The Menu button's icon. */
	public const ICONS = array( 'menu', 'menu-alt', 'grid', 'none' );

	/** The Menu button's look. */
	public const TOGGLES = array( 'outline', 'solid', 'plain' );

	/** How a submenu appears. */
	public const ANIMATIONS = array( 'none', 'fade', 'slide' );

	/** Close delays of a hover menu, in milliseconds. */
	public const DELAYS = array( 0, 150, 300, 500 );

	/** What a mega panel's item shows on phones (pbs/mega-panel `mobile`). */
	public const PANEL_MOBILE = array( 'show', 'links', 'hide' );

	/** The narrowest and widest custom breakpoint, in pixels. */
	public const MIN_WIDTH = 320;

	/** Widest custom breakpoint. */
	public const MAX_WIDTH = 2560;

	/**
	 * The data source (tests replace it).
	 *
	 * @var Menu_Data|null
	 */
	public static ?Menu_Data $data = null;

	/**
	 * Breakpoint widths whose collapsed layout this request needs and has not delivered yet.
	 *
	 * @var array<int, bool>
	 */
	private static array $pending = array();

	/**
	 * Breakpoint widths already delivered on this request.
	 *
	 * @var array<int, bool>
	 */
	private static array $delivered = array();

	/**
	 * Glyph paths (nav-glyphs.json).
	 *
	 * @var array<string, string>|null
	 */
	private static ?array $glyphs = null;

	/**
	 * The collapsed layout block of the stylesheet, with the `__PREFIX__` token still in it.
	 *
	 * @var string|null
	 */
	private static ?string $collapsed = null;

	/**
	 * The data source.
	 *
	 * @return Menu_Data
	 */
	public static function data(): Menu_Data {
		if ( null === self::$data ) {
			self::$data = new Menu_Data();
		}

		return self::$data;
	}

	/**
	 * Hooks (Blocks\Site::register()).
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'after_setup_theme', array( self::class, 'enable_menus' ), 99 );
		add_action( 'wp_enqueue_scripts', array( self::class, 'print_pending' ), 100 );
		add_action( 'wp_footer', array( self::class, 'print_pending' ), 5 );
	}

	/**
	 * `after_setup_theme`: WordPress menus stay available with a block theme, which hides
	 * Appearance › Menus unless the theme declares menu support; this block shows those menus.
	 *
	 * @return void
	 */
	public static function enable_menus(): void {
		if ( ! current_theme_supports( 'menus' ) ) {
			add_theme_support( 'menus' );
		}
	}

	/**
	 * Render callback.
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 * @param \WP_Block|null       $block      Block.
	 * @return string
	 */
	public static function render( array $attributes, $block = null ): string {
		$tree = self::data()->menu( (int) ( $attributes['menuId'] ?? 0 ) );
		if ( array() === $tree ) {
			return '';
		}
		$panels = array();
		$mobile = array();
		if ( $block instanceof \WP_Block && class_exists( \WP_Block_Type_Registry::class ) && \WP_Block_Type_Registry::get_instance()->is_registered( 'pbs/mega-panel' ) ) {
			foreach ( $block->inner_blocks as $child ) {
				$item = (int) ( $child->attributes['itemId'] ?? 0 );
				if ( $item > 0 && 'pbs/mega-panel' === $child->name ) {
					$html = (string) $child->render();
					if ( '' !== trim( $html ) ) {
						$panels[ $item ] = $html;
						$mobile[ $item ] = self::choice( $child->attributes['mobile'] ?? '', self::PANEL_MOBILE );
					}
				}
			}
		}
		$prefix = Settings::prefix();
		$store  = null !== Modules::enqueue( 'nav-menu' ) ? Modules::store() : '';
		if ( '' !== $store ) {
			self::queue( self::width( $attributes ) );
		}

		return self::markup( $tree, $panels, $attributes, $prefix, $store, wp_unique_id( $prefix . '-nav-' ), $mobile );
	}

	/**
	 * The attributes, each one of its allowed values.
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 * @return array{collapse: string, style: string, side: string, icon: string, toggle: string, label: string, showLabel: bool, closeOnLink: bool, subsOpen: bool, hover: bool, delay: int, animation: string, skip: string}
	 */
	public static function options( array $attributes ): array {
		$label = trim( wp_strip_all_tags( (string) ( $attributes['toggleLabel'] ?? '' ) ) );
		$icon  = self::choice( $attributes['toggleIcon'] ?? '', self::ICONS );
		$delay = (int) ( $attributes['closeDelay'] ?? 150 );
		$skip  = '';
		if ( ! empty( $attributes['skipLink'] ) ) {
			$skip = (string) ( $attributes['skipTarget'] ?? '' );
			$skip = 1 === preg_match( '/^[A-Za-z][A-Za-z0-9_-]{0,63}$/', $skip ) ? $skip : 'main';
		}

		return array(
			'collapse'    => self::choice( $attributes['collapse'] ?? '', self::COLLAPSE ),
			'style'       => self::choice( $attributes['mobileStyle'] ?? '', self::STYLES ),
			'side'        => 'start' === ( $attributes['drawerSide'] ?? '' ) ? 'start' : 'end',
			'icon'        => $icon,
			'toggle'      => self::choice( $attributes['toggleStyle'] ?? '', self::TOGGLES ),
			'label'       => '' === $label ? __( 'Menu', 'page-builder-sandwich' ) : $label,
			// A button with no icon must show its words.
			'showLabel'   => 'none' === $icon || false !== ( $attributes['toggleShowLabel'] ?? true ),
			'closeOnLink' => false !== ( $attributes['closeOnLink'] ?? true ),
			'subsOpen'    => 'expanded' === ( $attributes['mobileSubmenus'] ?? '' ),
			'hover'       => 'hover' === ( $attributes['openOn'] ?? '' ),
			'delay'       => in_array( $delay, self::DELAYS, true ) ? $delay : 150,
			'animation'   => self::choice( $attributes['animation'] ?? '', self::ANIMATIONS, 'fade' ),
			'skip'        => $skip,
		);
	}

	/**
	 * The width (px) below which the menu folds, or 0 when that is not a width (always, never).
	 * Phones and tablets follow the site's breakpoints (Design › Breakpoints).
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 * @return int
	 */
	public static function width( array $attributes ): int {
		$collapse = self::choice( $attributes['collapse'] ?? '', self::COLLAPSE );
		if ( 'custom' === $collapse ) {
			return max( self::MIN_WIDTH, min( self::MAX_WIDTH, (int) ( $attributes['breakpoint'] ?? 0 ) ) );
		}
		if ( 'mobile' !== $collapse && 'tablet' !== $collapse ) {
			return 0;
		}
		$site = class_exists( Breakpoints::class ) ? Breakpoints::get() : array();

		return (int) ( $site[ $collapse ] ?? ( 'mobile' === $collapse ? 767 : 1024 ) );
	}

	/**
	 * Ask for one width's collapsed layout on this page ('0': none). Queued for the REQUEST, never
	 * printed into the block's own output: a block can be rendered and its output thrown away
	 * before the real render (a page-CSS pre-scan, an excerpt, an SEO plugin reading the content),
	 * and a style printed into that copy is lost — the menu never folded on a real block theme
	 * page (NAV1 live proof, 2026-10-19). And WordPress.org refuses a printed `<style>` tag
	 * (wp-catalogue-check W015): it goes out through wp_add_inline_style() instead.
	 *
	 * @param int $width Width in px.
	 * @return void
	 */
	public static function queue( int $width ): void {
		if ( $width > 0 && ! isset( self::$delivered[ $width ] ) ) {
			self::$pending[ $width ] = true;
		}
	}

	/**
	 * The collapsed layouts queued and not yet delivered, as one stylesheet; marks them delivered.
	 *
	 * @param string $prefix Class prefix.
	 * @return string
	 */
	public static function take_css( string $prefix ): string {
		$css = '';
		foreach ( array_keys( self::$pending ) as $width ) {
			$css                      .= self::collapsed_css( (int) $width, $prefix );
			self::$delivered[ $width ] = true;
		}
		self::$pending = array();

		return $css;
	}

	/**
	 * `wp_enqueue_scripts` (late: it runs inside wp_head, after a block theme rendered its
	 * template, so the layout is in `<head>` and is the first paint) and `wp_footer` (early: a
	 * classic theme's menu renders after the head, like its stylesheet): enqueue what is queued as
	 * ONE inline stylesheet under a neutral handle — the design layer's delivery (Styles).
	 *
	 * @return void
	 */
	public static function print_pending(): void {
		$prefix = Settings::prefix();
		$css    = self::take_css( $prefix );
		if ( '' === $css ) {
			return;
		}
		$handle = $prefix . '-' . substr( sha1( $css ), 0, 8 );
		wp_register_style( $handle, false, array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- inline only.
		wp_enqueue_style( $handle );
		wp_add_inline_style( $handle, \ZinnDigital\PBS\Assets::inline_css( $css ) );
	}

	/**
	 * The stylesheet's collapsed block for one width: scoped to `--bp-<width>`, inside its media
	 * query. Pure but for reading the plugin's own stylesheet once.
	 *
	 * @param int    $width  Width in px.
	 * @param string $prefix Class prefix.
	 * @return string
	 */
	public static function collapsed_css( int $width, string $prefix ): string {
		if ( null === self::$collapsed ) {
			$file            = dirname( __DIR__, 3 ) . '/assets/blocks/nav-menu.css';
			$body            = is_readable( $file ) ? (string) file_get_contents( $file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a file inside this plugin.
			self::$collapsed = 1 === preg_match( '#/\* collapsed:start \*/(.*?)/\* collapsed:end \*/#s', $body, $m ) ? $m[1] : '';
		}
		if ( '' === self::$collapsed ) {
			return '';
		}
		$css = str_replace(
			array( '.__PREFIX__-nav-menu--collapse-always', '__PREFIX__' ),
			array( '.__PREFIX__-nav-menu--bp-' . $width, $prefix ),
			self::$collapsed
		);
		$css = (string) preg_replace( '#/\*.*?\*/#s', '', $css );
		$css = trim( (string) preg_replace( '/\s+/', ' ', $css ) );
		$css = str_replace( array( ' { ', '; ', ' } ', ': ' ), array( '{', ';', '}', ':' ), $css );

		// ⛔ No `<` anywhere: `width <= …` read as the start of a tag, and wp_strip_all_tags() cut
		// the whole block (caught by PbsNavMenuNav1Test). max-width says the same, and dropping
		// every `<` means nothing can close the <style> element early.
		return str_replace( '<', '', '@media (scripting: enabled) and (max-width: ' . $width . 'px){' . $css . '}' );
	}

	/**
	 * The navigation's markup. Pure.
	 *
	 * @param array<int, array<string, mixed>> $tree       Menu tree (Menu_Data::menu()).
	 * @param array<int, string>               $panels     Rendered mega panels by top-level item ID.
	 * @param array<string, mixed>             $attributes Attributes.
	 * @param string                           $prefix     Class prefix.
	 * @param string                           $store      Interactivity store ('' without the module).
	 * @param string                           $id         Id base.
	 * @param array<int, string>               $mobile     What each mega panel's item shows on phones.
	 * @return string
	 */
	public static function markup( array $tree, array $panels, array $attributes, string $prefix, string $store, string $id, array $mobile = array() ): string {
		$c        = static fn( string ...$parts ): string => esc_attr( Frontend::classes( $prefix, ...$parts ) );
		$o        = self::options( $attributes );
		$vertical = 'vertical' === ( $attributes['orientation'] ?? '' );
		$label    = trim( wp_strip_all_tags( (string) ( $attributes['label'] ?? '' ) ) );
		$label    = '' === $label ? __( 'Main', 'page-builder-sandwich' ) : $label;
		$script   = '' !== $store;
		$width    = self::width( $attributes );
		$n        = 0;

		$list = static function ( array $items, int $depth ) use ( &$list, &$n, $c, $panels, $mobile, $id, $script, $attributes, $prefix ): string {
			$html = '';
			foreach ( $items as $item ) {
				$panel  = 0 === $depth ? ( $panels[ (int) $item['id'] ] ?? '' ) : '';
				$kids   = array() !== $item['children'];
				$on_mob = '' === $panel ? 'show' : self::choice( $mobile[ (int) $item['id'] ] ?? '', self::PANEL_MOBILE );
				if ( 'links' === $on_mob && ! $kids ) {
					$on_mob = 'hide';
				}
				$has = '' !== $panel || $kids;
				$cls = array( 'nav-menu__item' );
				if ( $has ) {
					$cls[] = 'nav-menu__item--has-sub';
				}
				if ( ! empty( $item['current'] ) ) {
					$cls[] = 'nav-menu__item--current';
				}
				if ( 'hide' === $on_mob ) {
					$cls[] = 'nav-menu__item--mega-hide';
				}
				/**
				 * Filters an item's classes (the Pro item options add `nav-menu__item--hide-mobile`
				 * and `--hide-desktop`). Only `nav-menu__item--<name>` classes are kept.
				 *
				 * @param array<int, string>   $cls        Unprefixed classes.
				 * @param array<string, mixed> $item       The item (id, title, url, desc, children…).
				 * @param array<string, mixed> $attributes The block's attributes.
				 * @param int                  $depth      0 for a top-level item.
				 */
				$more = apply_filters( 'pbsw_nav_menu_item_classes', $cls, $item, $attributes, $depth );
				$cls  = array_values( array_filter( is_array( $more ) ? $more : $cls, static fn( $k ): bool => is_string( $k ) && 1 === preg_match( '/^nav-menu__item(--[a-z0-9-]+)?$/', $k ) ) );
				/**
				 * Filters an item's label, which is escaped HTML (the Pro item options add an icon,
				 * a badge and the description). A callback must return escaped HTML.
				 *
				 * @param string               $label      Escaped title.
				 * @param array<string, mixed> $item       The item.
				 * @param array<string, mixed> $attributes The block's attributes.
				 * @param int                  $depth      0 for a top-level item.
				 * @param string               $prefix     Class prefix.
				 */
				$text  = (string) apply_filters( 'pbsw_nav_menu_item_label', esc_html( (string) $item['title'] ), $item, $attributes, $depth, $prefix );
				$html .= '<li class="' . $c( ...$cls ) . '"><a class="' . $c( 'nav-menu__link' ) . '" href="' . esc_url( (string) $item['url'] ) . '"'
					. ( ! empty( $item['current'] ) ? ' aria-current="page"' : '' )
					. ( '_blank' === $item['target'] ? ' target="_blank" rel="noopener"' : '' )
					. '>' . $text . '</a>';
				if ( $has ) {
					++$n;
					$sub      = $id . '-sub-' . $n;
					$mob      = '' !== $panel && 'links' === $on_mob ? $sub . '-m' : '';
					$controls = '' === $mob ? $sub : $sub . ' ' . $mob;
					$html    .= '<button type="button" class="' . $c( 'nav-menu__expand' ) . '" aria-expanded="false" aria-controls="' . esc_attr( $controls ) . '"'
						. ( $script ? ' data-wp-context="' . esc_attr( (string) wp_json_encode( array( 'sub' => $sub ) ) ) . '" data-wp-bind--aria-expanded="state.navMenuSubOpen" data-wp-on--click="actions.navMenuSub"' : '' )
						/* translators: %s: menu item title. */
						. '>' . self::glyph( 'down' ) . '<span class="' . $c( 'nav-menu__sr' ) . '">' . esc_html( sprintf( __( '%s submenu', 'page-builder-sandwich' ), (string) $item['title'] ) ) . '</span></button>';
					if ( '' !== $panel ) {
						$html .= '<div class="' . $c( 'nav-menu__sub', 'nav-menu__sub--mega' ) . '" id="' . esc_attr( $sub ) . '">' . $panel . '</div>';
						if ( '' !== $mob ) {
							$html .= '<ul class="' . $c( 'nav-menu__sub', 'nav-menu__sub--mobile' ) . '" id="' . esc_attr( $mob ) . '" data-sub="' . esc_attr( $sub ) . '">' . $list( $item['children'], $depth + 1 ) . '</ul>';
						}
					} else {
						$html .= '<ul class="' . $c( 'nav-menu__sub' ) . '" id="' . esc_attr( $sub ) . '">' . $list( $item['children'], $depth + 1 ) . '</ul>';
					}
				}
				$html .= '</li>';
			}

			return $html;
		};

		$items  = $list( $tree, 0 );
		$drawer = $id . '-drawer';
		$cls    = array(
			'nav-menu',
			'nav-menu--' . ( $vertical ? 'vertical' : 'horizontal' ),
			'nav-menu--collapse-' . $o['collapse'],
			$script && $width > 0 ? 'nav-menu--bp-' . $width : '',
			'nav-menu--mobile-' . $o['style'],
			'start' === $o['side'] ? 'nav-menu--side-start' : '',
			'nav-menu--toggle-' . $o['toggle'],
			$o['subsOpen'] ? 'nav-menu--subs-open' : '',
			'none' === $o['animation'] ? '' : 'nav-menu--anim-' . $o['animation'],
			$o['hover'] ? 'nav-menu--hover' : '',
			$o['hover'] && $o['delay'] > 0 ? 'nav-menu--delay-' . $o['delay'] : '',
		);
		$html   = '<nav class="' . $c( ...$cls ) . '" aria-label="' . esc_attr( $label ) . '"';
		if ( $script ) {
			$html .= ' data-wp-interactive="' . esc_attr( $store ) . '" data-wp-context="' . esc_attr(
				(string) wp_json_encode(
					array(
						'open'        => array(),
						'drawer'      => false,
						'ready'       => false,
						'dialogLabel' => $label,
						'modal'       => 'dropdown' !== $o['style'],
						'closeOnLink' => $o['closeOnLink'],
					)
				)
			) . '" data-wp-init="callbacks.navMenuInit" data-wp-bind--data-ready="context.ready" data-wp-on-document--keydown="callbacks.navMenuKey" data-wp-on-document--click="callbacks.navMenuOutside" data-wp-on-document--focusin="callbacks.navMenuFocus"';
		}
		$html .= '>';
		if ( '' !== $o['skip'] ) {
			$html .= '<a class="' . $c( 'nav-menu__skip' ) . '" href="#' . esc_attr( $o['skip'] ) . '">' . esc_html__( 'Skip to content', 'page-builder-sandwich' ) . '</a>';
		}
		if ( $script && 'never' !== $o['collapse'] ) {
			$toggle = '<button type="button" class="' . $c( 'nav-menu__toggle' ) . '" aria-expanded="false" aria-controls="' . esc_attr( $drawer ) . '" data-wp-bind--aria-expanded="context.drawer" data-wp-on--click="actions.navMenuDrawer">'
				. ( 'none' === $o['icon'] ? '' : self::glyph( $o['icon'] ) )
				. '<span class="' . $c( 'nav-menu__toggle-label', $o['showLabel'] ? '' : 'nav-menu__sr' ) . '">' . esc_html( $o['label'] ) . '</span></button>';
			/**
			 * Filters the Menu button's HTML: the Pro toggle bar designer puts it in a bar with a
			 * logo, a search box, links… (shown only while the menu is folded). A result that
			 * does not contain the button unchanged is ignored, so the menu can never lose it.
			 *
			 * @since 6.36.24
			 *
			 * @param string               $toggle     The Menu button.
			 * @param array<string, mixed> $attributes The block's attributes.
			 * @param string               $prefix     Class prefix.
			 */
			$bar   = apply_filters( 'pbsw_nav_menu_toggle', $toggle, $attributes, $prefix );
			$html .= is_string( $bar ) && str_contains( $bar, $toggle ) ? $bar : $toggle;
		}
		$html .= '<div class="' . $c( 'nav-menu__drawer' ) . '" id="' . esc_attr( $drawer ) . '"';
		if ( $script ) {
			$html .= ' data-wp-bind--data-open="context.drawer" data-wp-bind--role="state.navMenuDialogRole" data-wp-bind--aria-modal="state.navMenuDialogModal" data-wp-bind--aria-label="state.navMenuDialogLabel"';
		}
		$html .= '>';
		if ( $script && 'never' !== $o['collapse'] ) {
			$html .= '<button type="button" class="' . $c( 'nav-menu__close' ) . '" hidden data-wp-bind--hidden="!context.drawer" data-wp-on--click="actions.navMenuDrawer">'
				. self::glyph( 'close' ) . '<span class="' . $c( 'nav-menu__sr' ) . '">' . esc_html__( 'Close menu', 'page-builder-sandwich' ) . '</span></button>';
		}

		return $html . '<ul class="' . $c( 'nav-menu__list' ) . '">' . $items . '</ul></div></nav>';
	}

	/**
	 * One decorative glyph from nav-glyphs.json (drawn for this block; the editor reads the same file).
	 *
	 * @param string $name Glyph.
	 * @return string
	 */
	public static function glyph( string $name ): string {
		if ( null === self::$glyphs ) {
			$json         = json_decode( (string) file_get_contents( __DIR__ . '/nav-glyphs.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local file shipped with the plugin.
			self::$glyphs = is_array( $json ) ? $json : array();
		}
		$d = self::$glyphs[ $name ] ?? null;
		if ( ! is_string( $d ) ) {
			return '';
		}

		return '<svg viewBox="0 0 24 24" width="1em" height="1em" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="' . esc_attr( $d ) . '"/></svg>';
	}

	/**
	 * One of the allowed values, else the default (the first unless given).
	 *
	 * @param mixed              $value   Value.
	 * @param array<int, string> $allowed Allowed values.
	 * @param string             $fallback Default.
	 * @return string
	 */
	private static function choice( $value, array $allowed, string $fallback = '' ): string {
		return is_string( $value ) && in_array( $value, $allowed, true ) ? $value : ( '' === $fallback ? $allowed[0] : $fallback );
	}
}
