<?php
/**
 * Design prop `justifyContent` (pbs-p4).
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
 * Justify content. Twin: src/design/props/justify-content.js.
 */
final class Justify_Content extends Choice_Prop {

	/** Stored key. */
	public const KEY = 'justifyContent';

	/** Panel section. */
	public const GROUP = 'layout';

	/** Lands on the element that lays out the children. */
	public const TARGET = 'layout';

	/** CSS property. */
	public const PROPERTY = 'justify-content';

	/** Allowed values. */
	public const CHOICES = array( 'start', 'center', 'end', 'space-between', 'space-around', 'space-evenly', 'stretch' );
}
