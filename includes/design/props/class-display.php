<?php
/**
 * Design prop `display` (pbs-p4).
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
 * Display. Twin: src/design/props/display.js.
 */
final class Display extends Choice_Prop {

	/** Stored key. */
	public const KEY = 'display';

	/** Panel section. */
	public const GROUP = 'layout';

	/** Lands on the element that lays out the children. */
	public const TARGET = 'layout';

	/** CSS property. */
	public const PROPERTY = 'display';

	/** Allowed values. */
	public const CHOICES = array( 'block', 'flex', 'grid', 'inline', 'inline-block', 'inline-flex', 'inline-grid', 'contents', 'none' );
}
