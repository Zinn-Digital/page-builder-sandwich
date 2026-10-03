<?php
/**
 * A style control an add-on registered (P16, pbs-dev1): a design prop built from a definition.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Dev;

use ZinnDigital\PBS\Design\Prop;
use ZinnDigital\PBS\Design\Values;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One CSS property, of one of three kinds the design engine already validates: `choice` (one of a
 * list), `length` (a number and unit, or a design token) and `color`. The value is stored per
 * breakpoint like every built-in control and compiled by the same compiler, so it works in the
 * editor, on the front end and in every breakpoint with no CSS of the add-on's own.
 */
final class Style_Control extends Prop {

	/** The kinds an add-on may use. */
	public const KINDS = array( 'choice', 'length', 'color' );

	/** The panel sections. */
	public const GROUPS = array( 'layout', 'spacing', 'size', 'typography', 'colour', 'border', 'shadow', 'position', 'effects', 'advanced' );

	/**
	 * Definition.
	 *
	 * @var array<string, mixed>
	 */
	private array $def;

	/**
	 * Build from a validated definition.
	 *
	 * @param array<string, mixed> $def `key`, `kind`, `property`, `group`, `choices`, `blocks`.
	 */
	public function __construct( array $def ) {
		$this->def = $def;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function key(): string {
		return (string) $this->def['key'];
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function group(): string {
		return (string) $this->def['group'];
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $block_name Block name.
	 * @return bool
	 */
	public function applies_to( string $block_name ): bool {
		$blocks = (array) ( $this->def['blocks'] ?? array() );

		return array() === $blocks || in_array( $block_name, $blocks, true );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param mixed $value Untrusted value.
	 * @return mixed
	 */
	public function sanitize( $value ) {
		switch ( $this->def['kind'] ) {
			case 'choice':
				return Values::choice( $value, (array) $this->def['choices'] );
			case 'length':
				return Values::length( $value );
			case 'color':
				return Values::color( $value );
		}

		return null;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param mixed  $value  Sanitised value.
	 * @param string $prefix Class prefix.
	 * @return array<string, string>
	 */
	public function to_css( $value, string $prefix ): array {
		$css = 'choice' === $this->def['kind'] ? (string) $value : Values::css( $value, $prefix );

		return '' === $css ? array() : array( (string) $this->def['property'] => $css );
	}

	/**
	 * Validate an add-on's definition.
	 *
	 * @param string               $name Registered name (vendor/name).
	 * @param array<string, mixed> $args Arguments.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function definition( string $name, array $args ) {
		$kind     = (string) ( $args['kind'] ?? 'choice' );
		$property = strtolower( trim( (string) ( $args['property'] ?? '' ) ) );
		if ( ! in_array( $kind, self::KINDS, true ) ) {
			return new \WP_Error( 'pbsw_dev_kind', 'kind must be choice, length or color' );
		}
		// A real CSS property name, and a logical one where a physical one exists (RTL).
		if ( 1 !== preg_match( '/^-?[a-z][a-z-]*$/', $property ) || 1 === preg_match( '/(^|-)(left|right|top|bottom)(-|$)/', $property ) ) {
			return new \WP_Error( 'pbsw_dev_property', 'property must be a CSS property, written with logical sides (inline-start, block-end), never left/right/top/bottom' );
		}
		$choices = array_values( array_filter( array_map( 'strval', (array) ( $args['choices'] ?? array() ) ), static fn( string $c ): bool => 1 === preg_match( '/^[a-z0-9-]+$/', $c ) ) );
		if ( 'choice' === $kind && array() === $choices ) {
			return new \WP_Error( 'pbsw_dev_choices', 'a choice control needs choices (CSS keywords)' );
		}
		$group = in_array( $args['group'] ?? '', self::GROUPS, true ) ? (string) $args['group'] : 'advanced';

		return array(
			'key'      => 'x-' . str_replace( '/', '-', $name ),
			'kind'     => $kind,
			'property' => $property,
			'group'    => $group,
			'choices'  => $choices,
			'options'  => array_map( 'strval', (array) ( $args['options'] ?? array() ) ),
			'blocks'   => array_values( array_map( 'strval', (array) ( $args['blocks'] ?? array() ) ) ),
		);
	}
}
