<?php
/**
 * Design prop `color` (pbs-p4).
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
 * Text colour. Twin: src/design/props/color.js.
 */
final class Color extends Color_Prop {

	/** Stored key. */
	public const KEY = 'color';

	/** CSS property. */
	public const PROPERTY = 'color';
}
