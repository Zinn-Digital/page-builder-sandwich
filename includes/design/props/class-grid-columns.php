<?php
/**
 * Design prop `gridColumns` (pbs-p4).
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
 * Columns. Twin: src/design/props/grid-columns.js.
 */
final class Grid_Columns extends Template_Prop {

	/** Stored key. */
	public const KEY = 'gridColumns';

	/** CSS property. */
	public const PROPERTY = 'grid-template-columns';

	/** One track of a counted template. */
	public const TRACK = 'minmax(0,1fr)';
}
