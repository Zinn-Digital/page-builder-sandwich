<?php
/**
 * Stored settings and the front-end class prefix.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The plugin's one option row, and the rules for the prefix it carries.
 */
final class Settings {

	/** The option name. */
	public const OPTION = 'pbsw_settings';

	/** The prefix every front-end class name, handle and asset folder starts with. */
	public const DEFAULT_PREFIX = 'zd';

	/**
	 * Words a prefix may not contain, because each one would put the plugin's identity back
	 * into the front-end HTML that F5 exists to keep neutral (CONTRACT §7).
	 */
	private const FOOTPRINT_WORDS = array( 'pbs', 'sandwich', 'tranzly', 'builder' );

	/**
	 * The stored settings merged over the defaults.
	 *
	 * `mcp` (default on, owner 2026-09-30): signed-in users with the right capability may drive the
	 * plugin through AI agents (MCP) and the abilities REST API. Off, neither is registered.
	 *
	 * @return array{prefix: string, mcp: bool}
	 */
	public static function get(): array {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return array(
			'prefix' => self::is_valid_prefix( (string) ( $stored['prefix'] ?? '' ) )
				? (string) $stored['prefix']
				: self::DEFAULT_PREFIX,
			'mcp'    => ! array_key_exists( 'mcp', $stored ) || true === $stored['mcp'],
		);
	}

	/**
	 * Save the settings. Anything invalid is refused rather than coerced.
	 *
	 * @param array<string, mixed> $input The new values.
	 * @return true|\WP_Error
	 */
	public static function save( array $input ) {
		$current = self::get();

		if ( array_key_exists( 'prefix', $input ) ) {
			$prefix = strtolower( trim( (string) $input['prefix'] ) );
			if ( ! self::is_valid_prefix( $prefix ) ) {
				return new \WP_Error(
					'pbsw_invalid_prefix',
					__( 'The prefix must be 1 to 8 lowercase letters or digits, start with a letter, and must not name the plugin.', 'page-builder-sandwich' ),
					array( 'status' => 400 )
				);
			}
			$current['prefix'] = $prefix;
		}
		if ( array_key_exists( 'mcp', $input ) ) {
			$current['mcp'] = (bool) $input['mcp'];
		}

		update_option( self::OPTION, $current, true );

		return true;
	}

	/**
	 * The prefix in effect for this request: the stored value, then the `pbsw_frontend_prefix`
	 * filter, then validation. An invalid filtered value falls back to the default rather than
	 * reaching the HTML.
	 *
	 * @return string
	 */
	public static function prefix(): string {
		/**
		 * Filters the neutral front-end class prefix.
		 *
		 * @param string $prefix The stored prefix, `zd` by default.
		 */
		$prefix = (string) apply_filters( 'pbsw_frontend_prefix', self::get()['prefix'] );

		return self::is_valid_prefix( $prefix ) ? $prefix : self::DEFAULT_PREFIX;
	}

	/**
	 * Is this a usable prefix? One lowercase letter followed by up to seven lowercase letters or
	 * digits, and none of the plugin's own names inside it.
	 *
	 * @param string $prefix Candidate prefix.
	 * @return bool
	 */
	public static function is_valid_prefix( string $prefix ): bool {
		if ( 1 !== preg_match( '/^[a-z][a-z0-9]{0,7}$/', $prefix ) ) {
			return false;
		}
		foreach ( self::FOOTPRINT_WORDS as $word ) {
			if ( str_contains( $prefix, $word ) ) {
				return false;
			}
		}
		return true;
	}
}
