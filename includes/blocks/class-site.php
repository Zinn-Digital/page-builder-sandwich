<?php
/**
 * Render callbacks of the "Site" group of design-system blocks (lane L09).
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Blocks;

use ZinnDigital\PBS\Frontend;
use ZinnDigital\PBS\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-site-data.php';

/**
 * One public static `render_<slug>()` per block of the group (Registry::callback()). Every
 * callback REBUILDS the live HTML from the attributes, prints classes only through
 * Frontend::cls(), escapes every value it prints, and translates every string it adds.
 *
 * Everything read from WordPress goes through Site_Data (self::data()), so these callbacks only
 * format; the unit bar swaps in a fixed site.
 */
final class Site {

	/**
	 * `pbs/table-of-contents`.
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 * @return string
	 */
	public static function render_table_of_contents( array $attributes ): string {
		return Site\Toc::render( $attributes );
	}

	/**
	 * `pbs/reading-progress`: a bar that fills as the visitor scrolls through the page.
	 *
	 * Decorative (`aria-hidden`): it repeats what the browser's scrollbar already tells every
	 * user, so announcing it would only be noise. Fixed to the top (below the admin bar) or the
	 * bottom of the window; the view module sets how full it is, and without script it stays empty.
	 *
	 * @param array<string, mixed> $attributes Attributes: position.
	 * @return string
	 */
	public static function render_reading_progress( array $attributes ): string {
		$position = 'bottom' === ( $attributes['position'] ?? '' ) ? 'bottom' : 'top';

		return '<div class="' . esc_attr( Frontend::cls( 'reading-progress', 'reading-progress--' . $position ) ) . '" aria-hidden="true"'
			. Markup::interactive( 'reading-progress', array() ) . ' data-wp-init="callbacks.readingInit">'
			. '<div class="' . esc_attr( Frontend::cls( 'reading-progress__bar' ) ) . '"></div></div>';
	}


	/**
	 * The data source (tests replace it).
	 *
	 * @var Site_Data|null
	 */
	public static ?Site_Data $data = null;

	/** The details pbs/post-meta can show, in display order. */
	public const META_ITEMS = array( 'author', 'date', 'modified', 'categories', 'tags', 'readingTime', 'comments' );

	/** Words a minute, for reading time. */
	private const WPM = 200;

	/**
	 * Heading ids used on this request (so two blocks never share one).
	 *
	 * @var int
	 */
	private static int $seq = 0;

	/**
	 * The data source.
	 *
	 * @return Site_Data
	 */
	public static function data(): Site_Data {
		if ( null === self::$data ) {
			self::$data = new Site_Data();
		}

		return self::$data;
	}

	/**
	 * Hooks (loaded from includes/blocks/load.php): no-store headers on pages with protected
	 * content, the password form's route and the editor's hashing route.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'template_redirect', array( self::class, 'no_store' ), 5 );
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
	}

	/**
	 * A heading id unique on this page.
	 *
	 * @param string $prefix Class prefix.
	 * @param string $slug   Block slug.
	 * @return string
	 */
	private static function heading_id( string $prefix, string $slug ): string {
		++self::$seq;

		return $prefix . '-' . $slug . '-' . self::$seq;
	}

	/**
	 * A heading level from an attribute (2–6).
	 *
	 * @param mixed $level Attribute.
	 * @param int   $fallback Default.
	 * @return int
	 */
	private static function level( $level, int $fallback = 2 ): int {
		$level = is_numeric( $level ) ? (int) $level : $fallback;

		return max( 2, min( 6, $level ) );
	}

	// ── pbs/related-posts ──────────────────────────────────────────────────────────────────────

