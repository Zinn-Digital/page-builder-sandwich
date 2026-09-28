<?php
/**
 * Design prop `minHeight` (pbs-p4).
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Design\Props;

use ZinnDigital\PBS\Design\Kinds\Length_Prop;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Min height. Twin: src/design/props/min-height.js.
 */
final class Min_Height extends Length_Prop {

	/** Stored key. */
	public const KEY = 'minHeight';

	/** Panel section. */
	public const GROUP = 'size';

	/** CSS property. */
	public const PROPERTY = 'min-block-size';

	/** Values::length() options. */
	public const OPTS = array(
		'keywords' => array( 'auto', 'fit-content', 'min-content', 'max-content' ),
		'tokens'   => array( 'var' ),
	);
}
