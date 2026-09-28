<?php
/**
 * Design prop `radius` (pbs-p4).
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
 * Corner radius. Twin: src/design/props/radius.js.
 */
final class Radius extends Sides_Prop {

	/** Stored key. */
	public const KEY = 'radius';

	/** Panel section. */
	public const GROUP = 'border';

	/** Stored corner key → CSS corner. */
	public const SIDES = array(
		'startStart' => 'start-start',
		'startEnd'   => 'start-end',
		'endStart'   => 'end-start',
		'endEnd'     => 'end-end',
	);

	/** CSS property pattern. */
	public const PATTERN = 'border-%s-radius';

	/** Values::length() options. */
	public const OPTS = array(
		'tokens' => array( 'radius', 'var' ),
	);
}
