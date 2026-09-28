<?php
/**
 * Design prop `flexDirection` (pbs-p4).
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Design\Props;

use ZinnDigital\PBS\Design\Kinds\Choice_Prop;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Direction. Twin: src/design/props/flex-direction.js.
 */
final class Flex_Direction extends Choice_Prop {

	/** Stored key. */
	public const KEY = 'flexDirection';

	/** Panel section. */
	public const GROUP = 'layout';

	/** Lands on the element that lays out the children. */
	public const TARGET = 'layout';

	/** CSS property. */
	public const PROPERTY = 'flex-direction';

	/** Allowed values. */
	public const CHOICES = array( 'row', 'row-reverse', 'column', 'column-reverse' );
}