	/**
	 * `pbs/related-posts`: posts sharing the current post's categories and/or tags.
	 *
	 * @param array<string, mixed> $attributes Attributes: count, by, heading, headingLevel, showImage, showDate, showExcerpt.
	 * @param string               $content    Unused.
	 * @param \WP_Block|null       $block      Block (its context gives the post in a query loop).
	 * @return string
	 */
	public static function render_related_posts( array $attributes, string $content = '', $block = null ): string {
		$count = max( 1, min( 12, (int) ( $attributes['count'] ?? 3 ) ) );
		$by    = in_array( $attributes['by'] ?? '', array( 'categories', 'tags', 'both' ), true ) ? (string) $attributes['by'] : 'both';
		$p     = Settings::prefix();
		$items = self::data()->related( self::data()->post_id( $block ), $by, $count, $p, ! isset( $attributes['fallback'] ) || ! empty( $attributes['fallback'] ) );
		if ( array() === $items ) {
			return '';
		}
		$c       = static fn( string ...$n ): string => esc_attr( Frontend::classes( $p, ...$n ) );
		$heading = trim( sanitize_text_field( (string) ( $attributes['heading'] ?? '' ) ) );
		$heading = '' === $heading ? __( 'Related posts', 'page-builder-sandwich' ) : $heading;
		$level   = self::level( $attributes['headingLevel'] ?? 2 );
		$id      = self::heading_id( $p, 'related' );
		$image   = ! isset( $attributes['showImage'] ) || ! empty( $attributes['showImage'] );
		$date    = ! isset( $attributes['showDate'] ) || ! empty( $attributes['showDate'] );
		$excerpt = ! empty( $attributes['showExcerpt'] );

		$html = '<section class="' . $c( 'related-posts' ) . '" aria-labelledby="' . esc_attr( $id ) . '">'
			. '<h' . $level . ' class="' . $c( 'related-posts__title' ) . '" id="' . esc_attr( $id ) . '">' . esc_html( $heading ) . '</h' . $level . '>'
			. '<ul class="' . $c( 'related-posts__list' ) . '">';
		foreach ( $items as $item ) {
			$html .= '<li class="' . $c( 'related-posts__item' ) . '">'
				. '<a class="' . $c( 'related-posts__link' ) . '" href="' . esc_url( $item['url'] ) . '">'
				. ( $image && '' !== $item['image'] ? wp_kses_post( $item['image'] ) : '' )
				. '<span class="' . $c( 'related-posts__name' ) . '">' . esc_html( wp_strip_all_tags( $item['title'] ) ) . '</span></a>';
			if ( $date && '' !== $item['date'] ) {
				$html .= '<time class="' . $c( 'related-posts__date' ) . '" datetime="' . esc_attr( $item['date'] ) . '">' . esc_html( $item['date_text'] ) . '</time>';
			}
			if ( $excerpt && '' !== $item['excerpt'] ) {
				$html .= '<p class="' . $c( 'related-posts__excerpt' ) . '">' . esc_html( $item['excerpt'] ) . '</p>';
			}
			$html .= '</li>';
		}

		return $html . '</ul></section>';
	}

	// ── pbs/post-meta ──────────────────────────────────────────────────────────────────────────

	/**
	 * `pbs/post-meta`: author, dates, terms, reading time, comments.
	 *
	 * @param array<string, mixed> $attributes Attributes: items (list of META_ITEMS).
	 * @param string               $content    Unused.
	 * @param \WP_Block|null       $block      Block.
	 * @return string
	 */
	public static function render_post_meta( array $attributes, string $content = '', $block = null ): string {
		$d = self::data()->post_details( self::data()->post_id( $block ) );
		if ( null === $d ) {
			return '';
		}
		$want = is_array( $attributes['items'] ?? null ) ? $attributes['items'] : array( 'author', 'date', 'readingTime' );
		$p    = Settings::prefix();
		$c    = static fn( string ...$n ): string => esc_attr( Frontend::classes( $p, ...$n ) );
		$li   = '';
		foreach ( self::META_ITEMS as $item ) {
			if ( ! in_array( $item, $want, true ) ) {
				continue;
			}
			$body = self::meta_item( $item, $d, $c );
			if ( '' !== $body ) {
				$li .= '<li class="' . $c( 'post-meta__item', 'post-meta__item--' . strtolower( $item ) ) . '">' . $body . '</li>';
			}
		}
		if ( '' === $li ) {
			return '';
		}

		return '<ul class="' . $c( 'post-meta' ) . '" aria-label="' . esc_attr__( 'Post details', 'page-builder-sandwich' ) . '">' . $li . '</ul>';
	}

