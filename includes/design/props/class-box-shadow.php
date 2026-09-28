<?php
/**
 * Design prop `boxShadow` (pbs-p4).
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
 * Box shadow. Twin: src/design/props/box-shadow.js.
 */
final class Box_Shadow extends Shadow_Prop {

	/** Stored key. */
	public const KEY = 'boxShadow';

	/** CSS property. */
	public const PROPERTY = 'box-shadow';
}
