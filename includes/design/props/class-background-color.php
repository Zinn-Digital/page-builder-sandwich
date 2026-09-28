<?php
/**
 * Design prop `backgroundColor` (pbs-p4).
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Design\Props;

use ZinnDigital\PBS\Design\Kinds\Color_Prop;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Background colour. Twin: src/design/props/background-color.js.
 */
final class Background_Color extends Color_Prop {

	/** Stored key. */
	public const KEY = 'backgroundColor';

	/** CSS property. */
	public const PROPERTY = 'background-color';
}
