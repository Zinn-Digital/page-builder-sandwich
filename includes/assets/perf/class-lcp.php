<?php
/**
 * Largest-Contentful-Paint priority (pbs-p5).
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Assets\Perf;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * On a PBS page: the first large image above an estimated fold loads first
 * (`fetchpriority="high"`, never lazy), the other above-the-fold images load eagerly, and every
 * image below the fold is `loading="lazy"`. All images get `decoding="async"`.
 *
 * ⭐ WordPress's own rule is a COUNT (the first three content images are not lazy). A page
 * builder's first screen is a layout, not a count: a hero row with three columns of icons can
 * hold six images above the fold, and a long intro paragraph can push the first image below it.
 * So the fold is estimated from the blocks themselves, top-level block by top-level block, as
 * they render.
 *
 * ⭐ It cooperates with WordPress instead of replacing it: it only ever WRITES an attribute an
 * image does not already have, and WordPress's `wp_filter_content_tags` (the_content, priority
 * 12, after this) respects an existing `loading`/`fetchpriority`/`decoding` and adds nothing on
 * top. Only one element per page may be high priority, so it asks
 * `wp_high_priority_element_flag()` first (a theme's featured image may already have taken it)
 * and lowers the flag when it takes it.
 *
 * A hero row whose background is an image (the commonest legacy PBS first screen) has no `<img>`
 * to prioritise, so its URL is preloaded instead (`wp_preload_resources`).
 */
final class Lcp {

	/** Estimated fold, CSS px from the top of the content (filter `pbsw_lcp_fold`). */
	public const FOLD = 900;

	/** Assumed content width, CSS px, to scale image heights. */
	private const WIDTH = 1200;

	/**
	 * Per-render state: inside the queried post's content, and the running estimate.
	 *
	 * @var array{on: bool, y: int, dims: array<string, array{0: int, 1: int}>}
	 */
	private static array $run = array(
		'on'   => false,
		'y'    => 0,
		'dims' => array(),
	);

