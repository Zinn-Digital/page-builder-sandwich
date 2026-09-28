<?php
/**
 * Design prop `flexShrink` (pbs-p4).
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
 * Shrink. Twin: src/design/props/flex-shrink.js.
 */
final class Flex_Shrink extends Number_Prop {

	/** Stored key. */
	public const KEY = 'flexShrink';

	/** Panel section. */
	public const GROUP = 'layout';

	/** CSS property. */
	public const PROPERTY = 'flex-shrink';

	/** Smallest value. */
	public const MIN = 0;

	/** Largest value. */
	public const MAX = 100;
}
