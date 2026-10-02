<?php
/**
 * The style compiler (pbs-p4).
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Design;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns the `pbs` attribute of every block in a parsed tree into CSS rules:
 *
 *   .<p>-s-<id>{…}  .<p>-s-<id>:hover{…}                      base (desktop)
 *
 *   @media (min-width:1440px){…}                                 Pro "from a width" breakpoints
 *   @media (max-width:1024px){…} @media (max-width:767px){…}     tablet, mobile, Pro "up to"
 *   @media (min-width:768px) and (max-width:1024px){.<p>-hide-tablet{display:none!important}}
 *   custom element CSS (Pro, d8)
 *
 * ⛔ Pure: no database, no globals beyond the prop registry and the filters it documents. The
 * render filter, the per-page sheet and the fallback `<style>` all call the same function, and
 * src/design/compiler.js is its twin in the editor.
 */
final class Compiler {

	/** A valid element id. */
	public const ID_PATTERN = '/^[a-z0-9]{8}$/';

	/** Longest custom element CSS accepted. */
	private const MAX_CUSTOM_CSS = 20000;

	/**
	 * Compile every block in a parsed tree (inner blocks included).
	 *
	 * @param array<int, array<string, mixed>> $blocks      Parsed blocks.
	 * @param string                           $prefix      Class prefix.
	 * @param array<string, mixed>|null        $breakpoints Breakpoints config (tests); null reads the option.
	 * @return string
	 */
	public static function css( array $blocks, string $prefix, ?array $breakpoints = null ): string {
		$list = Breakpoints::media_list( $breakpoints );
		$ids  = array_merge( array( 'base' ), array_column( $list, 'id' ) );

		$rules  = array_fill_keys( $ids, '' );
		$hides  = array();
		$custom = '';
		$seen   = array();
		self::walk( $blocks, $prefix, $ids, $rules, $hides, $custom, $seen );

		$css = $rules['base'];
		foreach ( $list as $bp ) {
			if ( '' !== $rules[ $bp['id'] ] ) {
				$css .= '@media ' . Breakpoints::media( $bp ) . '{' . $rules[ $bp['id'] ] . '}';
			}
		}
		foreach ( $ids as $id ) {
			if ( isset( $hides[ $id ] ) ) {
				$range = (string) Breakpoints::range( $id, $breakpoints );
				$rule  = '.' . $prefix . '-hide-' . $id . '{display:none!important}';
				$css  .= '' === $range ? $rule : '@media ' . $range . '{' . $rule . '}';
			}
		}
		if ( str_contains( $css, 'transition-duration:' ) ) {
			// A visitor who asks for reduced motion gets the end state at once.
			$css .= '@media (prefers-reduced-motion:reduce){[class*="' . $prefix . '-s-"]{transition:none!important}}';
		}

		return $css . $custom;
	}

