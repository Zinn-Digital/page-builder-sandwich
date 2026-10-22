<?php
/**
 * Design prop `navSubShadow` (NAV1, pbs/nav-menu).
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Design\Props;

use ZinnDigital\PBS\Design\Kinds\Shadow_Prop;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Submenu shadow: sets `--<prefix>-nav-sub-shadow`, which assets/blocks/nav-menu.css reads, so it follows the
 * theme's tokens and can differ per breakpoint. Twin: src/design/props/nav-sub-shadow.js.
 */
final class Nav_Sub_Shadow extends Shadow_Prop {

	/** Stored key. */
	public const KEY = 'navSubShadow';

	/** Panel section. */
	public const GROUP = 'menu';

	/** The block it styles. */
	public const BLOCKS = array( 'pbs/nav-menu' );

	/** Read back by to_css() (any non-empty name: the output key is the custom property). */
	public const PROPERTY = 'box-shadow';

	/**
	 * {@inheritDoc}
	 *
	 * @param mixed  $value  Sanitised value.
	 * @param string $prefix Class prefix.
	 * @return array<string, string>
	 */
	public function to_css( $value, string $prefix ): array {
		return array( '--' . $prefix . '-nav-sub-shadow' => (string) ( parent::to_css( $value, $prefix )[ self::PROPERTY ] ?? '' ) );
	}
}
