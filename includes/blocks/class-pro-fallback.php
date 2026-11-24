<?php
/**
 * What a visitor sees of a Pro block on a site where Pro is not running.
 *
 * When a page holds blocks saved by Page Builder Sandwich Pro and Pro is not active (it was
 * deactivated, or the site was moved without it), each such block renders as a free equivalent,
 * or is left out when there is none. The page never breaks and no PHP notice is raised. The saved
 * content is never changed, so activating Pro again shows every block as it was saved. This class
 * only renders; it adds nothing to the editor.
 *
 * Without it, WordPress renders an unregistered block's saved markup: most blocks already leave
 * static HTML, but an A/B test showed BOTH variants, a content switcher showed BOTH panes, a query
 * loop showed its template's placeholder text, a form left an empty box, and the search form,
 * login form and posts list vanished. (The navigation menu is free: only its mega panels come
 * from Pro, and they fall back to the item's submenu.)
 *
 * ⛔ Free code by design (WordPress.org guideline 5): this keeps a visitor's page whole when Pro
 * is not running. It unlocks nothing; it shows the free version of what was saved.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Blocks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Free fallbacks for unregistered Pro blocks.
 */
final class Pro_Fallback {

	/** Show the saved markup (WordPress's own rendering), hidden when it carries nothing visible. */
	public const SAVED = 'saved';

	/** Show only the first inner block: an A/B test's original, a switcher's first pane. */
	public const FIRST = 'first';

	/** Hide it: there is no free version (the saved markup is a placeholder or an empty shell). */
	public const HIDE = 'hide';

	/** A core WordPress equivalent: the search form. */
	public const SEARCH = 'search';

	/** A core WordPress equivalent: the login form (nothing for a signed-in visitor). */
	public const LOGIN = 'login';

	/** A core WordPress equivalent: the latest posts list. */
	public const POSTS = 'posts';

