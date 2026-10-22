<?php
/**
 * Design prop `navDrawerColor` (NAV1, pbs/nav-menu).
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Design\Props;

use ZinnDigital\PBS\Design\Kinds\Color_Prop;
use ZinnDigital\PBS\Design\Values;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mobile menu text: sets `--<prefix>-nav-drawer-color`, which assets/blocks/nav-menu.css reads, so it follows the
 * theme's tokens and can differ per breakpoint. Twin: src/design/props/nav-drawer-color.js.
 */
final class Nav_Drawer_Color extends Color_Prop {

	/** Stored key. */
	public const KEY = 'navDrawerColor';

	/** Panel section. */
	public const GROUP = 'menu';

	/** The block it styles. */
	public const BLOCKS = array( 'pbs/nav-menu' );

	/**
	 * {@inheritDoc}
	 *
	 * @param mixed  $value  Sanitised value.
	 * @param string $prefix Class prefix.
	 * @return array<string, string>
	 */
	public function to_css( $value, string $prefix ): array {
		return array( '--' . $prefix . '-nav-drawer-color' => Values::css( $value, $prefix ) );
	}
}
