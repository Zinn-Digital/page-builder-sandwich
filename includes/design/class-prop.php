<?php
/**
 * The design prop contract (pbs-p4).
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Design;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One style property a block can carry in `pbs.s`. Every prop has a JS twin in
 * src/design/props/ with the same key and the same output (held together by
 * wp/tests/fixtures/pbs-design/props.json).
 *
 * ⛔ to_css() emits LOGICAL properties only (padding-block-start, inset-inline-start,
 * inline-size, text-align:start), so a page flips correctly in a right-to-left language.
 */
abstract class Prop {

	/** The key stored in `pbs.s.<breakpoint>`. */
	public const KEY = '';

	/** Panel section: layout, spacing, size, typography, colour, border, shadow, position, effects, advanced. */
	public const GROUP = 'advanced';

	/**
	 * Which element of a block the declarations land on: '' = the block's own element,
	 * 'layout' = the element that lays out the children (a boxed section's inner wrapper).
	 */
	public const TARGET = '';

	/** Block names the prop applies to; empty = every block. */
	public const BLOCKS = array();

	/**
	 * Does the prop apply to a block?
	 *
	 * @param string $block_name Block name.
	 * @return bool
	 */
	public function applies_to( string $block_name ): bool {
		return array() === static::BLOCKS || in_array( $block_name, static::BLOCKS, true );
	}

	/**
	 * The stored key.
	 *
	 * @return string
	 */
	public function key(): string {
		return static::KEY;
	}

	/**
	 * The panel section.
	 *
	 * @return string
	 */
	public function group(): string {
		return static::GROUP;
	}

	/**
	 * The target element ('' or 'layout').
	 *
	 * @return string
	 */
	public function target(): string {
		return static::TARGET;
	}

	/**
	 * Validate a stored value.
	 *
	 * @param mixed $value Untrusted value.
	 * @return mixed The safe value, or null to drop it.
	 */
	abstract public function sanitize( $value );

	/**
	 * Declarations for a value that sanitize() returned.
	 *
	 * @param mixed  $value  Sanitised value.
	 * @param string $prefix Class prefix (Pro token variables are named with it).
	 * @return array<string, string> property => value.
	 */
	abstract public function to_css( $value, string $prefix ): array;
}
