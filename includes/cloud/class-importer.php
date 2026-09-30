<?php
/**
 * Imports a website kit (pbs-kit/1) into this site: its pages, its front page, its menu, its design.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Cloud;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One kit document in, real WordPress pages out (features pbs-c2, pbs-r1, pbs-r2).
 *
 * ⭐ Idempotent per kit and page: a page this importer created is remembered by two meta keys
 * (kit slug + page slug), so importing the same kit twice UPDATES those pages instead of creating
 * "About 2". A page the site owner made themselves is never touched: a slug clash gets a new,
 * unique slug from WordPress, and the kit's internal links are rewritten to wherever each page
 * really landed.
 *
 * ⛔ Nothing here runs without a person asking: the REST route that calls it needs
 * `edit_pages` + `publish_pages` (`Kits::can_import`), and pages are created as DRAFTS unless the
 * person ticks "Publish". A kit never replaces the front page unless asked.
 */
final class Importer {

	/** Meta key: the kit a page was imported from. */
	public const META_KIT = '_pbsw_kit';

	/** Meta key: the kit page slug a page was imported from. */
	public const META_PAGE = '_pbsw_kit_page';

	/**
	 * Import a kit.
	 *
	 * @param array<string, mixed> $kit     The pbs-kit/1 document.
	 * @param array<string, mixed> $options pages (string[] of kit page slugs, empty = all),
	 *                                      publish (bool), front (bool), menu (bool).
	 * @return array{pages: array<int, array{slug: string, id: int, url: string, created: bool}>, front: int, menu: int}
	 */
	public static function import( array $kit, array $options ): array {
		$wanted = array_values( array_filter( array_map( 'strval', (array) ( $options['pages'] ?? array() ) ) ) );
		$status = ! empty( $options['publish'] ) ? 'publish' : 'draft';
		$slug   = sanitize_key( (string) ( $kit['slug'] ?? '' ) );
		$pages  = array_values(
			array_filter(
				(array) ( $kit['pages'] ?? array() ),
				static fn( $p ): bool => is_array( $p ) && ( array() === $wanted || in_array( (string) ( $p['slug'] ?? '' ), $wanted, true ) )
			)
		);

		// Pass 1: every page exists (so pass 2 can link to any of them).
		$ids = array();
		$out = array();
		foreach ( $pages as $page ) {
			$page_slug = sanitize_title( (string) ( $page['slug'] ?? '' ) );
			if ( '' === $page_slug ) {
				continue;
			}
			$existing = self::find( $slug, $page_slug );
			$postarr  = array(
				'post_type'   => 'page',
				'post_title'  => sanitize_text_field( (string) ( $page['title'] ?? $page_slug ) ),
				'post_status' => $status,
				'post_name'   => $page_slug,
			);
			if ( $existing ) {
				$postarr['ID'] = $existing;
				$id            = wp_update_post( wp_slash( $postarr ), true );
			} else {
				$postarr['post_content'] = '';
				$id                      = wp_insert_post( wp_slash( $postarr ), true );
			}
			if ( is_wp_error( $id ) || ! $id ) {
				continue;
			}
			update_post_meta( (int) $id, self::META_KIT, $slug );
			update_post_meta( (int) $id, self::META_PAGE, $page_slug );
			$ids[ $page_slug ] = (int) $id;
			$out[]             = array(
				'slug'    => $page_slug,
				'id'      => (int) $id,
				'url'     => '',
				'created' => ! $existing,
			);
		}

		// Pass 2: the content, with the kit's "/<page>/" links pointing at the real pages.
		$map = array();
		foreach ( $ids as $page_slug => $id ) {
			$map[ '/' . $page_slug . '/' ] = (string) get_permalink( $id );
		}
		foreach ( $pages as $page ) {
			$page_slug = sanitize_title( (string) ( $page['slug'] ?? '' ) );
			if ( ! isset( $ids[ $page_slug ] ) ) {
				continue;
			}
			$content = self::relink( (string) ( $page['content'] ?? '' ), $map );
			wp_update_post(
				wp_slash(
					array(
						'ID'           => $ids[ $page_slug ],
						'post_content' => $content,
					)
				)
			);
		}
		foreach ( $out as $i => $row ) {
			$out[ $i ]['url'] = (string) get_permalink( $row['id'] );
		}

		$front = 0;
		if ( ! empty( $options['front'] ) ) {
			foreach ( $pages as $page ) {
				$page_slug = sanitize_title( (string) ( $page['slug'] ?? '' ) );
				if ( ! empty( $page['front'] ) && isset( $ids[ $page_slug ] ) ) {
					$front = $ids[ $page_slug ];
					if ( 'publish' !== get_post_status( $front ) ) {
						wp_publish_post( $front );
					}
					update_option( 'show_on_front', 'page' );
					update_option( 'page_on_front', $front );
				}
			}
		}

		$menu = ! empty( $options['menu'] ) ? self::menu( (string) ( $kit['name'] ?? $slug ), (array) ( $kit['menu'] ?? array() ), $ids ) : 0;

		/**
		 * Fires after a kit was imported.
		 *
		 * @param array<string, mixed> $kit  The kit document.
		 * @param array<string, int>   $ids  Kit page slug → page id.
		 * @param int                  $front The page set as front page, 0 for none.
		 */
		do_action( 'pbsw_kit_imported', $kit, $ids, $front );

		return array(
			'pages' => $out,
			'front' => $front,
			'menu'  => $menu,
		);
	}