	/**
	 * One detail.
	 *
	 * @param string               $item Item.
	 * @param array<string, mixed> $d    Details.
	 * @param callable             $c    Class helper.
	 * @return string
	 */
	private static function meta_item( string $item, array $d, callable $c ): string {
		$label = static fn( string $text ): string => '<span class="' . $c( 'post-meta__label' ) . '">' . esc_html( $text ) . '</span> ';
		$links = static function ( array $terms ) use ( $c ): string {
			$out = array();
			foreach ( $terms as $t ) {
				$out[] = '' !== $t['url'] ? '<a class="' . $c( 'post-meta__link' ) . '" href="' . esc_url( $t['url'] ) . '">' . esc_html( $t['name'] ) . '</a>' : esc_html( $t['name'] );
			}
			return implode( ', ', $out );
		};
		switch ( $item ) {
			case 'author':
				return '' === $d['author'] ? '' : $label( __( 'By', 'page-builder-sandwich' ) ) . '<a class="' . $c( 'post-meta__link' ) . '" href="' . esc_url( $d['author_url'] ) . '">' . esc_html( $d['author'] ) . '</a>';
			case 'date':
				return '' === $d['date'] ? '' : $label( __( 'Published', 'page-builder-sandwich' ) ) . '<time datetime="' . esc_attr( $d['date'] ) . '">' . esc_html( $d['date_text'] ) . '</time>';
			case 'modified':
				return '' === $d['modified'] || $d['modified_text'] === $d['date_text'] ? '' : $label( __( 'Updated', 'page-builder-sandwich' ) ) . '<time datetime="' . esc_attr( $d['modified'] ) . '">' . esc_html( $d['modified_text'] ) . '</time>';
			case 'categories':
				return array() === $d['categories'] ? '' : $label( __( 'In', 'page-builder-sandwich' ) ) . $links( $d['categories'] );
			case 'tags':
				return array() === $d['tags'] ? '' : $label( __( 'Tagged', 'page-builder-sandwich' ) ) . $links( $d['tags'] );
			case 'readingTime':
				$minutes = max( 1, (int) ceil( (int) $d['words'] / self::WPM ) );
				/* translators: %d: minutes it takes to read the post. */
				return esc_html( sprintf( _n( '%d minute read', '%d minutes read', $minutes, 'page-builder-sandwich' ), $minutes ) );
			case 'comments':
				$n = (int) $d['comments'];
				/* translators: %d: number of comments. */
				$text = 0 === $n ? __( 'No comments yet', 'page-builder-sandwich' ) : sprintf( _n( '%d comment', '%d comments', $n, 'page-builder-sandwich' ), $n );
				return '<a class="' . $c( 'post-meta__link' ) . '" href="' . esc_url( $d['comments_url'] ) . '">' . esc_html( $text ) . '</a>';
		}

		return '';
	}

	// ── pbs/sitemap ────────────────────────────────────────────────────────────────────────────

	/**
	 * `pbs/sitemap`: every published page/post (and other public types chosen) as a list of links;
	 * pages keep their parent/child tree.
	 *
	 * @param array<string, mixed> $attributes Attributes: postTypes, showHeadings, headingLevel.
	 * @return string
	 */
	public static function render_sitemap( array $attributes ): string {
		$types = is_array( $attributes['postTypes'] ?? null ) ? $attributes['postTypes'] : array( 'page', 'post' );
		$known = self::data()->public_types();
		$p     = Settings::prefix();
		$c     = static fn( string ...$n ): string => esc_attr( Frontend::classes( $p, ...$n ) );
		$heads = ! isset( $attributes['showHeadings'] ) || ! empty( $attributes['showHeadings'] );
		$level = self::level( $attributes['headingLevel'] ?? 2 );
		$html  = '';
		foreach ( $types as $type ) {
			if ( ! is_string( $type ) || ! isset( $known[ $type ] ) ) {
				continue;
			}
			$items = self::data()->sitemap( $type );
			if ( array() === $items ) {
				continue;
			}
			$id    = self::heading_id( $p, 'sitemap' );
			$html .= '<section class="' . $c( 'sitemap__group' ) . '"' . ( $heads ? ' aria-labelledby="' . esc_attr( $id ) . '"' : '' ) . '>';
			if ( $heads ) {
				$html .= '<h' . $level . ' class="' . $c( 'sitemap__title' ) . '" id="' . esc_attr( $id ) . '">' . esc_html( $known[ $type ] ) . '</h' . $level . '>';
			}
			$html .= self::tree( $items, 0, $c ) . '</section>';
		}
		if ( '' === $html ) {
			return '';
		}

		return '<nav class="' . $c( 'sitemap' ) . '" aria-label="' . esc_attr__( 'Sitemap', 'page-builder-sandwich' ) . '">' . $html . '</nav>';
	}

