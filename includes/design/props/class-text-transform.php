<?php
/**
 * Design prop `textTransform` (pbs-p4).
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
 * Letter case. Twin: src/design/props/text-transform.js.
 */
final class Text_Transform extends Choice_Prop {

	/** Stored key. */
	public const KEY = 'textTransform';

	/** Panel section. */
	public const GROUP = 'typography';

	/** CSS property. */
	public const PROPERTY = 'text-transform';

	/** Allowed values. */
	public const CHOICES = array( 'none', 'uppercase', 'lowercase', 'capitalize' );
}
