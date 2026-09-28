<?php
/**
 * Extensions of core text blocks (lane L09, P5 group G-B): the highlight format, the drop cap
 * and the pull quote style — their front-end CSS, added to a page only when the page uses them.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Blocks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The editor half is src/blocks/extensions/ (bundle `blocks`). What they save into post content:
 *
 *   Highlight:  `<mark data-hl="marker|underline|background">…</mark>` inside any rich text
 *   Drop cap:   core/paragraph's own `dropCap` (core styles it), or the block style
 *               `drop-cap-boxed` → class `is-style-drop-cap-boxed`
 *   Pull quote: the block style `accent-bar` on core/pullquote → class `is-style-accent-bar`
 *
 * ⭐ None of these names mention the plugin, so saved content stays footprint-free and readable
 * with the plugin off (a `<mark>` is still a highlight, a pull quote still a quote). Their CSS
 * joins the page's compiled stylesheet (filter `pbsw_page_css_extra`) only on a page whose blocks
 * use them — no global stylesheet (CLAUDE.md §2.22).
 */
final class Extensions {

	/** The highlight looks. */
	public const HIGHLIGHTS = array( 'marker', 'underline', 'background' );

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'pbsw_page_css_extra', array( self::class, 'page_css' ), 30, 3 );
		add_filter( 'pbsw_page_css_signature', array( self::class, 'signature' ) );
		add_action( 'enqueue_block_assets', array( self::class, 'editor_css' ), 20 );
	}

	/**
	 * Part of the page sheet's cache key (bumped when these rules change).
	 *
	 * @param mixed $signature So far.
	 * @return string
	 */
	public static function signature( $signature ): string {
		return (string) $signature . '|ext1';
	}

	/**
	 * CSS per feature.
	 *
	 * @return array<string, string>
	 */
	public static function rules(): array {
		return array(
			'highlight' => 'mark[data-hl]{background:none;color:inherit}'
				. 'mark[data-hl="marker"]{background-image:linear-gradient(transparent 55%,rgb(255 214 0 / 55%) 55%)}'
				. 'mark[data-hl="underline"]{text-decoration:underline;text-decoration-thickness:.18em;text-decoration-color:var(--wp--preset--color--primary,var(--wp--preset--color--accent-3,#5b21b6));text-underline-offset:.15em}'
				. 'mark[data-hl="background"]{padding-inline:.15em;border-radius:3px;background-color:#fff1a6;color:#1e1e1e}',
			'dropcap'   => '.is-style-drop-cap-boxed::first-letter{float:inline-start;margin-block:.08em 0;margin-inline-end:.12em;padding-block:.06em;padding-inline:.18em;border-radius:4px;background-color:var(--wp--preset--color--contrast,#111);color:var(--wp--preset--color--base,#fff);font-size:3.4em;font-weight:700;line-height:.9}',
			'pullquote' => '.is-style-accent-bar.is-style-accent-bar{margin-block:2rem;padding-block:.5rem;padding-inline:1.5rem 0;font-size:1rem;border:0;border-inline-start:6px solid var(--wp--preset--color--primary,var(--wp--preset--color--accent-3,#5b21b6));text-align:start}'
				. '.is-style-accent-bar blockquote,.is-style-accent-bar cite{margin:0;text-align:start}'
				. '.is-style-accent-bar p{font-size:clamp(1.25rem,1rem + 1.2vw,1.75rem);line-height:1.35}'
				. '.is-style-accent-bar cite{display:block;margin-block-start:.75em;font-size:1rem;font-style:normal;font-weight:600}',
		);
	}

	/**
	 * Which features a list of parsed blocks uses.
	 *
	 * @param array<int, array<string, mixed>> $blocks Parsed blocks.
	 * @return array<string, bool>
	 */
	public static function used( array $blocks ): array {
		$used  = array();
		$stack = $blocks;
		while ( array() !== $stack ) {
			$block = array_shift( $stack );
			if ( ! is_array( $block ) ) {
				continue;
			}
			foreach ( (array) ( $block['innerBlocks'] ?? array() ) as $child ) {
				$stack[] = $child;
			}
			$html = (string) ( $block['innerHTML'] ?? '' );
			if ( str_contains( $html, '<mark data-hl=' ) ) {
				$used['highlight'] = true;
			}
			// Attributes too: the rich text of a dynamic block (ours) lives in its attributes.
			$attrs = (string) wp_json_encode( $block['attrs'] ?? array() );
			if ( str_contains( $attrs, 'data-hl=' ) ) {
				$used['highlight'] = true;
			}
			$class = (string) ( $block['attrs']['className'] ?? '' );
			if ( 'core/paragraph' === ( $block['blockName'] ?? '' ) && 1 === preg_match( '/(^|\s)is-style-drop-cap-boxed(\s|$)/', $class ) ) {
				$used['dropcap'] = true;
			}
			if ( 'core/pullquote' === ( $block['blockName'] ?? '' ) && 1 === preg_match( '/(^|\s)is-style-accent-bar(\s|$)/', $class ) ) {
				$used['pullquote'] = true;
			}
		}

		return $used;
	}

	/**
	 * `pbsw_page_css_extra`: the rules this page needs.
	 *
	 * @param mixed $css    CSS so far.
	 * @param mixed $blocks Parsed blocks.
	 * @return string
	 */
	public static function page_css( $css, $blocks = array() ): string {
		$out   = (string) $css;
		$rules = self::rules();
		foreach ( array_keys( self::used( is_array( $blocks ) ? $blocks : array() ) ) as $feature ) {
			$out .= $rules[ $feature ];
		}

		return $out;
	}

	/**
	 * The same rules in the editor (canvas included: `enqueue_block_assets` reaches the iframe),
	 * attached to the design-system blocks' editor stylesheet. wp-admin only.
	 *
	 * @return void
	 */
	public static function editor_css(): void {
		if ( ! is_admin() || ! wp_style_is( 'pbsw-blocks-editor-style', 'enqueued' ) ) {
			return;
		}
		wp_add_inline_style( 'pbsw-blocks-editor-style', implode( '', self::rules() ) );
	}
}
