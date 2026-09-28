<?php
/**
 * Design prop `inset` (pbs-p4).
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
 * Offsets. Twin: src/design/props/inset.js.
 */
final class Inset extends Sides_Prop {

	/** Stored key. */
	public const KEY = 'inset';

	/** Panel section. */
	public const GROUP = 'position';

	/** CSS property pattern. */
	public const PATTERN = 'inset-%s';

	/** Values::length() options. */
	public const OPTS = array(
		'neg'      => true,
		'keywords' => array( 'auto' ),
		'tokens'   => array( 'space', 'var' ),
	);
}
