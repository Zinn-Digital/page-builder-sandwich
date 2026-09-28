<?php
/**
 * Design prop `cursor` (pbs-p4).
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
 * Cursor. Twin: src/design/props/cursor.js.
 */
final class Cursor extends Choice_Prop {

	/** Stored key. */
	public const KEY = 'cursor';

	/** Panel section. */
	public const GROUP = 'advanced';

	/** CSS property. */
	public const PROPERTY = 'cursor';

	/** Allowed values. */
	public const CHOICES = array( 'auto', 'default', 'pointer', 'text', 'move', 'grab', 'not-allowed', 'help', 'crosshair', 'zoom-in' );
}
