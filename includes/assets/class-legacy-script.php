<?php
/**
 * Front-end behaviour for legacy (5.x) content: the script, and the few things done server-side.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Assets;

use ZinnDigital\PBS\Assets;
use ZinnDigital\PBS\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reproduces what the 5.x FREE edition's front-end script did to saved content
 * (assets/legacy.js), loading it only where it has work to do.
 *
 * ⭐ Each edition runs what it ran in 5.x. The 5.x premium modules — carousel, countdown, Google
 * fonts, the responsive settings (text sizes, force overflow) and code highlighting — loaded only
 * when premium code could run, so the free edition showed that content as saved (slides stacked,
 * the countdown's saved numbers). They live in the premium layer, which adds its own script on top
 * of this one through `pbsw_legacy_script_enqueued`; nothing here reproduces them.
 *
 * ⛔ PERFORMANCE: a post pays for the script only when Legacy::post_needs_legacy() says it
 * carries legacy markup AND its raw content carries a marker of one of the behaviours below
 * (self::behaviours()). A legacy page that is only rows and text gets the stylesheet and no
 * script; a page that is not legacy gets neither. The script is deferred, in the footer, and
 * served from the neutral published path (ADR 0033), inline only when that copy is missing.
 *
 * Whitespace-only columns are emptied here rather than in the script (so the stylesheet's `:empty`
 * rules apply before the first paint), and a full-width row, which the script must move, gets a
 * small inline stylesheet that gives it its final width before the script runs.
 */
final class Legacy_Script {

	/** Assets source key. */
	public const JS_KEY = 'legacy.js';

	/**
	 * Markers of each behaviour in a post's RAW content: 5.x markup (unconverted, or kept inside
	 * core/html) and the `legacyData` JSON a converted row, column or button carries.
	 */
	private const BEHAVIOURS = array(
		// A converted legacy row stores its width as the `width` attribute (and renders 5.x's
		// data-width, Core\Render::row()); only a `legacy` block's marker counts.
		'full-width' => '/\sdata-width=(["\'])full-width|"width":"full-width[^"]*"[^\n]*?"legacy":true/',
		'parallax'   => '/data-pbs-parallax=|"pbs-parallax":/',
		'video'      => '/data-pbs-video-(?:url|mp4|webm)=|"pbs-video-(?:url|mp4|webm)":/',
		'kenburns'   => '/data-pbs-kenburns-1=|"pbs-kenburns-1":/',
		'animation'  => '/\sdata-aos(?:-js)?=|"aos(?:-js)?":/',
		'tabs'       => '/(?<![A-Za-z0-9_-])pbs-tab-state(?![A-Za-z0-9_-])/',
		'toggle'     => '/data-ce-tag=(["\'])toggleradio\1/',
		// Only a stored tablet/phone value changes anything: an inline margin with none is put back
		// as it was on every breakpoint (5.x saved it as the desktop value and restored it).
		'margins'    => '/data-pbs-(?:tablet|phone)-margin-(?:top|bottom)=|"pbs-(?:tablet|phone)-margin-(?:top|bottom)":/',
		'map'        => '/data-ce-tag=(["\'])map\1/',
		'anchors'    => '/\shref=(["\'])[#.][A-Za-z0-9_]/',
		'embeds'     => '#<(?:iframe|object)\b[^>]*(?:youtube\.com|youtube-nocookie\.com|player\.vimeo\.com|fast\.wistia\.net)|https?://(?:www\.)?(?:youtube\.com/watch|youtu\.be/|vimeo\.com/\d)#i',
	);

	/**
	 * Hook the enqueue and the server-side content step.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue_for_request' ), 20 );
		// Before Legacy::filter_content (999) rewrites the class names.
		add_filter( 'the_content', array( self::class, 'filter_content' ), 998 );
	}

	/**
	 * The behaviours this raw content needs the script for, in a fixed order.
	 *
	 * @param string $content Raw post content.
	 * @return array<int, string>
	 */
	public static function behaviours( string $content ): array {
		$found = array();
		foreach ( self::BEHAVIOURS as $name => $regex ) {
			if ( 1 === preg_match( $regex, $content ) ) {
				$found[] = $name;
			}
		}
		return $found;
	}

	/**
	 * The whole decision, pure: legacy content (Legacy::needs_legacy()) with at least one marker.
	 *
	 * @param string $content   Raw post content.
	 * @param bool   $has_style Whether the post has legacy pseudo styles.
	 * @return array<int, string> The behaviours; empty means no script.
	 */
	public static function needed( string $content, bool $has_style ): array {
		return Legacy::needs_legacy( $content, $has_style ) ? self::behaviours( $content ) : array();
	}

	/**
	 * `wp_enqueue_scripts`: the singular post being viewed, and nothing else.
	 *
	 * @return void
	 */
	public static function enqueue_for_request(): void {
		if ( is_singular() ) {
			self::maybe_enqueue_for_post( (int) get_queried_object_id() );
		}
	}