	/**
	 * Collect rules from a block tree.
	 *
	 * @param array<int, mixed>     $blocks Parsed blocks.
	 * @param string                $prefix Prefix.
	 * @param array<int, string>    $ids    Known breakpoint ids.
	 * @param array<string, string> $rules  Rules per breakpoint (by reference).
	 * @param array<string, bool>   $hides  Breakpoints some block hides on (by reference).
	 * @param string                $custom Custom element CSS (by reference).
	 * @param array<string, bool>   $seen   Element ids already written (by reference).
	 * @return void
	 */
	private static function walk( array $blocks, string $prefix, array $ids, array &$rules, array &$hides, string &$custom, array &$seen ): void {
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			$pbs = $block['attrs']['pbs'] ?? null;
			if ( is_array( $pbs ) ) {
				foreach ( self::hides( $pbs, $ids ) as $bp ) {
					$hides[ $bp ] = true;
				}
				$id = is_string( $pbs['id'] ?? null ) ? $pbs['id'] : '';
				if ( 1 === preg_match( self::ID_PATTERN, $id ) && ! isset( $seen[ $id ] ) ) {
					$seen[ $id ] = true;
					foreach ( self::block_rules( $block, $prefix, $ids ) as $bp => $css ) {
						$rules[ $bp ] .= $css;
					}
					$custom .= self::custom_css( $pbs, self::selector( $prefix, $id ) );
				}
			}
			if ( is_array( $block['innerBlocks'] ?? null ) ) {
				self::walk( $block['innerBlocks'], $prefix, $ids, $rules, $hides, $custom, $seen );
			}
		}
	}

	/**
	 * One block's rules, per breakpoint (normal then hover), without media wrappers.
	 *
	 * @param array<string, mixed> $block  Parsed block.
	 * @param string               $prefix Prefix.
	 * @param array<int, string>   $ids    Known breakpoint ids.
	 * @return array<string, string> breakpoint id → rules.
	 */
	public static function block_rules( array $block, string $prefix, array $ids ): array {
		$pbs = $block['attrs']['pbs'] ?? null;
		$id  = is_array( $pbs ) && is_string( $pbs['id'] ?? null ) ? $pbs['id'] : '';
		if ( 1 !== preg_match( self::ID_PATTERN, $id ) || ! is_array( $pbs['s'] ?? null ) ) {
			return array();
		}
		$name     = (string) ( $block['blockName'] ?? '' );
		$selector = self::selector( $prefix, $id );
		$suffix   = self::layout_suffix( $block, $prefix );
		$out      = array();
		foreach ( $ids as $bp ) {
			$css = '';
			foreach ( array(
				''       => '',
				':hover' => ':hover',
			) as $state => $pseudo ) {
				$values = $pbs['s'][ $bp . $state ] ?? null;
				if ( ! is_array( $values ) ) {
					continue;
				}
				$decl = self::declarations( $values, $prefix, $name );
				if ( '' === $suffix ) {
					$css .= '' === $decl['all'] ? '' : $selector . $pseudo . '{' . $decl['all'] . '}';
					continue;
				}
				if ( '' !== $decl[''] ) {
					$css .= $selector . $pseudo . '{' . $decl[''] . '}';
				}
				if ( '' !== $decl['layout'] ) {
					$css .= $selector . $pseudo . $suffix . '{' . $decl['layout'] . '}';
				}
			}
			if ( '' !== $css ) {
				$out[ $bp ] = $css;
			}
		}

		return $out;
	}

	/**
	 * Declarations for one breakpoint's values, split by target element, in registry order.
	 * Unknown keys and invalid values are dropped.
	 *
	 * @param array<string, mixed> $values     Stored prop values.
	 * @param string               $prefix     Prefix.
	 * @param string               $block_name Block name.
	 * @return array{'': string, layout: string, all: string}
	 */
	public static function declarations( array $values, string $prefix, string $block_name = '' ): array {
		$out = array(
			''       => '',
			'layout' => '',
			'all'    => '',
		);
		foreach ( Props::all() as $key => $prop ) {
			if ( ! array_key_exists( $key, $values ) || ! $prop->applies_to( $block_name ) ) {
				continue;
			}
			$clean = $prop->sanitize( $values[ $key ] );
			if ( null === $clean ) {
				continue;
			}
			$target = 'layout' === $prop->target() ? 'layout' : '';
			foreach ( $prop->to_css( $clean, $prefix ) as $property => $value ) {
				$out[ $target ] .= $property . ':' . $value . ';';
				$out['all']     .= $property . ':' . $value . ';';
			}
		}
		foreach ( $out as $k => $v ) {
			$out[ $k ] = rtrim( $v, ';' );
		}

		return $out;
	}

	/**
	 * Does a block have anything the compiler would write for it?
	 *
	 * @param array<string, mixed> $block  Parsed block.
	 * @param string               $prefix Prefix.
	 * @return bool
	 */
	public static function has_styles( array $block, string $prefix ): bool {
		$pbs = $block['attrs']['pbs'] ?? null;
		if ( ! is_array( $pbs ) ) {
			return false;
		}
		if ( array() !== self::block_rules( $block, $prefix, Breakpoints::ids() ) ) {
			return true;
		}
		$id = is_string( $pbs['id'] ?? null ) ? $pbs['id'] : '';

		return 1 === preg_match( self::ID_PATTERN, $id ) && '' !== self::custom_css( $pbs, self::selector( $prefix, $id ) );
	}

	/**
	 * A block's own selector: its design class, twice. Specificity (0,2,0), so a theme rule
	 * scoped to the content area (`.entry-content :where(h1)`, (0,1,0), printed after this sheet)
	 * cannot silently override a value the Style tab set. Custom CSS uses the same selector and
	 * is printed after the block's rules, so it still wins as it did.
	 *
	 * @param string $prefix Prefix.
	 * @param string $id     Design id.
	 * @return string
	 */
	public static function selector( string $prefix, string $id ): string {
		$class = '.' . $prefix . '-s-' . $id;

		return $class . $class;
	}

	/**
	 * Valid breakpoint ids a block hides on.
	 *
	 * @param array<string, mixed> $pbs The `pbs` attribute.
	 * @param array<int, string>   $ids Known ids.
	 * @return array<int, string>
	 */
	public static function hides( array $pbs, array $ids ): array {
		$hide = is_array( $pbs['hide'] ?? null ) ? $pbs['hide'] : array();

		return array_values( array_unique( array_filter( $hide, static fn( $bp ): bool => is_string( $bp ) && in_array( $bp, $ids, true ) ) ) );
	}

	/**
	 * The selector suffix for the element that lays out a block's children, when it is not the
	 * block's own element: a boxed section or container lays out in its inner wrapper.
	 *
	 * @param array<string, mixed> $block  Parsed block.
	 * @param string               $prefix Prefix.
	 * @return string
	 */
	public static function layout_suffix( array $block, string $prefix ): string {
		$name = (string) ( $block['blockName'] ?? '' );
		// parse_blocks() leaves defaults out: a section is boxed unless it says otherwise.
		$width = $block['attrs']['width'] ?? ( 'pbs/section' === $name ? 'boxed' : 'full' );
		if ( in_array( $name, array( 'pbs/section', 'pbs/container' ), true ) && 'boxed' === $width ) {
			return '>.' . $prefix . '-' . substr( $name, 4 ) . '__in';
		}
		/**
		 * Filters the layout-element selector suffix for a block ('' = the block's own element).
		 *
		 * @param string               $suffix Suffix.
		 * @param array<string, mixed> $block  Parsed block.
		 * @param string               $prefix Prefix.
		 */
		$suffix = apply_filters( 'pbsw_design_layout_suffix', '', $block, $prefix );

		return is_string( $suffix ) && 1 === preg_match( '/^[>+~ ]?\s*\.[a-z0-9_-]+$/', $suffix ) ? $suffix : '';
	}

	/**
	 * Custom element CSS (Pro, d8): `selector` stands for this element. Kept only when the
	 * premium layer enables it, and never when it contains something that could escape the
	 * stylesheet or load anything.
	 *
	 * @param array<string, mixed> $pbs      The `pbs` attribute.
	 * @param string               $selector This element's selector.
	 * @return string
	 */
	public static function custom_css( array $pbs, string $selector ): string {
		$css = $pbs['css'] ?? null;
		if ( ! is_string( $css ) || '' === trim( $css ) || strlen( $css ) > self::MAX_CUSTOM_CSS ) {
			return '';
		}
		/**
		 * Filters whether per-element custom CSS is compiled (the premium layer enables it, d8).
		 *
		 * @param bool $enabled Default false.
		 */
		if ( ! apply_filters( 'pbsw_design_element_css', false ) ) {
			return '';
		}
		if ( ! self::safe_css( $css ) ) {
			return '';
		}

		return str_replace( 'selector', $selector, trim( $css ) );
	}

	/**
	 * Refuse CSS that could close the style element, import, run script or run an expression.
	 *
	 * @param string $css CSS.
	 * @return bool
	 */
	public static function safe_css( string $css ): bool {
		$lower = strtolower( $css );
		foreach ( array( '</', '<!--', '@import', 'expression(', 'javascript:', 'behavior:', '-moz-binding', '\\' ) as $bad ) {
			if ( str_contains( $lower, $bad ) ) {
				return false;
			}
		}
		// Braces must balance, or the rules after this element's would be swallowed.
		return substr_count( $css, '{' ) === substr_count( $css, '}' );
	}
}
