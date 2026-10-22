<?php
/**
 * Design prop `navDrawerWidth` (NAV1, pbs/nav-menu).
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Design\Props;

use ZinnDigital\PBS\Design\Kinds\Length_Prop;
use ZinnDigital\PBS\Design\Values;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mobile menu width: sets `--<prefix>-nav-drawer-width`, which assets/blocks/nav-menu.css reads, so it follows the
 * theme's tokens and can differ per breakpoint. Twin: src/design/props/nav-drawer-width.js.
 */
final class Nav_Drawer_Width extends Length_Prop {

	/** Stored key. */
	public const KEY = 'navDrawerWidth';

	/** Panel section. */
	public const GROUP = 'menu';

	/** The block it styles. */
	public const BLOCKS = array( 'pbs/nav-menu' );

	/** Values::length() options. */
	public const OPTS = array(
		'tokens' => array( 'var' ),
	);

	/**
	 * {@inheritDoc}
	 *
	 * @param mixed  $value  Sanitised value.
	 * @param string $prefix Class prefix.
	 * @return array<string, string>
	 */
	public function to_css( $value, string $prefix ): array {
		return array( '--' . $prefix . '-nav-drawer-width' => Values::css( $value, $prefix ) );
	}
}