	/**
	 * Enqueue the script (and its pre-positioning rules) when the post needs it.
	 *
	 * @param int $post_id Post id.
	 * @return array<int, string> The behaviours the script was loaded for; empty when it was not.
	 */
	public static function maybe_enqueue_for_post( int $post_id ): array {
		if ( $post_id < 1 || ! Legacy::post_needs_legacy( $post_id ) ) {
			return array();
		}
		$post    = get_post( $post_id );
		$content = $post instanceof \WP_Post ? (string) $post->post_content : '';
		$prefix  = Settings::prefix();
		$found   = self::behaviours( $content );
		$handle  = null;

		if ( array() !== $found ) {
			$handle = Assets::enqueue_script( self::JS_KEY, 'window.' . $prefix . 'LegacyConfig=' . wp_json_encode( self::config() ) . ';' );
			$css    = self::pre_css( $found, $prefix );
			if ( '' !== $css ) {
				self::inline_style( $css, $prefix );
			}
		}

		/**
		 * A legacy post was examined for its front-end behaviour (the premium layer adds its own).
		 *
		 * @param int         $post_id Post id.
		 * @param string      $content Raw post content.
		 * @param string|null $handle  The base script's handle, or null when the post needed none.
		 */
		do_action( 'pbsw_legacy_script_enqueued', $post_id, $content, $handle );

		return $found;
	}

	/**
	 * An inline stylesheet under a neutral handle (printed as `<prefix>-<hash>-inline-css`).
	 *
	 * @param string $css    CSS.
	 * @param string $prefix Neutral prefix.
	 * @return void
	 */
	public static function inline_style( string $css, string $prefix ): void {
		$handle = $prefix . '-' . substr( sha1( $css ), 0, 8 );
		wp_register_style( $handle, false, array(), PBSW_VERSION );
		wp_enqueue_style( $handle );
		wp_add_inline_style( $handle, $css );
	}

	/**
	 * What the script reads: the theme name (5.x put `theme-<name>` on `<html>`) and the strings it
	 * gives assistive technology.
	 *
	 * @return array<string, mixed>
	 */
	public static function config(): array {
		$theme = function_exists( 'wp_get_theme' ) ? (string) wp_get_theme()->get( 'Name' ) : '';

		return array(
			'theme'      => sanitize_html_class( str_replace( ' ', '-', strtolower( $theme ) ) ),
			'mapTitle'   => __( 'Map', 'page-builder-sandwich' ),
			'videoTitle' => __( 'Background video', 'page-builder-sandwich' ),
		);
	}

	/**
	 * Rules that give a full-width row its final size before the script moves it (CLS): the compat
	 * stylesheet hides such a row until the script sets its offset; this gives the hidden row the
	 * viewport's width, so the page around it is already laid out (the script then corrects for the
	 * scrollbar and an off-centre column).
	 *
	 * @param array<int, string> $behaviours What the page needs.
	 * @param string             $prefix     Neutral class prefix.
	 * @return string
	 */
	public static function pre_css( array $behaviours, string $prefix ): string {
		if ( ! in_array( 'full-width', $behaviours, true ) ) {
			return '';
		}
		// Only until the script has placed the row (it then sets `visibility` inline — and before
		// that `left`, before it measures): a padding still applying while the script
		// measures the row's natural width inflated the row by the padding and left the
		// retain-content right padding 40px short (measured on converted rows).
		$row = '.' . $prefix . '-l-main-wrapper > .' . $prefix . '-l-row:not([style*="visibility"])';
		return $row . '[data-width^="full-width"]{width:100vw;max-width:100vw;margin-inline-start:calc(50% - 50vw);margin-inline-end:0;box-sizing:border-box}'
			. $row . '[data-width="full-width-retain-content"]{padding-inline:calc(50vw - 50%)}';
	}

	/**
	 * `the_content`, just before the class rewrite: empty the whitespace-only legacy columns.
	 *
	 * @param mixed $content Rendered content.
	 * @return mixed
	 */
	public static function filter_content( $content ) {
		if ( ! is_string( $content ) || ! str_contains( $content, 'pbs-col' ) ) {
			return $content;
		}
		$post_id = (int) get_the_ID();
		if ( $post_id < 1 || ! Legacy::post_needs_legacy( $post_id ) ) {
			return $content;
		}
		return self::empty_columns( $content );
	}

	/**
	 * 5.x emptied a column holding only whitespace so the stylesheet's `:empty` rules hide it.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function empty_columns( string $html ): string {
		return (string) preg_replace(
			'/(<div\b[^>]*\sclass=(["\'])(?:[^"\']*\s)?pbs-col(?:\s[^"\']*)?\2[^>]*>)\s+(<\/div>)/',
			'$1$3',
			$html
		);
	}
}
