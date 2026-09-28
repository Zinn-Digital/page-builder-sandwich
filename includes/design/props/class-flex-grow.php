<?php
/**
 * Design prop `flexGrow` (pbs-p4).
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
 * Grow. Twin: src/design/props/flex-grow.js.
 */
final class Flex_Grow extends Number_Prop {

	/** Stored key. */
	public const KEY = 'flexGrow';

	/** Panel section. */
	public const GROUP = 'layout';

	/** CSS property. */
	public const PROPERTY = 'flex-grow';

	/** Smallest value. */
	public const MIN = 0;

	/** Largest value. */
	public const MAX = 100;
}