	/**
	 * The hero background this request reserved the high-priority slot for (per post id).
	 *
	 * @var array<int, string>
	 */
	private static array $hero = array();

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'the_content', array( self::class, 'open' ), 8 );
		add_filter( 'the_content', array( self::class, 'close' ), 11 );
		add_filter( 'render_block_data', array( self::class, 'mark_top_level' ), 10, 3 );
		add_filter( 'render_block', array( self::class, 'on_render_block' ), 40, 2 );
		add_filter( 'wp_preload_resources', array( self::class, 'preload_hero' ) );
	}

	/**
	 * `the_content` (8, before blocks render): start estimating when this is the queried PBS post.
	 *
	 * @param string|mixed $content Content.
	 * @return string|mixed
	 */
	public static function open( $content ) {
		$post            = get_post();
		self::$run       = array(
			'on'   => false,
			'y'    => 0,
			'dims' => array(),
		);
		$is_main         = is_singular() && $post instanceof \WP_Post && (int) get_queried_object_id() === (int) $post->ID;
		self::$run['on'] = $is_main && Perf::is_pbs_content( (string) $post->post_content );
		if ( self::$run['on'] ) {
			$state             = Page_Css::state( (int) $post->ID, $post );
			self::$run['dims'] = is_array( $state['dims'] ?? null ) ? $state['dims'] : array();
			// A block theme renders content BEFORE wp_head: reserve the slot for the hero first.
			self::reserve_hero( (int) $post->ID, $state );
		}

		return $content;
	}

	/**
	 * `the_content` (11): stop.
	 *
	 * @param string|mixed $content Content.
	 * @return string|mixed
	 */
	public static function close( $content ) {
		self::$run['on'] = false;

		return $content;
	}

	/**
	 * `render_block_data`: remember which blocks are top level (no parent).
	 *
	 * @param array<string, mixed> $parsed Parsed block.
	 * @param array<string, mixed> $source Source block.
	 * @param mixed                $parent_block Parent block or null.
	 * @return array<string, mixed>
	 */
	public static function mark_top_level( $parsed, $source = array(), $parent_block = null ) {
		if ( self::$run['on'] && is_array( $parsed ) && null === $parent_block ) {
			$parsed['pbswTopLevel'] = true;
		}

		return $parsed;
	}

	/**
	 * `render_block` (top-level blocks of the queried post): apply the attributes.
	 *
	 * @param string|mixed         $html  HTML.
	 * @param array<string, mixed> $block Parsed block.
	 * @return string|mixed
	 */
	public static function on_render_block( $html, $block ) {
		if ( ! self::$run['on'] || ! is_string( $html ) || empty( $block['pbswTopLevel'] ) ) {
			return $html;
		}
		/**
		 * How far down the content counts as the first screen, in CSS pixels from its top. Blocks
		 * starting above it are treated as above the fold: their images load eagerly and the
		 * likeliest LCP image gets `fetchpriority="high"`; blocks below it lazy-load. Raise it for a
		 * tall hero, lower it for a compact header.
		 *
		 * @since 6.1.0
		 *
		 * @param int $fold Estimated fold height in CSS pixels (Lcp::FOLD).
		 */
		$fold            = (int) apply_filters( 'pbsw_lcp_fold', self::FOLD );
		$above           = self::$run['y'] < $fold;
		$html            = self::apply( $html, $above, self::$run['dims'] );
		self::$run['y'] += self::estimate( $block );

		return $html;
	}

	/**
	 * The pure half: set loading attributes on every `<img>` in a top-level block's HTML.
	 *
	 * @param string                               $html  HTML.
	 * @param bool                                 $above Whether the block starts above the fold.
	 * @param array<string, array{0: int, 1: int}> $dims  Known sizes by URL.
	 * @return string
	 */
	public static function apply( string $html, bool $above, array $dims = array() ): string {
		if ( ! str_contains( $html, '<img' ) ) {
			return $html;
		}
		$tags = new \WP_HTML_Tag_Processor( $html );
		while ( $tags->next_tag( 'IMG' ) ) {
			if ( null === $tags->get_attribute( 'decoding' ) ) {
				$tags->set_attribute( 'decoding', 'async' );
			}
			$has_loading  = null !== $tags->get_attribute( 'loading' );
			$has_priority = null !== $tags->get_attribute( 'fetchpriority' );
			if ( ! $above ) {
				if ( ! $has_loading && ! $has_priority ) {
					$tags->set_attribute( 'loading', 'lazy' );
				}
				continue;
			}
			if ( ! $has_priority && ! $has_loading && self::is_large( $tags, $dims ) && self::take_high_priority() ) {
				$tags->set_attribute( 'fetchpriority', 'high' );
				continue;
			}
			if ( ! $has_loading ) {
				$tags->set_attribute( 'loading', 'eager' );
			}
		}

		return $tags->get_updated_html();
	}

	/**
	 * Estimated rendered height of a block, CSS px (desktop layout; columns side by side).
	 *
	 * @param array<string, mixed> $block Parsed block.
	 * @return int
	 */
	public static function estimate( array $block ): int {
		$name  = (string) ( $block['blockName'] ?? '' );
		$attrs = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
		$inner = array_values( array_filter( (array) ( $block['innerBlocks'] ?? array() ), 'is_array' ) );
		$html  = (string) ( $block['innerHTML'] ?? '' );
		// A PBS block keeps `style` as a CSS string; a CORE block keeps it as an object (colours,
		// spacing, `dimensions.minHeight`). Casting that object to a string warned on every page
		// with a styled core block (found by the kit import proof, lane L11).
		$style = $attrs['style'] ?? '';
		$min   = is_array( $style )
			? self::css_px( 'min-height:' . (string) ( is_array( $style['dimensions'] ?? null ) ? ( $style['dimensions']['minHeight'] ?? '' ) : '' ), 'min-height' )
			: self::css_px( (string) $style, 'min-height' );

		switch ( $name ) {
			case 'pbs/row':
				$tallest = 0;
				foreach ( $inner as $child ) {
					$tallest = max( $tallest, self::estimate( $child ) );
				}
				return max( $min, $tallest );
			case 'pbs/column':
			case 'core/group':
			case 'core/column':
				$sum = 0;
				foreach ( $inner as $child ) {
					$sum += self::estimate( $child );
				}
				return max( $min, $sum );
			case 'core/columns':
				$tallest = 0;
				foreach ( $inner as $child ) {
					$tallest = max( $tallest, self::estimate( $child ) );
				}
				return $tallest;
			case 'core/cover':
				return max( 430, (int) ( $attrs['minHeight'] ?? 0 ) );
			case 'core/spacer':
				return (int) ( $attrs['height'] ?? 100 );
			case 'core/heading':
				return 70;
			case 'core/paragraph':
				return 24 + 26 * max( 1, (int) ceil( strlen( wp_strip_all_tags( $html ) ) / 110 ) );
			case 'core/list':
				return 20 + 30 * max( 1, substr_count( $html, '<li' ) + count( $inner ) );
			case 'core/separator':
				return 50;
			case 'pbs/button':
			case 'pbs/icon':
				return 60;
		}
		if ( str_contains( $html, '<img' ) ) {
			return self::image_height( $html );
		}

		return in_array( $name, array( 'core/embed', 'core/video' ), true ) ? 420 : 120;
	}

	/**
	 * `wp_preload_resources`: preload the hero row's background image of the queried PBS post.
	 *
	 * @param array<int, array<string, string>>|mixed $resources Resources.
	 * @return array<int, array<string, string>>|mixed
	 */
	public static function preload_hero( $resources ) {
		if ( ! is_array( $resources ) || ! is_singular() ) {
			return $resources;
		}
		$post_id = (int) get_queried_object_id();
		$post    = get_post( $post_id );
		if ( ! $post instanceof \WP_Post || ! Perf::is_pbs_content( (string) $post->post_content ) ) {
			return $resources;
		}
		$hero = self::reserve_hero( $post_id, Page_Css::state( $post_id, $post ) );
		if ( null === $hero ) {
			return $resources;
		}
		$resources[] = array(
			'href'          => $hero,
			'as'            => 'image',
			'fetchpriority' => 'high',
		);

		return $resources;
	}

	/**
	 * Reserve the high-priority slot for a post's hero background (once per request).
	 *
	 * @param int                       $post_id Post ID.
	 * @param array<string, mixed>|null $state   Compiled state.
	 * @return string|null The hero URL when this request reserved the slot for it.
	 */
	private static function reserve_hero( int $post_id, ?array $state ): ?string {
		if ( isset( self::$hero[ $post_id ] ) ) {
			return '' === self::$hero[ $post_id ] ? null : self::$hero[ $post_id ];
		}
		$url                    = is_array( $state ) ? ( $state['hero'] ?? null ) : null;
		$ok                     = is_string( $url ) && '' !== $url && self::take_high_priority();
		self::$hero[ $post_id ] = $ok ? (string) $url : '';

		return $ok ? (string) $url : null;
	}

	/**
	 * Forget per-request state (tests).
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$hero = array();
		self::$run  = array(
			'on'   => false,
			'y'    => 0,
			'dims' => array(),
		);
	}

	/**
	 * The background-image URL of the first top-level block when it is a row (or its first column).
	 *
	 * @param array<int, array<string, mixed>> $blocks Parsed blocks.
	 * @return string|null
	 */
	public static function hero_image( array $blocks ): ?string {
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) || null === ( $block['blockName'] ?? null ) ) {
				continue; // Whitespace between blocks.
			}
			if ( 'pbs/row' !== $block['blockName'] ) {
				return null;
			}
			$url = self::background_url( (string) ( $block['attrs']['style'] ?? '' ) );
			if ( null === $url ) {
				$first = $block['innerBlocks'][0] ?? null;
				// The first child may be a core block, whose `style` is an object, not CSS text.
				$inner = is_array( $first ) ? ( $first['attrs']['style'] ?? '' ) : '';
				$url   = is_string( $inner ) ? self::background_url( $inner ) : null;
			}
			return $url;
		}

		return null;
	}

	/**
	 * A `background(-image)` url() in a declaration list, http(s) or root-relative only.
	 *
	 * @param string $style Declarations.
	 * @return string|null
	 */
	public static function background_url( string $style ): ?string {
		if ( 1 !== preg_match( '/background(?:-image)?\s*:[^;]*url\(\s*[\'"]?([^\'")]+)[\'"]?\s*\)/i', $style, $m ) ) {
			return null;
		}
		$url = trim( $m[1] );

		return 1 === preg_match( '#^(https?://|/)#i', $url ) ? $url : null;
	}

	/**
	 * Take the page's single high-priority slot, if WordPress has not given it away already.
	 *
	 * @return bool
	 */
	private static function take_high_priority(): bool {
		if ( ! function_exists( 'wp_high_priority_element_flag' ) || ! wp_high_priority_element_flag() ) {
			return false;
		}
		wp_high_priority_element_flag( false );

		return true;
	}

	/**
	 * Is the image at the processor large enough to be the LCP element?
	 *
	 * @param \WP_HTML_Tag_Processor               $tags Processor at an IMG.
	 * @param array<string, array{0: int, 1: int}> $dims Known sizes.
	 * @return bool
	 */
	private static function is_large( \WP_HTML_Tag_Processor $tags, array $dims ): bool {
		$w   = (int) $tags->get_attribute( 'width' );
		$h   = (int) $tags->get_attribute( 'height' );
		$src = $tags->get_attribute( 'src' );
		if ( ( $w <= 0 || $h <= 0 ) && is_string( $src ) ) {
			$size = $dims[ $src ] ?? Layout_Guard::from_file_name( $src );
			if ( null !== $size ) {
				list( $w, $h ) = $size;
			}
		}
		if ( ( $w <= 0 || $h <= 0 ) && 1 === preg_match( '/(?:^|\s)wp-image-(\d+)(?:\s|$)/', (string) $tags->get_attribute( 'class' ), $m ) ) {
			$meta = wp_get_attachment_metadata( (int) $m[1] ); // A library image: WordPress sizes it later (priority 12), so ask it now.
			$w    = (int) ( is_array( $meta ) ? ( $meta['width'] ?? 0 ) : 0 );
			$h    = (int) ( is_array( $meta ) ? ( $meta['height'] ?? 0 ) : 0 );
		}
		/** This filter is documented in wp-includes/media.php */
		$min = (int) apply_filters( 'wp_min_priority_img_pixels', 50000 ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core's own filter, so a site's setting is respected.

		return $w > 0 && $h > 0 && $w * $h >= $min;
	}

	/**
	 * Height of the first image in some HTML, scaled to the content width; 400 when unknown.
	 *
	 * @param string $html HTML.
	 * @return int
	 */
	private static function image_height( string $html ): int {
		if ( 1 === preg_match( '/<img\b[^>]*>/i', $html, $img )
			&& 1 === preg_match( '/\swidth=["\']?(\d+)/i', $img[0], $w )
			&& 1 === preg_match( '/\sheight=["\']?(\d+)/i', $img[0], $h )
			&& (int) $w[1] > 0 && (int) $h[1] > 0 ) {
			return (int) round( (int) $h[1] * min( 1.0, self::WIDTH / (int) $w[1] ) );
		}

		return 400;
	}

	/**
	 * A px value of a property in a declaration list, 0 when absent or not px.
	 *
	 * @param string $style    Declarations.
	 * @param string $property Property.
	 * @return int
	 */
	private static function css_px( string $style, string $property ): int {
		return 1 === preg_match( '/(?:^|;)\s*' . preg_quote( $property, '/' ) . '\s*:\s*(\d+(?:\.\d+)?)px/i', $style, $m ) ? (int) $m[1] : 0;
	}
}