	/**
	 * A nested list of items under a parent.
	 *
	 * @param array<int, array<string, mixed>> $items  Items (id, parent, title, url).
	 * @param int                              $root_id Parent id.
	 * @param callable                         $c      Class helper.
	 * @return string
	 */
	private static function tree( array $items, int $root_id, callable $c ): string {
		$by = array();
		foreach ( $items as $item ) {
			$by[ (int) $item['parent'] ][] = $item;
		}
		$ids = array_column( $items, 'id' );
		// An item whose parent is not listed (a draft parent) goes at the top level.
		foreach ( $by as $pid => $children ) {
			if ( 0 !== $pid && ! in_array( $pid, $ids, true ) ) {
				$by[0] = array_merge( $by[0] ?? array(), $children );
				unset( $by[ $pid ] );
			}
		}
		$render = static function ( int $pid, int $depth ) use ( &$render, $by, $c ): string {
			if ( empty( $by[ $pid ] ) || $depth > 20 ) {
				return '';
			}
			$out = '<ul class="' . $c( 'sitemap__list' ) . '">';
			foreach ( $by[ $pid ] as $item ) {
				$title = trim( wp_strip_all_tags( (string) $item['title'] ) );
				$out  .= '<li class="' . $c( 'sitemap__item' ) . '"><a class="' . $c( 'sitemap__link' ) . '" href="' . esc_url( (string) $item['url'] ) . '">'
					. esc_html( '' === $title ? __( '(no title)', 'page-builder-sandwich' ) : $title ) . '</a>'
					. $render( (int) $item['id'], $depth + 1 ) . '</li>';
			}
			return $out . '</ul>';
		};

		return $render( $root_id, 0 );
	}

	// ── pbs/user-profile ───────────────────────────────────────────────────────────────────────

	/**
	 * `pbs/user-profile`: the post's author (or a chosen user): picture, name, bio, links.
	 *
	 * @param array<string, mixed> $attributes Attributes: source (author|user), userId, showAvatar, showBio, showLinks.
	 * @param string               $content    Unused.
	 * @param \WP_Block|null       $block      Block.
	 * @return string
	 */
	public static function render_user_profile( array $attributes, string $content = '', $block = null ): string {
		$uid = 'user' === ( $attributes['source'] ?? 'author' )
			? (int) ( $attributes['userId'] ?? 0 )
			: self::data()->author_of( self::data()->post_id( $block ) );
		$u   = $uid > 0 ? self::data()->user( $uid ) : null;
		if ( null === $u ) {
			return '';
		}
		$p    = Settings::prefix();
		$c    = static fn( string ...$n ): string => esc_attr( Frontend::classes( $p, ...$n ) );
		$html = '<div class="' . $c( 'user-profile' ) . '">';
		if ( ( ! isset( $attributes['showAvatar'] ) || ! empty( $attributes['showAvatar'] ) ) && '' !== $u['avatar'] ) {
			$html .= '<img class="' . $c( 'user-profile__avatar' ) . '" src="' . esc_url( $u['avatar'] ) . '" alt="" width="96" height="96" loading="lazy" decoding="async">';
		}
		$html .= '<div class="' . $c( 'user-profile__body' ) . '">'
			. '<p class="' . $c( 'user-profile__name' ) . '">' . esc_html( $u['name'] ) . '</p>';
		if ( ( ! isset( $attributes['showBio'] ) || ! empty( $attributes['showBio'] ) ) && '' !== trim( $u['bio'] ) ) {
			$html .= '<p class="' . $c( 'user-profile__bio' ) . '">' . wp_kses_post( $u['bio'] ) . '</p>';
		}
		if ( ! isset( $attributes['showLinks'] ) || ! empty( $attributes['showLinks'] ) ) {
			$links = '';
			if ( '' !== $u['posts_url'] ) {
				/* translators: %s: a person's name. */
				$links .= '<a class="' . $c( 'user-profile__link' ) . '" href="' . esc_url( $u['posts_url'] ) . '">' . esc_html( sprintf( __( 'All posts by %s', 'page-builder-sandwich' ), $u['name'] ) ) . '</a>';
			}
			if ( '' !== $u['website'] ) {
				$links .= '<a class="' . $c( 'user-profile__link' ) . '" href="' . esc_url( $u['website'] ) . '" rel="me">' . esc_html__( 'Website', 'page-builder-sandwich' ) . '</a>';
			}
			if ( '' !== $links ) {
				$html .= '<p class="' . $c( 'user-profile__links' ) . '">' . $links . '</p>';
			}
		}

		return $html . '</div></div>';
	}

