<?php
/**
 * Design prop `colSpan` (pbs-p4, pbs-d1).
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Design\Props;

use ZinnDigital\PBS\Design\Kinds\Span_Prop;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Columns this block spans in its parent grid. Twin: src/design/props/col-span.js.
 */
final class Col_Span extends Span_Prop {

	/** Stored key. */
	public const KEY = 'colSpan';

	/** CSS property. */
	public const PROPERTY = 'grid-column';
}
