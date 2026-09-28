<?php
/**
 * Design prop `fontStyle` (pbs-p4).
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
 * Style. Twin: src/design/props/font-style.js.
 */
final class Font_Style extends Choice_Prop {

	/** Stored key. */
	public const KEY = 'fontStyle';

	/** Panel section. */
	public const GROUP = 'typography';

	/** CSS property. */
	public const PROPERTY = 'font-style';

	/** Allowed values. */
	public const CHOICES = array( 'normal', 'italic' );
}