	// ── pbs/protected ──────────────────────────────────────────────────────────────────────────

	/**
	 * `pbs/protected`: inner blocks shown only to a visitor who entered the password. While locked
	 * the page carries a password form and NOTHING of the content — it is never rendered, so it is
	 * not in the HTML, the REST API's rendered content, a feed or an excerpt.
	 *
	 * @param array<string, mixed> $attributes Attributes: lockId, hash, message.
	 * @param string               $content    Inner blocks already rendered (unit bar), or the saved fallback.
	 * @param \WP_Block|null       $block      Block.
	 * @return string
	 */
	public static function render_protected( array $attributes, string $content = '', $block = null ): string {
		$p    = Settings::prefix();
		$c    = static fn( string ...$n ): string => esc_attr( Frontend::classes( $p, ...$n ) );
		$lock = (string) ( $attributes['lockId'] ?? '' );
		$hash = (string) ( $attributes['hash'] ?? '' );
		if ( 1 !== preg_match( '/^[a-z0-9]{8}$/', $lock ) || '' === $hash ) {
			return ''; // No password set: nothing to show, and nothing to unlock.
		}
		if ( self::unlocked( $lock, $hash, $p ) ) {
			$inner = '';
			if ( $block instanceof \WP_Block ) {
				foreach ( $block->inner_blocks as $child ) {
					$inner .= $child->render();
				}
			}
			return '<div class="' . $c( 'protected', 'protected--open' ) . '" id="' . esc_attr( $p . '-lock-' . $lock ) . '">' . $inner . '</div>';
		}

		$message = trim( wp_kses_post( (string) ( $attributes['message'] ?? '' ) ) );
		$message = '' === $message ? esc_html__( 'This content is protected. Enter the password to see it.', 'page-builder-sandwich' ) : $message;
		$field   = $p . '-pw-' . $lock;
		$denied  = isset( $_GET[ $p . '-denied' ] ) && sanitize_key( wp_unslash( $_GET[ $p . '-denied' ] ) ) === $lock; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only chooses whether to show "wrong password".

		$post_id = self::data()->post_id( $block );

		return '<form class="' . $c( 'protected' ) . '" id="' . esc_attr( $p . '-lock-' . $lock ) . '" method="post" action="' . esc_url( self::data()->rest_url( self::unlock_namespace() . '/unlock' ) ) . '">'
			. '<p class="' . $c( 'protected__message' ) . '">' . $message . '</p>'
			. ( $denied ? '<p class="' . $c( 'protected__error' ) . '" role="alert">' . esc_html__( 'That password is not right. Try again.', 'page-builder-sandwich' ) . '</p>' : '' )
			. '<p class="' . $c( 'protected__row' ) . '"><label class="' . $c( 'protected__label' ) . '" for="' . esc_attr( $field ) . '">' . esc_html__( 'Password', 'page-builder-sandwich' ) . '</label>'
			. '<input class="' . $c( 'protected__input' ) . '" type="password" id="' . esc_attr( $field ) . '" name="pw" autocomplete="current-password" required>'
			. '<input type="hidden" name="lock" value="' . esc_attr( $lock ) . '">'
			. '<input type="hidden" name="post" value="' . esc_attr( (string) $post_id ) . '">'
			. '<button class="' . $c( 'protected__button' ) . '" type="submit">' . esc_html__( 'Show the content', 'page-builder-sandwich' ) . '</button></p>'
			. '</form>';
	}

