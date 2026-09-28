<?php
/**
 * Design prop `overflow` (pbs-p4).
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
 * Overflow. Twin: src/design/props/overflow.js.
 */
final class Overflow extends Choice_Prop {

	/** Stored key. */
	public const KEY = 'overflow';

	/** Panel section. */
	public const GROUP = 'advanced';

	/** CSS property. */
	public const PROPERTY = 'overflow';

	/** Allowed values. */
	public const CHOICES = array( 'visible', 'hidden', 'clip', 'auto', 'scroll' );
}
