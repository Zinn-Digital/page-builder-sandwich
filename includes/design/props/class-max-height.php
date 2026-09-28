<?php
/**
 * Design prop `maxHeight` (pbs-p4).
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
 * Max height. Twin: src/design/props/max-height.js.
 */
final class Max_Height extends Length_Prop {

	/** Stored key. */
	public const KEY = 'maxHeight';

	/** Panel section. */
	public const GROUP = 'size';

	/** CSS property. */
	public const PROPERTY = 'max-block-size';

	/** Values::length() options. */
	public const OPTS = array(
		'keywords' => array( 'none', 'fit-content', 'min-content', 'max-content' ),
		'tokens'   => array( 'var' ),
	);
}
