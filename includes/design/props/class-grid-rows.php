<?php
/**
 * Design prop `gridRows` (pbs-p4).
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Design\Props;

use ZinnDigital\PBS\Design\Kinds\Template_Prop;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rows. Twin: src/design/props/grid-rows.js.
 */
final class Grid_Rows extends Template_Prop {

	/** Stored key. */
	public const KEY = 'gridRows';

	/** CSS property. */
	public const PROPERTY = 'grid-template-rows';

	/** One track of a counted template. */
	public const TRACK = 'auto';
}
