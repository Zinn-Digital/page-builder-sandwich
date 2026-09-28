<?php
/**
 * Design prop `height` (pbs-p4).
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
 * Height. Twin: src/design/props/height.js.
 */
final class Height extends Length_Prop {

	/** Stored key. */
	public const KEY = 'height';

	/** Panel section. */
	public const GROUP = 'size';

	/** CSS property. */
	public const PROPERTY = 'block-size';

	/** Values::length() options. */
	public const OPTS = array(
		'keywords' => array( 'auto', 'fit-content', 'min-content', 'max-content' ),
		'tokens'   => array( 'var' ),
	);
}
