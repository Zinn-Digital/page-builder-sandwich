<?php
/**
 * Design prop `fontSize` (pbs-p4).
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
 * Size. Twin: src/design/props/font-size.js.
 */
final class Font_Size extends Length_Prop {

	/** Stored key. */
	public const KEY = 'fontSize';

	/** Panel section. */
	public const GROUP = 'typography';

	/** CSS property. */
	public const PROPERTY = 'font-size';

	/** Values::length() options. */
	public const OPTS = array(
		'tokens' => array( 'size', 'var' ),
	);
}
