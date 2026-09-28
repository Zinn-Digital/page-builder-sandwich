<?php
/**
 * Design prop `flexBasis` (pbs-p4).
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
 * Basis. Twin: src/design/props/flex-basis.js.
 */
final class Flex_Basis extends Length_Prop {

	/** Stored key. */
	public const KEY = 'flexBasis';

	/** Panel section. */
	public const GROUP = 'layout';

	/** CSS property. */
	public const PROPERTY = 'flex-basis';

	/** Values::length() options. */
	public const OPTS = array(
		'keywords' => array( 'auto', 'content' ),
		'tokens'   => array( 'var' ),
	);
}
