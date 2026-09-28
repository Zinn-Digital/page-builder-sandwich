<?php
/**
 * Design prop `textShadow` (pbs-p4).
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Design\Props;

use ZinnDigital\PBS\Design\Kinds\Shadow_Prop;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Text shadow (no spread, no inset). Twin: src/design/props/text-shadow.js.
 */
final class Text_Shadow extends Shadow_Prop {

	/** Stored key. */
	public const KEY = 'textShadow';

	/** CSS property. */
	public const PROPERTY = 'text-shadow';

	/** Text shadows take no spread and no inset. */
	public const BOX = false;
}
