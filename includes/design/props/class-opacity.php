<?php
/**
 * Design prop `opacity` (pbs-p4).
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
 * Opacity. Twin: src/design/props/opacity.js.
 */
final class Opacity extends Number_Prop {

	/** Stored key. */
	public const KEY = 'opacity';

	/** Panel section. */
	public const GROUP = 'advanced';

	/** CSS property. */
	public const PROPERTY = 'opacity';

	/** Smallest value. */
	public const MIN = 0;

	/** Largest value. */
	public const MAX = 1;
}
