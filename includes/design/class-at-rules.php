<?php
/**
 * At-rule presets for the style builder (`@media`, `@container`, `@supports`).
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Design;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The conditions the Style tab offers next to the screen sizes: "only when printing", "only in
 * a dark colour scheme", "only inside a narrow card"… A preset is
 * `{id, label, type: media|container|supports, query}`; a block stores the values set for it
 * under `pbs.s['@<id>']`, and the compiler writes them as `@<type> <query>{…}` after the
 * breakpoint rules, so a condition wins over the screen-size values it overlaps.
 *
 * Developers define the list with the `pbsw_style_at_rules` filter (docs/plugins-overhaul/
 * pbs-developer-docs: "At-rule presets"). Every preset is validated here: a query is plain
 * condition text — no braces, semicolons, quotes, backslashes, `<`, `>` or `@` — so a preset can
 * never close the rule it is printed in. An invalid preset is dropped (and named in the
 * `pbsw_style_at_rules_invalid` action) instead of breaking the page.
 */
final class At_Rules {

	/** The rule types a preset may use. */
	public const TYPES = array( 'media', 'container', 'supports' );

	/** Most presets the style builder lists. */
	public const MAX = 40;

	/** A preset id (the `pbs.s` key is `@` + id). */
	public const ID_PATTERN = '/^[a-z][a-z0-9-]{1,23}$/';

	/** Longest query. */
	private const MAX_QUERY = 200;

	/**
	 * The built-in presets.
	 *
	 * @return array<int, array{id: string, label: string, type: string, query: string}>
	 */
	public static function defaults(): array {
		return array(
			array(
				'id'    => 'print',
				'label' => __( 'When printing', 'page-builder-sandwich' ),
				'type'  => 'media',
				'query' => 'print',
			),
			array(
				'id'    => 'dark',
				'label' => __( 'Dark colour scheme', 'page-builder-sandwich' ),
				'type'  => 'media',
				'query' => '(prefers-color-scheme:dark)',
			),
			array(
				'id'    => 'reduced-motion',
				'label' => __( 'Reduced motion', 'page-builder-sandwich' ),
				'type'  => 'media',
				'query' => '(prefers-reduced-motion:reduce)',
			),
			array(
				'id'    => 'can-hover',
				'label' => __( 'Mouse or trackpad', 'page-builder-sandwich' ),
				'type'  => 'media',
				'query' => '(hover:hover)',
			),
		);
	}

	/**
	 * The presets in effect: the defaults through the `pbsw_style_at_rules` filter, validated.
	 *
	 * @return array<int, array{id: string, label: string, type: string, query: string}>
	 */
	public static function all(): array {
		/**
		 * Filters the at-rule presets the style builder offers under Style › Condition: a list of
		 * `array( 'id' => 'narrow-card', 'label' => 'Narrow card', 'type' => 'container', 'query' =>
		 * 'card (max-width:400px)' )`, where `type` is `media`, `container` or `supports`. Values set
		 * under a condition print as `@<type> <query>{…}` after the screen-size rules. The built-in
		 * presets are print, dark colour scheme, reduced motion and mouse or trackpad; an invalid
		 * preset is dropped and named in the `pbsw_style_at_rules_invalid` action.
		 *
		 * Each preset is an array:
		 * - `id`    2–24 characters: a lowercase letter, then lowercase letters, digits, hyphens.
		 *           Stored in the block (`pbs.s['@<id>']`), so keep it stable once used.
		 * - `label` What the editor shows.
		 * - `type`  `media`, `container` or `supports`.
		 * - `query` The condition, e.g. `(min-width:600px)`, `card (max-width:400px)`,
		 *           `(display:grid)`, `print`.
		 *
		 * Output order follows the list order: a later preset wins over an earlier one.
		 *
		 * @since 6.36.24
		 *
		 * @param array<int, array<string, string>> $presets The built-in presets.
		 */
		$presets = apply_filters( 'pbsw_style_at_rules', self::defaults() );

		$clean = self::clean( is_array( $presets ) ? $presets : array() );
		if ( array() !== $clean['invalid'] ) {
			/**
			 * Fires when presets from `pbsw_style_at_rules` were dropped as invalid.
			 *
			 * @since 6.36.24
			 *
			 * @param array<int, string> $invalid The dropped presets' ids (or their positions).
			 */
			do_action( 'pbsw_style_at_rules_invalid', $clean['invalid'] );
		}

		return $clean['presets'];
	}

