<?php
/**
 * Design prop `rowSpan` (pbs-p4, pbs-d1).
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
 * Rows this block spans in its parent grid. Twin: src/design/props/row-span.js.
 */
final class Row_Span extends Span_Prop {

	/** Stored key. */
	public const KEY = 'rowSpan';

	/** CSS property. */
	public const PROPERTY = 'grid-row';
}
