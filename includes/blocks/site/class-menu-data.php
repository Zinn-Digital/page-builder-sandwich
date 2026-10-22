<?php
/**
 * What `pbs/nav-menu` reads from WordPress, behind one seam: Site\Nav only formats, and the unit
 * bar swaps this for a fixed menu. Real WordPress is proven by the browser leg
 * (wp/tests/e2e/pbs-blocks/blocks/nav-menu.mjs).
 *
 * Free since 6.36.22 (NAV1, owner ruling 2026-10-19 "Basic free, mega Pro"): moved here
 * unchanged from the Pro site data, plus each item's description (shown by the Pro item options).
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Blocks\Site;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Menu data.
 */
class Menu_Data {

	/**
	 * A navigation menu's items, as a tree — through the same filters core's wp_nav_menu() runs,
	 * so a multilingual plugin (Tranzly, WPML, Polylang) or any other plugin that changes menu items
	 * changes this menu too: `wp_nav_menu_args` (which may swap the menu), `wp_nav_menu_objects`
	 * (the item list), and per item `nav_menu_item_title` and `nav_menu_link_attributes` (its
	 * label, address and target). An item a filter removes is not shown.
	 *
	 * @param int $menu_id Menu term ID.
	 * @return array<int, array{id: int, title: string, url: string, current: bool, target: string, desc: string, children: array<int, mixed>}>
	 */
	public function menu( int $menu_id ): array {
		if ( $menu_id <= 0 ) {
			return array();
		}
		// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core's own menu filters, applied as wp_nav_menu() applies them.
		$args = (object) apply_filters(
			'wp_nav_menu_args',
			array(
				'menu'           => $menu_id,
				'container'      => 'nav',
				'menu_class'     => 'nav-menu__list',
				'echo'           => false,
				'fallback_cb'    => false,
				'depth'          => 3,
				'theme_location' => '',
				'item_spacing'   => 'discard',
				'pbs_block'      => 'pbs/nav-menu',
			)
		);
		$menu = wp_get_nav_menu_object( $args->menu ?? $menu_id );
		if ( ! $menu instanceof \WP_Term ) {
			return array();
		}
		$args->menu = $menu;
		$items      = wp_get_nav_menu_items( $menu->term_id, array( 'update_post_term_cache' => false ) );
		if ( ! is_array( $items ) ) {
			return array();
		}
		_wp_menu_item_classes_by_context( $items );
		$sorted = array();
		foreach ( $items as $item ) {
			$sorted[ (int) $item->menu_order ] = $item;
		}
		$sorted = apply_filters( 'wp_nav_menu_objects', $sorted, $args );
		if ( ! is_array( $sorted ) ) {
			return array();
		}
		$parents = array();
		foreach ( $sorted as $item ) {
			$parents[ (int) $item->ID ] = (int) $item->menu_item_parent;
		}
		$flat = array();
		foreach ( $sorted as $item ) {
			$depth  = self::depth( (int) $item->ID, $parents );
			$title  = (string) apply_filters( 'the_title', $item->title, $item->ID );
			$title  = (string) apply_filters( 'nav_menu_item_title', $title, $item, $args, $depth );
			$atts   = apply_filters(
				'nav_menu_link_attributes',
				array(
					'title'        => (string) ( $item->attr_title ?? '' ),
					'target'       => (string) ( $item->target ?? '' ),
					'rel'          => (string) ( $item->xfn ?? '' ),
					'href'         => (string) $item->url,
					'aria-current' => ! empty( $item->current ) ? 'page' : '',
				),
				$item,
				$args,
				$depth
			);
			$atts   = is_array( $atts ) ? $atts : array();
			$flat[] = array(
				'id'      => (int) $item->ID,
				'parent'  => (int) $item->menu_item_parent,
				'title'   => wp_strip_all_tags( $title ),
				'url'     => (string) ( $atts['href'] ?? $item->url ),
				'target'  => '_blank' === ( $atts['target'] ?? '' ) ? '_blank' : '',
				'current' => ! empty( $item->current ),
				// The item's "Description" (Appearance › Menus › Screen Options), plain text.
				'desc'    => trim( wp_strip_all_tags( (string) ( $item->description ?? '' ) ) ),
			);
		}
		// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

		return self::tree( $flat );
	}

	/**
	 * An item's depth (0 = top level), from the parent of every item. Pure.
	 *
	 * @param int            $id      Item.
	 * @param array<int,int> $parents Item => parent item (0 = none).
	 * @return int
	 */
	public static function depth( int $id, array $parents ): int {
		$depth = 0;
		$seen  = array( $id => true );
		$id    = $parents[ $id ] ?? 0;
		while ( $id > 0 && isset( $parents[ $id ] ) && ! isset( $seen[ $id ] ) ) {
			$seen[ $id ] = true;
			++$depth;
			$id = $parents[ $id ];
		}

		return $depth;
	}

	/**
	 * The site's menus, for the editor's picker.
	 *
	 * @return array<int, array{id: int, name: string}>
	 */
	public function menus(): array {
		$out = array();
		foreach ( wp_get_nav_menus() as $menu ) {
			$out[] = array(
				'id'   => (int) $menu->term_id,
				'name' => (string) $menu->name,
			);
		}

		return $out;
	}

	/**
	 * Flat items (id, parent, …) → a tree. Pure. Items whose parent is missing become top level,
	 * so a half-deleted menu still shows every link.
	 *
	 * @param array<int, array<string, mixed>> $flat Items in menu order.
	 * @return array<int, array<string, mixed>>
	 */
	public static function tree( array $flat ): array {
		$ids = array();
		foreach ( $flat as $item ) {
			$ids[ (int) $item['id'] ] = true;
		}
		$children = array();
		foreach ( $flat as $item ) {
			$parent                = isset( $ids[ (int) $item['parent'] ] ) ? (int) $item['parent'] : 0;
			$children[ $parent ][] = $item;
		}
		// Every descendant of an item, depth first, in menu order.
		$below = static function ( int $parent_id ) use ( &$below, $children ): array {
			$out = array();
			foreach ( $children[ $parent_id ] ?? array() as $item ) {
				$out[] = $item;
				$out   = array_merge( $out, $below( (int) $item['id'] ) );
			}

			return $out;
		};
		$build = static function ( int $parent_id, int $depth ) use ( &$build, $below, $children ): array {
			$out = array();
			foreach ( $children[ $parent_id ] ?? array() as $item ) {
				$id   = (int) $item['id'];
				$kids = $depth < 2 ? $build( $id, $depth + 1 ) : array();
				unset( $item['parent'] );
				$item['children'] = $kids;
				$out[]            = $item;
				if ( $depth >= 2 ) {
					// Three levels deep at most: deeper items follow their ancestor at the third
					// level, so no link is lost.
					foreach ( $below( $id ) as $deeper ) {
						unset( $deeper['parent'] );
						$deeper['children'] = array();
						$out[]              = $deeper;
					}
				}
			}

			return $out;
		};

		return $build( 0, 0 );
	}
}
