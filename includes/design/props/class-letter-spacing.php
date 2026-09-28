<?php
/**
 * Design prop `letterSpacing` (pbs-p4).
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
 * Letter spacing. Twin: src/design/props/letter-spacing.js.
 */
final class Letter_Spacing extends Length_Prop {

	/** Stored key. */
	public const KEY = 'letterSpacing';

	/** Panel section. */
	public const GROUP = 'typography';

	/** CSS property. */
	public const PROPERTY = 'letter-spacing';

	/** Values::length() options. */
	public const OPTS = array(
		'neg'      => true,
		'keywords' => array( 'normal' ),
		'tokens'   => array( 'var' ),
	);
}
