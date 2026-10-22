<?php
/**
 * Design prop `containerType` (DESIGN1).
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Design\Props;

use ZinnDigital\PBS\Design\Kinds\Choice_Prop;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Makes an element a query container, so `@container` at-rule presets (the
 * `pbsw_style_at_rules` filter) can style what is inside it by its width. Twin:
 * src/design/props/container-type.js.
 */
final class Container_Type extends Choice_Prop {

	/** Stored key. */
	public const KEY = 'containerType';

	/** Panel section. */
	public const GROUP = 'advanced';

	/** CSS property. */
	public const PROPERTY = 'container-type';

	/** Allowed values. */
	public const CHOICES = array( 'normal', 'inline-size', 'size' );
}