	/**
	 * The cookie that proves a visitor entered the password for one block: an HMAC over the lock
	 * id and the password hash, so changing the password locks everyone out again.
	 *
	 * @param string $lock Lock id.
	 * @param string $hash Password hash.
	 * @return string
	 */
	public static function proof( string $lock, string $hash ): string {
		return hash_hmac( 'sha256', $lock . '|' . $hash, wp_salt( 'auth' ) );
	}

	/**
	 * The cookie name for a lock.
	 *
	 * @param string $lock   Lock id.
	 * @param string $prefix Prefix.
	 * @return string
	 */
	public static function cookie( string $lock, string $prefix ): string {
		return $prefix . '_unlock_' . $lock;
	}

	/**
	 * Has this visitor unlocked the block?
	 *
	 * @param string $lock   Lock id.
	 * @param string $hash   Password hash.
	 * @param string $prefix Prefix.
	 * @return bool
	 */
	public static function unlocked( string $lock, string $hash, string $prefix ): bool {
		$name = self::cookie( $lock, $prefix );
		$got  = isset( $_COOKIE[ $name ] ) ? sanitize_text_field( wp_unslash( (string) $_COOKIE[ $name ] ) ) : '';

		return '' !== $got && hash_equals( self::proof( $lock, $hash ), $got );
	}

	/** Unlock attempts allowed per visitor address and post, per window. */
	private const UNLOCK_LIMIT = 10;

	/** The rate window, in seconds. */
	private const UNLOCK_WINDOW = 600;

	/**
	 * The unlock route's namespace: neutral (the form's address is in the page).
	 *
	 * @return string
	 */
	public static function unlock_namespace(): string {
		return Settings::prefix() . '-u/v1';
	}

	/**
	 * `template_redirect`: a page holding protected content is never stored by a page cache
	 * (one visitor's unlocked view must not be served to the next).
	 *
	 * @return void
	 */
	public static function no_store(): void {
		$post = get_queried_object();
		if ( $post instanceof \WP_Post && str_contains( (string) $post->post_content, '<!-- wp:pbs/protected' ) ) {
			nocache_headers();
		}
	}

	/**
	 * Who may try a password: anyone, for a post that is published and publicly viewable (and not
	 * itself behind WordPress's post password), within the rate limit. The password is the
	 * authorisation; this only narrows who may ask.
	 *
	 * ⭐ Like WordPress's own post password form there is no nonce: the only effect of a right
	 * password is a cookie in the visitor's own browser, and a nonce would fail on every cached page.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool|\WP_Error
	 */
	public static function can_unlock( \WP_REST_Request $request ) {
		$post = get_post( (int) $request->get_param( 'post' ) );
		if ( ! $post instanceof \WP_Post || 'publish' !== $post->post_status || ! is_post_publicly_viewable( $post ) || post_password_required( $post ) ) {
			return new \WP_Error( 'pbsw_unlock_post', __( 'That page cannot be unlocked.', 'page-builder-sandwich' ), array( 'status' => 404 ) );
		}
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key = 'pbsw_ul_' . md5( $ip . '|' . $post->ID );
		$n   = (int) get_transient( $key );
		if ( $n >= self::UNLOCK_LIMIT ) {
			return new \WP_Error( 'pbsw_unlock_rate', __( 'Too many tries. Wait a few minutes and try again.', 'page-builder-sandwich' ), array( 'status' => 429 ) );
		}
		set_transient( $key, $n + 1, self::UNLOCK_WINDOW );

		return true;
	}