	/**
	 * A short hash of the presets (part of the page and site sheet signatures, so a changed
	 * filter rebuilds the CSS).
	 *
	 * @return string
	 */
	public static function hash(): string {
		return substr( md5( (string) wp_json_encode( self::all() ) ), 0, 12 );
	}

	// ── The pure half ────────────────────────────────────────────────────────────────────────

	/**
	 * Validate a preset list. Pure.
	 *
	 * @param array<int|string, mixed> $presets Presets.
	 * @return array{presets: array<int, array{id: string, label: string, type: string, query: string}>, invalid: array<int, string>}
	 */
	public static function clean( array $presets ): array {
		$out     = array();
		$invalid = array();
		$seen    = array();
		foreach ( array_values( $presets ) as $i => $preset ) {
			$id = is_array( $preset ) && is_string( $preset['id'] ?? null ) ? $preset['id'] : '';
			if ( ! is_array( $preset ) || 1 !== preg_match( self::ID_PATTERN, $id ) || isset( $seen[ $id ] ) ) {
				$invalid[] = '' === $id ? '#' . ( $i + 1 ) : $id;
				continue;
			}
			$type  = is_string( $preset['type'] ?? null ) ? $preset['type'] : '';
			$query = self::query( is_string( $preset['query'] ?? null ) ? $preset['query'] : '' );
			if ( ! in_array( $type, self::TYPES, true ) || null === $query || count( $out ) >= self::MAX ) {
				$invalid[] = $id;
				continue;
			}
			$label       = trim( wp_strip_all_tags( is_string( $preset['label'] ?? null ) ? $preset['label'] : '' ) );
			$seen[ $id ] = true;
			$out[]       = array(
				'id'    => $id,
				'label' => '' === $label ? $id : mb_substr( $label, 0, 60 ),
				'type'  => $type,
				'query' => $query,
			);
		}

		return array(
			'presets' => $out,
			'invalid' => $invalid,
		);
	}

	/**
	 * A safe condition: printable condition text with balanced parentheses, whitespace
	 * collapsed. Null when it could escape its rule or is empty.
	 *
	 * @param string $query Query.
	 * @return string|null
	 */
	public static function query( string $query ): ?string {
		$query = trim( (string) preg_replace( '/\s+/', ' ', $query ) );
		if ( '' === $query || strlen( $query ) > self::MAX_QUERY || 1 !== preg_match( '/^[a-zA-Z0-9 ():,.%_\/-]+$/', $query ) ) {
			return null;
		}
		$depth = 0;
		foreach ( str_split( $query ) as $ch ) {
			if ( '(' === $ch ) {
				++$depth;
			} elseif ( ')' === $ch && --$depth < 0 ) {
				return null;
			}
		}

		return 0 === $depth ? $query : null;
	}

	/**
	 * The style keys of these presets (`@<id>`), in output order.
	 *
	 * @param array<int, array{id: string}> $presets Presets.
	 * @return array<int, string>
	 */
	public static function keys( array $presets ): array {
		return array_map( static fn( array $p ): string => '@' . $p['id'], $presets );
	}

	/**
	 * The at-rule prelude for a preset: `@media print`, `@container card (max-width:400px)`.
	 *
	 * @param array{type: string, query: string} $preset Preset.
	 * @return string
	 */
	public static function prelude( array $preset ): string {
		return '@' . $preset['type'] . ' ' . $preset['query'];
	}
}
