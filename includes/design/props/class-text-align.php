<?php
/**
 * Design prop `textAlign` (pbs-p4).
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
 * Alignment. Twin: src/design/props/text-align.js.
 */
final class Text_Align extends Choice_Prop {

	/** Stored key. */
	public const KEY = 'textAlign';

	/** Panel section. */
	public const GROUP = 'typography';

	/** CSS property. */
	public const PROPERTY = 'text-align';

	/** Allowed values. */
	public const CHOICES = array( 'start', 'center', 'end', 'justify' );
}