	/**
	 * Every Pro block: slug → strategy. ⛔ Must list exactly the Pro registry's blocks
	 * (ProFallbackTest derives the comparison from that registry, so a new Pro block without a
	 * line here fails the suite).
	 */
	public const BLOCKS = array(
		'ab-test'           => self::FIRST,
		'ab-variant'        => self::SAVED,
		'affiliate-button'  => self::SAVED,
		'age-gate'          => self::HIDE,
		'animated-headline' => self::SAVED,
		'animated-text'     => self::SAVED,
		'audio-player'      => self::SAVED,
		'author-box'        => self::SAVED,
		'back-to-top'       => self::HIDE,
		'before-after'      => self::SAVED,
		'breadcrumbs'       => self::HIDE,
		'business-hours'    => self::SAVED,
		'casino-table'      => self::SAVED,
		'chart'             => self::SAVED,
		'click-to-tweet'    => self::SAVED,
		'content-switcher'  => self::FIRST,
		'countdown'         => self::SAVED,
		'counter'           => self::SAVED,
		'coupon-code'       => self::SAVED,
		'crypto-ticker'     => self::HIDE,
		'cursor-effects'    => self::SAVED,
		'dark-mode-switch'  => self::HIDE,
		'data-table'        => self::SAVED,
		'events'            => self::SAVED,
		'flip-box'          => self::SAVED,
		'form-builder'      => self::HIDE,
		'form-field'        => self::HIDE,
		'form-step'         => self::HIDE,
		'gallery'           => self::SAVED,
		'hotspots'          => self::SAVED,
		'image-accordion'   => self::SAVED,
		'login-form'        => self::LOGIN,
		'logo-grid'         => self::SAVED,
		'logo-marquee'      => self::SAVED,
		'lottie'            => self::HIDE,
		'marquee'           => self::SAVED,
		'mega-panel'        => self::HIDE,
		'mini-cart'         => self::HIDE,
		'model-viewer'      => self::SAVED,
		'newsletter'        => self::SAVED,
		'offer-card'        => self::SAVED,
		'org-chart'         => self::SAVED,
		'parallax-image'    => self::SAVED,
		'particles'         => self::SAVED,
		'pdf-viewer'        => self::SAVED,
		'posts'             => self::POSTS,
		'price-list'        => self::SAVED,
		'pricing-table'     => self::SAVED,
		'product-element'   => self::HIDE,
		'progress-circle'   => self::SAVED,
		'promo-bar'         => self::SAVED,
		'pros-cons'         => self::SAVED,
		'query-loop'        => self::HIDE,
		'reading-time'      => self::HIDE,
		'review-score'      => self::SAVED,
		'reviews'           => self::HIDE,
		'rg-notice'         => self::SAVED,
		'roadmap'           => self::SAVED,
		'search-form'       => self::SEARCH,
		'share-bar'         => self::HIDE,
		'share-buttons'     => self::HIDE,
		'shop-page'         => self::HIDE,
		'show-more'         => self::SAVED,
		'site-header'       => self::SAVED,
		'slide'             => self::SAVED,
		'slider'            => self::SAVED,
		'social-proof'      => self::HIDE,
		'store-locator'     => self::SAVED,
		'switcher-pane'     => self::SAVED,
		'team'              => self::SAVED,
		'timeline'          => self::SAVED,
		'trust-badges'      => self::SAVED,
		'video-gallery'     => self::SAVED,
		'video-playlist'    => self::SAVED,
	);

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'render_block', array( self::class, 'render' ), 10, 2 );
	}

	/**
	 * The strategy for a block name, or null when it is not a Pro block or Pro is running for it.
	 *
	 * @param string $name Block name.
	 * @return string|null
	 */
	public static function strategy( string $name ): ?string {
		if ( 0 !== strpos( $name, 'pbsw/' ) ) {
			return null;
		}
		$slug = substr( $name, 5 );
		if ( ! isset( self::BLOCKS[ $slug ] ) || \WP_Block_Type_Registry::get_instance()->is_registered( $name ) ) {
			return null;
		}

		return self::BLOCKS[ $slug ];
	}

	/**
	 * `render_block`: the free version of an unregistered Pro block.
	 *
	 * @param string|mixed         $content What WordPress rendered (the saved markup).
	 * @param array<string, mixed> $block   The parsed block.
	 * @return string|mixed
	 */
	public static function render( $content, $block ) {
		$name     = is_array( $block ) && is_string( $block['blockName'] ?? null ) ? $block['blockName'] : '';
		$strategy = '' === $name ? null : self::strategy( $name );
		if ( null === $strategy ) {
			return $content;
		}
		$attrs = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();

		switch ( $strategy ) {
			case self::HIDE:
				return '';
			case self::FIRST:
				$first = is_array( $block['innerBlocks'][0] ?? null ) ? $block['innerBlocks'][0] : null;
				return null === $first ? '' : self::visible( render_block( $first ) );
			case self::SEARCH:
				return (string) get_search_form( array( 'echo' => false ) );
			case self::LOGIN:
				return is_user_logged_in() ? '' : (string) wp_login_form( array( 'echo' => false ) );
			case self::POSTS:
				return self::posts( (int) ( $attrs['perPage'] ?? 6 ) );
		}

		return self::visible( (string) $content );
	}

	/**
	 * The markup, or '' when it shows nothing (an empty box left by a block that drew in a script).
	 *
	 * @param string $html Markup.
	 * @return string
	 */
	public static function visible( string $html ): string {
		if ( '' !== trim( wp_strip_all_tags( $html ) ) || preg_match( '/<(img|video|audio|iframe|svg|picture|object|embed|canvas|input|select|textarea|button)\b/i', $html ) ) {
			return $html;
		}

		return '';
	}

	/**
	 * The latest posts as a plain list of links (core's own Latest Posts block).
	 *
	 * @param int $count How many.
	 * @return string
	 */
	private static function posts( int $count ): string {
		$count = max( 1, min( 100, $count ) );

		$parsed = array(
			'blockName'    => 'core/latest-posts',
			'attrs'        => array( 'postsToShow' => $count ),
			'innerBlocks'  => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
		);

		return self::visible( render_block( $parsed ) );
	}
}
