<?php
/**
 * Design prop `lineHeight` (pbs-p4).
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
 * Line height. Twin: src/design/props/line-height.js.
 */
final class Line_Height extends Length_Prop {

	/** Stored key. */
	public const KEY = 'lineHeight';

	/** Panel section. */
	public const GROUP = 'typography';

	/** CSS property. */
	public const PROPERTY = 'line-height';

	/** Values::length() options. */
	public const OPTS = array(
		'unitless' => true,
		'keywords' => array( 'normal' ),
		'tokens'   => array( 'var' ),
	);
}
