<?php
/**
 * Layout-shift guard (pbs-p4).
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Assets\Perf;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Makes images and videos on a PBS page reserve their space before they load.
 *
 * An `<img>` or `<video>` with no `width`/`height` is laid out at 0×0 and then jumps when its
 * bytes arrive — the commonest cause of Cumulative Layout Shift, and exactly what legacy content
 * carries (a converted legacy image is a `core/html` block with a bare `<img src>`). WordPress
 * itself fills in dimensions only for an `<img>` carrying a `wp-image-<id>` class; this fills in
 * the rest:
 *
 * 1. On save (and when a stale state is rebuilt) every dimension-less `<img>`/`<video>` source is resolved to
 *    its attachment's real size and stored in the page's compiled state (Page_Css `dims`).
 * 2. A WordPress-sized file name (`photo-300x200.jpg`) carries its own size and needs no lookup.
 * 3. At render, the_content (priority 11 — after blocks and shortcodes, before WordPress's own
 *    `wp_filter_content_tags` at 12, so its loading optimisation sees the dimensions) adds
 *    `width`/`height` plus `height:auto`, so a responsive image keeps its aspect ratio.
 *
 * An image that cannot be resolved (a remote URL) is left alone, and the editor warns about it
 * instead (src/core/perf). Embeds are sized by WordPress's own aspect-ratio wrapper, and an
 * `<iframe>` without dimensions is a fixed 300×150 box that never shifts.
 */
final class Layout_Guard {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'the_content', array( self::class, 'filter_content' ), 11 );
	}

	/**
	 * `the_content`: add dimensions on a PBS post.
	 *
	 * @param string|mixed $content Rendered content.
	 * @return string|mixed
	 */
	public static function filter_content( $content ) {
		$post = get_post();
		if ( ! is_string( $content ) || '' === $content || ! $post instanceof \WP_Post
			|| ! Perf::is_pbs_content( (string) $post->post_content ) ) {
			return $content;
		}
		$state = Page_Css::state( (int) $post->ID, $post );

		return self::add_dimensions( $content, is_array( $state['dims'] ?? null ) ? $state['dims'] : array() );
	}

	/**
	 * The pure half: give every `<img>`/`<video>` without both dimensions its known size.
	 *
	 * @param string                               $html HTML.
	 * @param array<string, array{0: int, 1: int}> $dims Known sizes by source URL.
	 * @return string
	 */
	public static function add_dimensions( string $html, array $dims ): string {
		if ( ! str_contains( $html, '<img' ) && ! str_contains( $html, '<video' ) ) {
			return $html;
		}
		$tags = new \WP_HTML_Tag_Processor( $html );
		while ( $tags->next_tag() ) {
			$tag = $tags->get_tag();
			if ( 'IMG' !== $tag && 'VIDEO' !== $tag ) {
				continue;
			}
			if ( ( null !== $tags->get_attribute( 'width' ) && null !== $tags->get_attribute( 'height' ) ) || self::core_sizes( $tags ) ) {
				continue;
			}
			$src  = $tags->get_attribute( 'src' );
			$size = is_string( $src ) ? ( $dims[ $src ] ?? self::from_file_name( $src ) ) : null;
			if ( null === $size ) {
				continue;
			}
			$tags->set_attribute( 'width', (string) $size[0] );
			$tags->set_attribute( 'height', (string) $size[1] );
			$style = (string) $tags->get_attribute( 'style' );
			if ( 1 !== preg_match( '/(^|;)\s*height\s*:/i', $style ) ) {
				$tags->set_attribute( 'style', ( '' === trim( $style ) ? '' : rtrim( trim( $style ), ';' ) . ';' ) . 'height:auto' );
			}
		}

		return $tags->get_updated_html();
	}

	/**
	 * Does WordPress size this image itself (an `<img>` with a `wp-image-<id>` class)?
	 *
	 * @param \WP_HTML_Tag_Processor $tags Processor at an element.
	 * @return bool
	 */
	private static function core_sizes( \WP_HTML_Tag_Processor $tags ): bool {
		return 'IMG' === $tags->get_tag() && 1 === preg_match( '/(?:^|\s)wp-image-\d+(?:\s|$)/', (string) $tags->get_attribute( 'class' ) );
	}

	/**
	 * A WordPress intermediate size's own dimensions: `name-300x200.jpg` → [300, 200].
	 *
	 * @param string $src URL.
	 * @return array{0: int, 1: int}|null
	 */
	public static function from_file_name( string $src ): ?array {
		$path = (string) wp_parse_url( $src, PHP_URL_PATH );
		if ( 1 === preg_match( '/-(\d{1,5})x(\d{1,5})\.(?:jpe?g|png|gif|webp|avif)$/i', $path, $m ) && (int) $m[1] > 0 && (int) $m[2] > 0 ) {
			return array( (int) $m[1], (int) $m[2] );
		}

		return null;
	}

	/**
	 * On save: resolve every dimension-less image/video source in the blocks to its size.
	 *
	 * @param array<int, array<string, mixed>> $blocks Parsed blocks.
	 * @return array<string, array{0: int, 1: int}>
	 */
	public static function resolve_dims( array $blocks ): array {
		$sources = array();
		self::collect_sources( $blocks, $sources );
		$dims = array();
		foreach ( array_keys( $sources ) as $src ) {
			if ( null !== self::from_file_name( $src ) ) {
				continue; // Carries its own size; no query.
			}
			$size = self::attachment_size( $src );
			if ( null !== $size ) {
				$dims[ $src ] = $size;
			}
		}

		return $dims;
	}

	/**
	 * Every `<img>`/`<video>` source without both dimensions in the blocks' saved HTML.
	 *
	 * @param array<int, array<string, mixed>> $blocks  Parsed blocks.
	 * @param array<string, bool>              $sources Found sources (by reference).
	 * @return void
	 */
	private static function collect_sources( array $blocks, array &$sources ): void {
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			$html = (string) ( $block['innerHTML'] ?? '' );
			if ( str_contains( $html, '<img' ) || str_contains( $html, '<video' ) ) {
				$tags = new \WP_HTML_Tag_Processor( $html );
				while ( $tags->next_tag() ) {
					if ( ! in_array( $tags->get_tag(), array( 'IMG', 'VIDEO' ), true ) ) {
						continue;
					}
					$src = $tags->get_attribute( 'src' );
					if ( is_string( $src ) && '' !== $src && ! self::core_sizes( $tags ) && ( null === $tags->get_attribute( 'width' ) || null === $tags->get_attribute( 'height' ) ) ) {
						$sources[ $src ] = true;
					}
				}
			}
			self::collect_sources( (array) ( $block['innerBlocks'] ?? array() ), $sources );
		}
	}

	/**
	 * The size of the local attachment a URL points at.
	 *
	 * @param string $src URL.
	 * @return array{0: int, 1: int}|null
	 */
	private static function attachment_size( string $src ): ?array {
		$id = attachment_url_to_postid( $src );
		if ( $id <= 0 ) {
			return null;
		}
		$meta = wp_get_attachment_metadata( $id );
		$w    = (int) ( is_array( $meta ) ? ( $meta['width'] ?? 0 ) : 0 );
		$h    = (int) ( is_array( $meta ) ? ( $meta['height'] ?? 0 ) : 0 );

		return $w > 0 && $h > 0 ? array( $w, $h ) : null;
	}
}
