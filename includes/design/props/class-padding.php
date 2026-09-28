<?php
/**
 * Design prop `padding` (pbs-p4).
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
 * Padding. Twin: src/design/props/padding.js.
 */
final class Padding extends Sides_Prop {

	/** Stored key. */
	public const KEY = 'padding';

	/** Panel section. */
	public const GROUP = 'spacing';

	/** CSS property pattern. */
	public const PATTERN = 'padding-%s';

	/** Values::length() options. */
	public const OPTS = array(
		'tokens' => array( 'space', 'var' ),
	);
}
