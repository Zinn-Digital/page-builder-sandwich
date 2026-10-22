<?php
/**
 * Design prop `navToggleBg` (NAV1, pbs/nav-menu).
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
 * Menu button background: sets `--<prefix>-nav-toggle-bg`, which assets/blocks/nav-menu.css reads, so it follows the
 * theme's tokens and can differ per breakpoint. Twin: src/design/props/nav-toggle-bg.js.
 */
final class Nav_Toggle_Bg extends Color_Prop {

	/** Stored key. */
	public const KEY = 'navToggleBg';

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
		return array( '--' . $prefix . '-nav-toggle-bg' => Values::css( $value, $prefix ) );
	}
}
