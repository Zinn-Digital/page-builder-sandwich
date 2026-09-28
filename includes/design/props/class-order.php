<?php
/**
 * Design prop `order` (pbs-p4).
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
 * Order. Twin: src/design/props/order.js.
 */
final class Order extends Number_Prop {

	/** Stored key. */
	public const KEY = 'order';

	/** Panel section. */
	public const GROUP = 'layout';

	/** CSS property. */
	public const PROPERTY = 'order';

	/** Smallest value. */
	public const MIN = -99;

	/** Largest value. */
	public const MAX = 99;

	/** Whole numbers only. */
	public const INTEGER = true;
}
