<?php
/**
 * Design prop `textDecoration` (pbs-p4).
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
 * Decoration. Twin: src/design/props/text-decoration.js.
 */
final class Text_Decoration extends Choice_Prop {

	/** Stored key. */
	public const KEY = 'textDecoration';

	/** Panel section. */
	public const GROUP = 'typography';

	/** CSS property. */
	public const PROPERTY = 'text-decoration-line';

	/** Allowed values. */
	public const CHOICES = array( 'none', 'underline', 'overline', 'line-through' );
}