	/**
	 * `POST /<prefix>-u/v1/unlock` (the password form): check the password against the block's
	 * hash, set the proof cookie, and send the visitor back to the page (303, post/redirect/get).
	 *
	 * @param \WP_REST_Request $request Request: post, lock, pw.
	 * @return \WP_REST_Response
	 */
	public static function unlock( \WP_REST_Request $request ): \WP_REST_Response {
		$p    = Settings::prefix();
		$post = get_post( (int) $request->get_param( 'post' ) );
		$lock = (string) $request->get_param( 'lock' );
		$pw   = (string) $request->get_param( 'pw' );
		$hash = $post instanceof \WP_Post ? self::find_hash( parse_blocks( (string) $post->post_content ), $lock ) : null;
		$back = (string) get_permalink( $post );
		$ok   = null !== $hash && '' !== $pw && wp_check_password( $pw, $hash );
		if ( $ok ) {
			setcookie(
				self::cookie( $lock, $p ),
				self::proof( $lock, (string) $hash ),
				array(
					'expires'  => time() + 10 * DAY_IN_SECONDS,
					'path'     => COOKIEPATH,
					'domain'   => (string) COOKIE_DOMAIN,
					'secure'   => is_ssl(),
					'httponly' => true,
					'samesite' => 'Lax',
				)
			);
		}
		$to       = ( $ok ? $back : add_query_arg( $p . '-denied', $lock, $back ) ) . '#' . $p . '-lock-' . $lock;
		$response = new \WP_REST_Response( array( 'unlocked' => $ok ), 303 );
		$response->header( 'Location', wp_validate_redirect( $to, home_url( '/' ) ) );
		$response->header( 'Cache-Control', 'no-store' );

		return $response;
	}

	/**
	 * The password hash of the protected block with this lock id, anywhere in a block tree.
	 *
	 * @param array<int, mixed> $blocks Parsed blocks.
	 * @param string            $lock   Lock id.
	 * @return string|null
	 */
	public static function find_hash( array $blocks, string $lock ): ?string {
		foreach ( $blocks as $b ) {
			if ( ! is_array( $b ) ) {
				continue;
			}
			if ( 'pbs/protected' === ( $b['blockName'] ?? '' ) && ( $b['attrs']['lockId'] ?? null ) === $lock && is_string( $b['attrs']['hash'] ?? null ) && '' !== $b['attrs']['hash'] ) {
				return $b['attrs']['hash'];
			}
			$found = self::find_hash( is_array( $b['innerBlocks'] ?? null ) ? $b['innerBlocks'] : array(), $lock );
			if ( null !== $found ) {
				return $found;
			}
		}

		return null;
	}

	/**
	 * `rest_api_init`: POST pbs/v1/blocks/protected/hash {password} → {hash}. The editor stores
	 * only the hash, so the password itself is never saved in the post.
	 *
	 * @return void
	 */
	public static function routes(): void {
		register_rest_route(
			self::unlock_namespace(),
			'/unlock',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'unlock' ),
				'permission_callback' => array( self::class, 'can_unlock' ),
				'args'                => array(
					'post' => array(
						'type'     => 'integer',
						'required' => true,
					),
					'lock' => array(
						'type'     => 'string',
						'required' => true,
						'pattern'  => '^[a-z0-9]{8}$',
					),
					'pw'   => array(
						'type'      => 'string',
						'required'  => true,
						'maxLength' => 200,
					),
				),
			)
		);
		register_rest_route(
			'pbs/v1',
			'/blocks/protected/hash',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => static function ( \WP_REST_Request $request ) {
					$pw = (string) $request->get_param( 'password' );
					if ( strlen( $pw ) < 4 || strlen( $pw ) > 200 ) {
						return new \WP_Error( 'pbsw_password_length', __( 'The password must be 4 to 200 characters long.', 'page-builder-sandwich' ), array( 'status' => 400 ) );
					}
					return new \WP_REST_Response( array( 'hash' => wp_hash_password( $pw ) ) );
				},
				'permission_callback' => static fn(): bool => current_user_can( 'edit_posts' ),
				'args'                => array(
					'password' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
	}
}
