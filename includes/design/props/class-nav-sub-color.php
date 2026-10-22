<?php
/**
 * Design prop `navSubColor` (NAV1, pbs/nav-menu).
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
 * Submenu text: sets `--<prefix>-nav-sub-color`, which assets/blocks/nav-menu.css reads, so it follows the
 * theme's tokens and can differ per breakpoint. Twin: src/design/props/nav-sub-color.js.
 */
final class Nav_Sub_Color extends Color_Prop {

	/** Stored key. */
	public const KEY = 'navSubColor';

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
		return array( '--' . $prefix . '-nav-sub-color' => Values::css( $value, $prefix ) );
	}
}
