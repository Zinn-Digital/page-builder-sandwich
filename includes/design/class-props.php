<?php
/**
 * The design prop registry (pbs-p4).
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Design;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every prop the compiler knows, in output order. One file per prop under props/, named
 * `class-<slug>.php`, holding `Props\<Slug_In_Words>`; the JS twin is src/design/props/<slug>.js and
 * is listed in the same order in src/design/props/index.js.
 *
 * ⭐ To add a prop (effects, P5 blocks): add its two files and ONE line to INDEX here and in
 * index.js, and a case to wp/tests/fixtures/pbs-design/props.json. Nothing else changes: the
 * compiler, the render filter and the panel read the registry.
 */
final class Props {

	/** Stored key → file slug. Order is the order declarations are written in. */
	public const INDEX = array(
		// Layout.
		'display'         => 'display',
		'flexDirection'   => 'flex-direction',
		'flexWrap'        => 'flex-wrap',
		'justifyContent'  => 'justify-content',
		'alignItems'      => 'align-items',
		'gridColumns'     => 'grid-columns',
		'gridRows'        => 'grid-rows',
		'gridAreas'       => 'grid-areas',
		'placeItems'      => 'place-items',
		'gap'             => 'gap',
		'contentWidth'    => 'content-width',
		'alignSelf'       => 'align-self',
		'colSpan'         => 'col-span',
		'rowSpan'         => 'row-span',
		'gridArea'        => 'grid-area',
		'flexGrow'        => 'flex-grow',
		'flexShrink'      => 'flex-shrink',
		'flexBasis'       => 'flex-basis',
		'order'           => 'order',
		// Spacing.
		'padding'         => 'padding',
		'margin'          => 'margin',
		// Size.
		'width'           => 'width',
		'minWidth'        => 'min-width',
		'maxWidth'        => 'max-width',
		'height'          => 'height',
		'minHeight'       => 'min-height',
		'maxHeight'       => 'max-height',
		'aspectRatio'     => 'aspect-ratio',
		// Typography.
		'fontFamily'      => 'font-family',
		'fontSize'        => 'font-size',
		'fontWeight'      => 'font-weight',
		'fontStyle'       => 'font-style',
		'lineHeight'      => 'line-height',
		'letterSpacing'   => 'letter-spacing',
		'textTransform'   => 'text-transform',
		'textDecoration'  => 'text-decoration',
		'textAlign'       => 'text-align',
		// Colour and background.
		'color'           => 'color',
		'backgroundColor' => 'background-color',
		'backgroundImage' => 'background-image',
		// Border.
		'border'          => 'border',
		'radius'          => 'radius',
		// Shadow.
		'boxShadow'       => 'box-shadow',
		'textShadow'      => 'text-shadow',
		// Position.
		'position'        => 'position',
		'inset'           => 'inset',
		'zIndex'          => 'z-index',
		// Effects: W3 (pbs-r14) adds its lines here.
		// Advanced.
		'overflow'        => 'overflow',
		'opacity'         => 'opacity',
		'transition'      => 'transition',
		'cursor'          => 'cursor',
	);

	/**
	 * Built props, key → instance.
	 *
	 * @var array<string, Prop>|null
	 */
	private static ?array $props = null;

	/**
	 * Every prop, in output order.
	 *
	 * @return array<string, Prop>
	 */
	public static function all(): array {
		if ( null !== self::$props ) {
			return self::$props;
		}
		$props = array();
		foreach ( self::INDEX as $key => $slug ) {
			require_once __DIR__ . '/props/class-' . $slug . '.php';
			$class = __NAMESPACE__ . '\\Props\\' . str_replace( ' ', '_', ucwords( str_replace( '-', ' ', $slug ) ) );
			$prop  = new $class();
			if ( $prop instanceof Prop && $prop->key() === $key ) {
				$props[ $key ] = $prop;
			}
		}
		/**
		 * Filters the design props (the premium layer adds its own).
		 *
		 * @param array<string, Prop> $props Key → prop, in output order.
		 */
		$filtered = apply_filters( 'pbsw_design_props', $props );
		$out      = array();
		foreach ( is_array( $filtered ) ? $filtered : $props as $key => $prop ) {
			if ( $prop instanceof Prop && is_string( $key ) && $prop->key() === $key ) {
				$out[ $key ] = $prop;
			}
		}
		self::$props = $out;

		return self::$props;
	}

	/**
	 * One prop.
	 *
	 * @param string $key Stored key.
	 * @return Prop|null
	 */
	public static function get( string $key ): ?Prop {
		return self::all()[ $key ] ?? null;
	}

	/**
	 * Forget the built list (tests; a filter added late).
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$props = null;
	}

	/**
	 * Autoloader for the free prop classes. Other code calls their static helpers directly
	 * (`Background_Image::url()` from the Pro effects and mask), and that must work before
	 * anything in the request has built the registry. A REST request often has not.
	 * Only classes whose file is listed in INDEX are loaded.
	 *
	 * @param string $name Fully qualified class name.
	 * @return void
	 */
	public static function autoload( string $name ): void {
		$ns = __NAMESPACE__ . '\\Props\\';
		if ( ! str_starts_with( $name, $ns ) ) {
			return;
		}
		$slug = strtolower( str_replace( '_', '-', substr( $name, strlen( $ns ) ) ) );
		if ( in_array( $slug, self::INDEX, true ) ) {
			require_once __DIR__ . '/props/class-' . $slug . '.php';
		}
	}
}