	/**
	 * The page this importer made earlier for (kit, page), or 0.
	 *
	 * @param string $kit  Kit slug.
	 * @param string $page Kit page slug.
	 * @return int
	 */
	public static function find( string $kit, string $page ): int {
		$found = get_posts(
			array(
				'post_type'        => 'page',
				'post_status'      => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'numberposts'      => 1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- one indexed lookup per imported page, only when a person imports a kit.
				'meta_query'       => array(
					'relation' => 'AND',
					array(
						'key'   => self::META_KIT,
						'value' => $kit,
					),
					array(
						'key'   => self::META_PAGE,
						'value' => $page,
					),
				),
			)
		);

		return $found ? (int) $found[0] : 0;
	}

	/**
	 * Point the kit's internal links ("/about/") at the pages that were really created.
	 *
	 * ⛔ Only href values that are EXACTLY a kit page path are rewritten; an external link, or a
	 * path that merely starts the same way, is left alone.
	 *
	 * @param string                $content Block markup.
	 * @param array<string, string> $map     "/slug/" → permalink.
	 * @return string
	 */
	public static function relink( string $content, array $map ): string {
		if ( array() === $map ) {
			return $content;
		}
		$replace = static function ( array $m ) use ( $map ): string {
			$path = $m[2];
			// esc_attr( esc_url_raw() ): the same `&amp;` a block's save() writes, so the block stays valid.
			return isset( $map[ $path ] ) ? $m[1] . esc_attr( esc_url_raw( $map[ $path ] ) ) . $m[3] : $m[0];
		};
		// In the saved HTML: href="/about/".
		$content = (string) preg_replace_callback( '#(href=")(/[a-z0-9-]+/)(")#', $replace, $content );
		// In block-comment JSON attributes: "url":"/about/" (buttons, call to action, offers).
		$content = (string) preg_replace_callback(
			'#("(?:url|primaryUrl|secondaryUrl|buttonUrl|linkUrl)":")(/[a-z0-9-]+/)(")#',
			static function ( array $m ) use ( $map ): string {
				return isset( $map[ $m[2] ] ) ? $m[1] . str_replace( '/', '\\/', esc_url_raw( $map[ $m[2] ] ) ) . $m[3] : $m[0];
			},
			$content
		);

		return $content;
	}

	/**
	 * A navigation menu (block themes: a wp_navigation post; classic themes: a nav menu).
	 *
	 * @param string             $name  Kit name.
	 * @param array<int, string> $order Kit page slugs in menu order.
	 * @param array<string, int> $ids   Kit page slug → page id.
	 * @return int The navigation post or menu id, 0 when nothing was made.
	 */
	private static function menu( string $name, array $order, array $ids ): int {
		$items = array();
		foreach ( $order as $page_slug ) {
			$page_slug = sanitize_title( (string) $page_slug );
			if ( isset( $ids[ $page_slug ] ) ) {
				$items[] = $ids[ $page_slug ];
			}
		}
		if ( array() === $items ) {
			return 0;
		}
		if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
			$links = '';
			foreach ( $items as $id ) {
				$links .= '<!-- wp:navigation-link ' . wp_json_encode(
					array(
						'label' => get_the_title( $id ),
						'type'  => 'page',
						'id'    => $id,
						'url'   => get_permalink( $id ),
						'kind'  => 'post-type',
					)
				) . ' /-->';
			}
			$id = wp_insert_post(
				wp_slash(
					array(
						'post_type'    => 'wp_navigation',
						'post_title'   => $name,
						'post_status'  => 'publish',
						'post_content' => $links,
					)
				),
				true
			);

			return is_wp_error( $id ) ? 0 : (int) $id;
		}
		$menu_id = wp_create_nav_menu( $name . ' ' . wp_date( 'Y-m-d H:i' ) );
		if ( is_wp_error( $menu_id ) ) {
			return 0;
		}
		foreach ( $items as $id ) {
			wp_update_nav_menu_item(
				(int) $menu_id,
				0,
				array(
					'menu-item-object-id' => $id,
					'menu-item-object'    => 'page',
					'menu-item-type'      => 'post_type',
					'menu-item-status'    => 'publish',
				)
			);
		}

		return (int) $menu_id;
	}
}
