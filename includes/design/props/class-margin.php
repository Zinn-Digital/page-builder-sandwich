<?php
/**
 * Design prop `margin` (pbs-p4).
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Design\Props;

use ZinnDigital\PBS\Design\Kinds\Sides_Prop;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Margin. Twin: src/design/props/margin.js.
 */
final class Margin extends Sides_Prop {

	/** Stored key. */
	public const KEY = 'margin';

	/** Panel section. */
	public const GROUP = 'spacing';

	/** CSS property pattern. */
	public const PATTERN = 'margin-%s';

	/** Values::length() options. */
	public const OPTS = array(
		'neg'      => true,
		'keywords' => array( 'auto' ),
		'tokens'   => array( 'space', 'var' ),
	);
}
