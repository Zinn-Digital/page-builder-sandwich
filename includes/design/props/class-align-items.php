<?php
/**
 * Design prop `alignItems` (pbs-p4).
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
 * Align items. Twin: src/design/props/align-items.js.
 */
final class Align_Items extends Choice_Prop {

	/** Stored key. */
	public const KEY = 'alignItems';

	/** Panel section. */
	public const GROUP = 'layout';

	/** Lands on the element that lays out the children. */
	public const TARGET = 'layout';

	/** CSS property. */
	public const PROPERTY = 'align-items';

	/** Allowed values. */
	public const CHOICES = array( 'start', 'center', 'end', 'stretch', 'baseline' );
}
