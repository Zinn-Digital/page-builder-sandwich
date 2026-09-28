<?php
/**
 * Design prop `alignSelf` (pbs-p4).
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
 * Align self. Twin: src/design/props/align-self.js.
 */
final class Align_Self extends Choice_Prop {

	/** Stored key. */
	public const KEY = 'alignSelf';

	/** Panel section. */
	public const GROUP = 'layout';

	/** CSS property. */
	public const PROPERTY = 'align-self';

	/** Allowed values. */
	public const CHOICES = array( 'auto', 'start', 'center', 'end', 'stretch', 'baseline' );
}
