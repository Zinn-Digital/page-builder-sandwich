<?php
/**
 * Where the compiled design rules reach the page (pbs-p4).
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Design;

use ZinnDigital\PBS\Assets\Perf\Page_Css;
use ZinnDigital\PBS\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * - Adds the `pbs` attribute to every block type registered on the server (the editor receives
 *   server definitions, so core blocks get it in JS too; src/design/attribute.js covers
 *   client-only blocks).
 * - One `render_block` filter adds `<p>-s-<id>`, `<p>-g-<slug>` and `<p>-hide-<bp>` to the first
 *   tag of a block's rendered HTML. Saved content is never touched, so core blocks stay valid
 *   and switching the plugin off leaves clean markup.
 * - The rules go into the page's compiled sheet (Page_Css, hook `pbsw_page_css_extra`). Blocks
 *   the sheet does not cover — a template part, a widget area, another post in a query loop —
 *   are collected as they render and delivered as ONE inline stylesheet in `<head>` (block
 *   themes render the template before `wp_head`; archives are pre-scanned), and whatever renders
 *   after the head as ONE more in the footer (WordPress prints late styles there). Never one per
 *   block, never an inline `style` attribute.
 */
final class Styles {

	/**
	 * Blocks rendered on this request whose rules are not in the page sheet and not printed yet.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private static array $pending = array();

	/**
	 * Element ids (and `hide:<bp>` keys) already delivered on this request.
	 *
	 * @var array<string, bool>
	 */
	private static array $delivered = array();

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'register_block_type_args', array( self::class, 'add_attribute' ) );
		add_filter( 'render_block', array( self::class, 'render_block' ), 10, 2 );
		add_filter( 'pbsw_page_css_extra', array( self::class, 'page_css' ), 10, 3 );
		add_filter( 'pbsw_page_css_state', array( self::class, 'page_state' ), 10, 2 );
		add_filter( 'pbsw_page_css_signature', array( self::class, 'signature' ) );
		add_action( 'pbsw_page_css_enqueued', array( self::class, 'on_page_css' ), 10, 2 );
		add_action( 'template_redirect', array( self::class, 'prescan_main_query' ), 3 );
		add_action( 'wp_enqueue_scripts', array( self::class, 'print_pending' ), 100 );
		add_action( 'wp_footer', array( self::class, 'print_pending' ), 5 );
	}

	/**
	 * `register_block_type_args`: every block type carries the `pbs` object attribute.
	 *
	 * @param array<string, mixed>|mixed $args Block type args.
	 * @return array<string, mixed>|mixed
	 */
	public static function add_attribute( $args ) {
		if ( ! is_array( $args ) ) {
			return $args;
		}
		$attrs = is_array( $args['attributes'] ?? null ) ? $args['attributes'] : array();
		if ( ! isset( $attrs['pbs'] ) ) {
			$attrs['pbs']       = array( 'type' => 'object' );
			$args['attributes'] = $attrs;
		}

		return $args;
	}

	// ── Render ───────────────────────────────────────────────────────────────────────────────

	/**
	 * The classes a block's `pbs` attribute puts on its first tag.
	 *
	 * @param array<string, mixed>  $block   Parsed block.
	 * @param string                $prefix  Prefix.
	 * @param array<string, string> $globals Global class id → slug (Pro, d5).
	 * @return array<int, string>
	 */
	public static function classes_for( array $block, string $prefix, array $globals = array() ): array {
		$pbs = $block['attrs']['pbs'] ?? null;
		if ( ! is_array( $pbs ) ) {
			return array();
		}
		$out = array();
		if ( Compiler::has_styles( $block, $prefix ) ) {
			$out[] = $prefix . '-s-' . $pbs['id'];
		}
		foreach ( is_array( $pbs['cls'] ?? null ) ? $pbs['cls'] : array() as $gid ) {
			if ( is_string( $gid ) && isset( $globals[ $gid ] ) ) {
				$out[] = $prefix . '-g-' . $globals[ $gid ];
			}
		}
		foreach ( Compiler::hides( $pbs, Breakpoints::ids() ) as $bp ) {
			$out[] = $prefix . '-hide-' . $bp;
		}

		return $out;
	}

	/**
	 * Add classes to the first tag of an HTML fragment.
	 *
	 * @param string             $html    HTML.
	 * @param array<int, string> $classes Classes.
	 * @return string
	 */
	public static function add_classes( string $html, array $classes ): string {
		if ( array() === $classes || ! class_exists( '\WP_HTML_Tag_Processor' ) ) {
			return $html;
		}
		$tags = new \WP_HTML_Tag_Processor( $html );
		if ( ! $tags->next_tag() ) {
			return $html;
		}
		foreach ( $classes as $class ) {
			$tags->add_class( $class );
		}

		return $tags->get_updated_html();
	}

	/**
	 * `render_block`.
	 *
	 * @param string|mixed         $html  Rendered HTML.
	 * @param array<string, mixed> $block Parsed block.
	 * @return string|mixed
	 */
	public static function render_block( $html, $block ) {
		if ( ! is_string( $html ) || '' === $html || ! is_array( $block ) || ! is_array( $block['attrs']['pbs'] ?? null ) ) {
			return $html;
		}
		$prefix  = Settings::prefix();
		$classes = self::classes_for( $block, $prefix, self::global_slugs() );
		if ( array() === $classes ) {
			return $html;
		}
		self::remember( $block, $prefix );

		return self::add_classes( $html, $classes );
	}

	/**
	 * Global class id → slug, from the premium option `pbsw_classes` (d5).
	 *
	 * @return array<string, string>
	 */
	public static function global_slugs(): array {
		static $cache = null;
		if ( null === $cache ) {
			$cache = array();
			$rows  = get_option( 'pbsw_classes', array() );
			foreach ( is_array( $rows ) ? $rows : array() as $row ) {
				if ( is_array( $row ) && is_string( $row['id'] ?? null ) && is_string( $row['slug'] ?? null ) && 1 === preg_match( '/^[a-z0-9][a-z0-9-]{0,63}$/', $row['slug'] ) ) {
					$cache[ $row['id'] ] = $row['slug'];
				}
			}
		}

		return $cache;
	}

	/**
	 * Queue a rendered block's rules unless they were delivered already on this request.
	 *
	 * @param array<string, mixed> $block  Parsed block.
	 * @param string               $prefix Prefix.
	 * @return void
	 */
	private static function remember( array $block, string $prefix ): void {
		$pbs  = (array) $block['attrs']['pbs'];
		$id   = is_string( $pbs['id'] ?? null ) ? $pbs['id'] : '';
		$need = false;
		if ( 1 === preg_match( Compiler::ID_PATTERN, $id ) && ! isset( self::$delivered[ $id ] ) && Compiler::has_styles( $block, $prefix ) ) {
			self::$delivered[ $id ] = true;
			$need                   = true;
		}
		$hides = array();
		foreach ( Compiler::hides( $pbs, Breakpoints::ids() ) as $bp ) {
			if ( ! isset( self::$delivered[ 'hide:' . $bp ] ) ) {
				self::$delivered[ 'hide:' . $bp ] = true;
				$hides[]                          = $bp;
			}
		}
		if ( ! $need && array() === $hides ) {
			return;
		}
		$copy                         = $block;
		$copy['innerBlocks']          = array(); // Inner blocks queue themselves when they render.
		$copy['attrs']['pbs']['hide'] = $hides;
		if ( ! $need ) {
			unset( $copy['attrs']['pbs']['s'], $copy['attrs']['pbs']['css'] );
		}
		self::$pending[] = $copy;
	}

	/**
	 * `wp_enqueue_scripts` (late — it runs inside wp_head, after a block theme rendered the
	 * template) and `wp_footer` (early — before WordPress prints late styles): enqueue everything
	 * queued so far as ONE inline stylesheet under a neutral handle.
	 *
	 * @return void
	 */
	public static function print_pending(): void {
		if ( array() === self::$pending ) {
			return;
		}
		$prefix        = Settings::prefix();
		$css           = Compiler::css( self::$pending, $prefix );
		self::$pending = array();
		if ( '' === $css ) {
			return;
		}
		$handle = $prefix . '-' . substr( sha1( $css ), 0, 8 );
		wp_register_style( $handle, false, array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- inline only.
		wp_enqueue_style( $handle );
		// The compiler emits only validated values; this makes a closing tag impossible anyway.
		wp_add_inline_style( $handle, str_replace( '</', '<\\/', $css ) );
	}

	/**
	 * `template_redirect`: archives, the blog index and search — compile the main query's posts
	 * now so their rules print in `<head>` instead of after the content.
	 *
	 * @return void
	 */
	public static function prescan_main_query(): void {
		if ( is_singular() ) {
			return;
		}
		global $wp_query;
		$prefix = Settings::prefix();
		foreach ( $wp_query instanceof \WP_Query ? (array) $wp_query->posts : array() as $post ) {
			if ( $post instanceof \WP_Post && str_contains( (string) $post->post_content, '"pbs":{' ) ) {
				self::queue_tree( parse_blocks( (string) $post->post_content ), $prefix );
			}
		}
	}

	/**
	 * Queue every styled block of a tree.
	 *
	 * @param array<int, mixed> $blocks Parsed blocks.
	 * @param string            $prefix Prefix.
	 * @return void
	 */
	private static function queue_tree( array $blocks, string $prefix ): void {
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			if ( is_array( $block['attrs']['pbs'] ?? null ) ) {
				self::remember( $block, $prefix );
			}
			self::queue_tree( is_array( $block['innerBlocks'] ?? null ) ? $block['innerBlocks'] : array(), $prefix );
		}
	}

	// ── The per-page sheet ────────────────────────────────────────────────────────────────────

	/**
	 * `pbsw_page_css_extra`: the design rules of the page's blocks.
	 *
	 * @param string|mixed                     $css    CSS so far.
	 * @param array<int, array<string, mixed>> $blocks Parsed blocks (synced patterns expanded).
	 * @param string                           $prefix Prefix.
	 * @return string
	 */
	public static function page_css( $css, $blocks = array(), $prefix = '' ): string {
		return (string) $css . Compiler::css( is_array( $blocks ) ? $blocks : array(), (string) $prefix );
	}

	/**
	 * `pbsw_page_css_state`: remember which element ids and hide rules the sheet carries, so the
	 * render filter does not print them a second time.
	 *
	 * @param array<string, mixed>|mixed       $state  State.
	 * @param array<int, array<string, mixed>> $blocks Parsed blocks (synced patterns expanded).
	 * @return array<string, mixed>|mixed
	 */
	public static function page_state( $state, $blocks = array() ) {
		if ( ! is_array( $state ) ) {
			return $state;
		}
		$ids   = array();
		$hides = array();
		self::collect( is_array( $blocks ) ? $blocks : array(), $ids, $hides );
		$state['design'] = array(
			'ids'  => array_keys( $ids ),
			'hide' => array_keys( $hides ),
		);

		return $state;
	}

	/**
	 * Element ids with rules, and hidden breakpoints, in a tree.
	 *
	 * @param array<int, mixed>   $blocks Parsed blocks.
	 * @param array<string, bool> $ids    Ids (by reference).
	 * @param array<string, bool> $hides  Breakpoints (by reference).
	 * @return void
	 */
	private static function collect( array $blocks, array &$ids, array &$hides ): void {
		$prefix = Settings::prefix();
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			$pbs = $block['attrs']['pbs'] ?? null;
			if ( is_array( $pbs ) ) {
				if ( Compiler::has_styles( $block, $prefix ) ) {
					$ids[ (string) $pbs['id'] ] = true;
				}
				foreach ( Compiler::hides( $pbs, Breakpoints::ids() ) as $bp ) {
					$hides[ $bp ] = true;
				}
			}
			self::collect( is_array( $block['innerBlocks'] ?? null ) ? $block['innerBlocks'] : array(), $ids, $hides );
		}
	}

	/**
	 * `pbsw_page_css_signature`: a sheet goes stale when the breakpoints change.
	 *
	 * @param string|mixed $signature Signature so far.
	 * @return string
	 */
	public static function signature( $signature ): string {
		return (string) $signature . '|bp:' . Breakpoints::hash();
	}

	/**
	 * `pbsw_page_css_enqueued`: what the page sheet already delivers.
	 *
	 * @param string               $handle Style handle.
	 * @param array<string, mixed> $state  State.
	 * @return void
	 */
	public static function on_page_css( $handle, $state ): void {
		$design = is_array( $state ) && is_array( $state['design'] ?? null ) ? $state['design'] : array();
		foreach ( (array) ( $design['ids'] ?? array() ) as $id ) {
			self::$delivered[ (string) $id ] = true;
		}
		foreach ( (array) ( $design['hide'] ?? array() ) as $bp ) {
			self::$delivered[ 'hide:' . $bp ] = true;
		}
	}

	/**
	 * Forget this request's state (tests).
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$pending   = array();
		self::$delivered = array();
	}
}
