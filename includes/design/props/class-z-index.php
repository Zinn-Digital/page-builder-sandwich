<?php
/**
 * Design prop `zIndex` (pbs-p4).
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Design\Props;

use ZinnDigital\PBS\Design\Kinds\Number_Prop;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stack order (z-index). Twin: src/design/props/z-index.js.
 */
final class Z_Index extends Number_Prop {

	/** Stored key. */
	public const KEY = 'zIndex';

	/** Panel section. */
	public const GROUP = 'position';

	/** CSS property. */
	public const PROPERTY = 'z-index';

	/** Smallest value. */
	public const MIN = -9999;

	/** Largest value. */
	public const MAX = 9999;

	/** Whole numbers only. */
	public const INTEGER = true;
}
