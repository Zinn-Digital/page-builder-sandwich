<?php
/**
 * Core's block parser, reading the earlier `pbs/` block names as `pbsw/`.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Parses as core does, then translates the names.
 */
final class Block_Parser extends \WP_Block_Parser {

	/**
	 * Parse a document.
	 *
	 * @param string $document The document.
	 * @return array<int, array<string, mixed>>
	 */
	public function parse( $document ) {
		return Block_Names::upgrade_blocks( parent::parse( $document ) );
	}
}
