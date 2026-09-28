<?php
/**
 * What the "Site" blocks read from WordPress (lane L09).
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Blocks;

use ZinnDigital\PBS\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every WordPress read the Site blocks make, in one place, returning plain arrays. The render
 * callbacks only format what this returns, so the unit bar can hand them a fixed site
 * (Site::$data) and real WordPress is proven by the browser leg.
 */
class Site_Data {

	/**
	 * The post a block is showing: the block context's (query loops) or the current one.
	 *
	 * @param \WP_Block|null $block Block.
	 * @return int
	 */
	public function post_id( $block ): int {
		if ( $block instanceof \WP_Block && isset( $block->context['postId'] ) ) {
			return (int) $block->context['postId'];
		}

		return (int) get_the_ID();
	}

	/**
	 * Posts related to a post by shared categories and/or tags, newest first.
	 *
	 * @param int    $post_id  Post.
	 * @param string $by       categories | tags | both.
	 * @param int    $count    How many.
	 * @param string $prefix   Class prefix (for the image's class).
	 * @param bool   $fallback When nothing shares a term, the newest others of the same type.
	 * @return array<int, array<string, string>> Items: url, title, date (ISO), date_text, excerpt, image (HTML).
	 */
	public function related( int $post_id, string $by, int $count, string $prefix, bool $fallback = true ): array {
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return array();
		}
		$tax = array();
		if ( 'tags' !== $by ) {
			$tax[] = array(
				'taxonomy' => 'category',
				'terms'    => wp_get_post_categories( $post_id ),
			);
		}
		if ( 'categories' !== $by ) {
			$tax[] = array(
				'taxonomy' => 'post_tag',
				'terms'    => wp_get_post_tags( $post_id, array( 'fields' => 'ids' ) ),
			);
		}
		$tax   = array_values( array_filter( $tax, static fn( array $t ): bool => array() !== $t['terms'] ) );
		$query = array(
			'post_type'           => $post->post_type,
			'post_status'         => 'publish',
			'posts_per_page'      => $count,
			'post__not_in'        => array( $post_id ), // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- one post, a bounded list.
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
			'has_password'        => false,
			'fields'              => 'ids',
		);
		$ids   = array();
		if ( array() !== $tax ) {
			$tax['relation'] = 'OR';
			$ids             = get_posts( array_merge( $query, array( 'tax_query' => $tax ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- the point of the block; bounded by posts_per_page.
		}
		if ( array() === $ids && $fallback ) {
			$ids = get_posts( $query );
		}
		$out = array();
		foreach ( $ids as $id ) {
			$out[] = $this->summary( (int) $id, $prefix );
		}

		return $out;
	}

	/**
	 * One post, as the blocks print it.
	 *
	 * @param int    $id     Post.
	 * @param string $prefix Class prefix.
	 * @return array<string, string>
	 */
	public function summary( int $id, string $prefix ): array {
		$thumb = (int) get_post_thumbnail_id( $id );

		return array(
			'url'       => (string) get_permalink( $id ),
			'title'     => (string) get_the_title( $id ),
			'date'      => (string) get_the_date( 'c', $id ),
			'date_text' => (string) get_the_date( '', $id ),
			'excerpt'   => (string) wp_trim_words( (string) get_the_excerpt( $id ), 24 ),
			'image'     => $thumb > 0 ? (string) wp_get_attachment_image(
				$thumb,
				'medium_large',
				false,
				array(
					'class'   => Frontend::classes( $prefix, 'related-posts__image' ),
					'alt'     => '',
					'loading' => 'lazy',
				)
			) : '',
		);
	}

	/**
	 * Details of a post for pbs/post-meta.
	 *
	 * @param int $post_id Post.
	 * @return array<string, mixed>|null author, author_url, date, date_text, modified, modified_text, categories, tags, words, comments, comments_url.
	 */
	public function post_details( int $post_id ): ?array {
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return null;
		}
		$terms = static function ( string $taxonomy ) use ( $post_id ): array {
			$out = array();
			foreach ( (array) get_the_terms( $post_id, $taxonomy ) as $term ) {
				if ( $term instanceof \WP_Term ) {
					$link  = get_term_link( $term );
					$out[] = array(
						'name' => $term->name,
						'url'  => is_string( $link ) ? $link : '',
					);
				}
			}
			return $out;
		};

		return array(
			'author'        => (string) get_the_author_meta( 'display_name', (int) $post->post_author ),
			'author_url'    => (string) get_author_posts_url( (int) $post->post_author ),
			'date'          => (string) get_the_date( 'c', $post ),
			'date_text'     => (string) get_the_date( '', $post ),
			'modified'      => (string) get_the_modified_date( 'c', $post ),
			'modified_text' => (string) get_the_modified_date( '', $post ),
			'categories'    => $terms( 'category' ),
			'tags'          => $terms( 'post_tag' ),
			'words'         => str_word_count( wp_strip_all_tags( (string) $post->post_content ) ),
			'comments'      => (int) get_comments_number( $post ),
			'comments_url'  => (string) get_comments_link( $post ),
		);
	}

	/**
	 * Every published item of a post type, for the HTML sitemap: pages in their tree order,
	 * posts newest first. Cached until any post changes.
	 *
	 * @param string $type Post type.
	 * @return array<int, array{id: int, parent: int, title: string, url: string}>
	 */
	public function sitemap( string $type ): array {
		$key    = 'sitemap_' . $type . '_' . wp_cache_get_last_changed( 'posts' );
		$cached = wp_cache_get( $key, 'pbsw' );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$hier = is_post_type_hierarchical( $type );
		$ids  = get_posts(
			array(
				'post_type'      => $type,
				'post_status'    => 'publish',
				'posts_per_page' => -1, // The sitemap lists everything; the result is cached until a post changes.
				'orderby'        => $hier ? array(
					'menu_order' => 'ASC',
					'title'      => 'ASC',
				) : 'date',
				'order'          => $hier ? 'ASC' : 'DESC',
				'no_found_rows'  => true,
				'fields'         => 'id=>parent',
				'has_password'   => false,
			)
		);
		$out  = array();
		foreach ( $ids as $id => $parent ) {
			$out[] = array(
				'id'     => (int) $id,
				'parent' => $hier ? (int) $parent : 0,
				'title'  => (string) get_the_title( (int) $id ),
				'url'    => (string) get_permalink( (int) $id ),
			);
		}
		wp_cache_set( $key, $out, 'pbsw', DAY_IN_SECONDS );

		return $out;
	}

	/**
	 * The public post types a sitemap may list, slug → plural label.
	 *
	 * @return array<string, string>
	 */
	public function public_types(): array {
		$out = array();
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $slug => $obj ) {
			if ( 'attachment' !== $slug ) {
				$out[ (string) $slug ] = (string) $obj->labels->name;
			}
		}

		return $out;
	}

	/**
	 * A user, as pbs/user-profile prints them.
	 *
	 * @param int $user_id User.
	 * @return array<string, string>|null name, bio, posts_url, website, avatar (URL).
	 */
	public function user( int $user_id ): ?array {
		$user = get_userdata( $user_id );
		if ( ! $user instanceof \WP_User ) {
			return null;
		}

		return array(
			'name'      => (string) $user->display_name,
			'bio'       => (string) get_the_author_meta( 'description', $user_id ),
			'posts_url' => (string) get_author_posts_url( $user_id ),
			'website'   => (string) $user->user_url,
			'avatar'    => (string) get_avatar_url( $user_id, array( 'size' => 192 ) ),
		);
	}

	/**
	 * The author of a post.
	 *
	 * @param int $post_id Post.
	 * @return int User ID (0 when none).
	 */
	public function author_of( int $post_id ): int {
		$post = get_post( $post_id );

		return $post instanceof \WP_Post ? (int) $post->post_author : 0;
	}

	/**
	 * A REST route's public address.
	 *
	 * @param string $path Route, e.g. `zd-u/v1/unlock`.
	 * @return string
	 */
	public function rest_url( string $path ): string {
		return (string) rest_url( $path );
	}
}
