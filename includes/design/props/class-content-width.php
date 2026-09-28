<?php
/**
 * Design prop `contentWidth` (pbs-p4, pbs-d1).
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Design\Props;

use ZinnDigital\PBS\Design\Kinds\Length_Prop;
use ZinnDigital\PBS\Design\Values;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The width of a boxed section's or container's content. Sets the custom property the
 * block's own stylesheet reads (`--<prefix>-cw`), so it can differ per breakpoint.
 * Twin: src/design/props/content-width.js.
 */
final class Content_Width extends Length_Prop {

	/** Stored key. */
	public const KEY = 'contentWidth';

	/** Panel section. */
	public const GROUP = 'layout';

	/** The blocks that have a boxed content area. */
	public const BLOCKS = array( 'pbs/section', 'pbs/container' );

	/** Values::length() options. */
	public const OPTS = array(
		'tokens' => array( 'var' ),
	);

	/**
	 * {@inheritDoc}
	 *
	 * @param mixed  $value  Sanitised value.
	 * @param string $prefix Class prefix.
	 * @return array<string, string>
	 */
	public function to_css( $value, string $prefix ): array {
		return array( '--' . $prefix . '-cw' => Values::css( $value, $prefix ) );
	}
}
