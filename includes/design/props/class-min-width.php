<?php
/**
 * Design prop `minWidth` (pbs-p4).
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
 * Min width. Twin: src/design/props/min-width.js.
 */
final class Min_Width extends Length_Prop {

	/** Stored key. */
	public const KEY = 'minWidth';

	/** Panel section. */
	public const GROUP = 'size';

	/** CSS property. */
	public const PROPERTY = 'min-inline-size';

	/** Values::length() options. */
	public const OPTS = array(
		'keywords' => array( 'auto', 'fit-content', 'min-content', 'max-content' ),
		'tokens'   => array( 'var' ),
	);
}
