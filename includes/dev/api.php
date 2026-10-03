<?php
/**
 * The public PHP API for add-ons (P16, pbs-dev1). Call these on `init` (or later), from your own
 * plugin, after checking `function_exists( 'pbsw_register_block' )`.
 *
 * Reference: https://zinndigital.com/wordpress-plugins/page-builder-sandwich/mcp-api (generated
 * from these docblocks) and docs/plugins-overhaul/pbs-developer.md. A worked example add-on:
 * https://github.com/Zinn-Digital/pbs-starter-addon.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

use ZinnDigital\PBS\Dev\Registry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'pbsw_register_block' ) ) {
	/**
	 * Register a block that works like Page Builder Sandwich's own: in the builder's block library
	 * (category "Page Builder Sandwich" unless block.json names another), with the Style panel's
	 * design controls (every block gets the `pbs` design attribute), and listed as an extension.
	 * Put the block's own CSS in block.json `style`: WordPress then loads it only on pages that use
	 * the block.
	 *
	 * @param string               $path_or_name The folder holding block.json (or its file), or a
	 *                                           block name for a block defined in PHP.
	 * @param array<string, mixed> $args         Args for register_block_type(); plus `plugin` (your
	 *                                           plugin's name, shown in the extensions list).
	 * @return \WP_Block_Type|false The block type, or false (the reason goes to _doing_it_wrong).
	 */
	function pbsw_register_block( string $path_or_name, array $args = array() ) {
		$plugin = (string) ( $args['plugin'] ?? '' );
		unset( $args['plugin'] );
		$file = str_ends_with( $path_or_name, 'block.json' ) ? $path_or_name : trailingslashit( $path_or_name ) . 'block.json';
		$name = $path_or_name;
		if ( is_readable( $file ) ) {
			$meta = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- the add-on's own block.json.
			$name = is_array( $meta ) ? (string) ( $meta['name'] ?? '' ) : '';
			if ( is_array( $meta ) && empty( $meta['category'] ) && empty( $args['category'] ) ) {
				$args['category'] = 'pbs-content';
			}
		} elseif ( empty( $args['category'] ) ) {
			$args['category'] = 'pbs-content';
		}
		if ( ! Registry::add(
			'blocks',
			$name,
			array(
				'label'  => (string) ( $args['title'] ?? $name ),
				'plugin' => $plugin,
			),
			array()
		) ) {
			return false;
		}
		$type = register_block_type( is_readable( $file ) ? dirname( $file ) : $path_or_name, $args );

		return $type instanceof \WP_Block_Type ? $type : false;
	}
}

if ( ! function_exists( 'pbsw_register_dynamic_source' ) ) {
	/**
	 * Register a dynamic data source: a value a heading, paragraph, button, image or any of our
	 * blocks can show instead of fixed text, chosen in the editor's Dynamic content panel (Pro).
	 *
	 * @param string               $name Source name, `vendor/name`.
	 * @param array<string, mixed> $args `label` (string, required); `callback` (required):
	 *                                   fn( array $args, array $context ): ?string, where $args holds
	 *                                   the binding's `field` and $context the item (`postId`,
	 *                                   `postType`, the query loop's current card); return null for
	 *                                   "no value"; `fields` (optional): field => label, offered in
	 *                                   the panel; `plugin` (optional): your plugin's name.
	 * @return bool Registered.
	 */
	function pbsw_register_dynamic_source( string $name, array $args ): bool {
		return Registry::add( 'sources', $name, $args, array( 'label', 'callback' ) );
	}
}

if ( ! function_exists( 'pbsw_register_condition' ) ) {
	/**
	 * Register a display condition, offered wherever conditions are chosen: a theme-builder
	 * template's "Show on" rules and a block's display conditions (Pro).
	 *
	 * @param string               $name Condition name, `vendor/name`.
	 * @param array<string, mixed> $args `label` (required); `callback` (required): fn( array $rule ):
	 *                                   bool, where $rule['value'] is what the author typed into the
	 *                                   condition's text field; `input` (optional, default true):
	 *                                   whether the condition has that text field; `cacheable`
	 *                                   (optional, default false): true only when the answer is the
	 *                                   same for every visitor of a URL (otherwise the page is not
	 *                                   page-cached); `plugin` (optional).
	 * @return bool Registered.
	 */
	function pbsw_register_condition( string $name, array $args ): bool {
		return Registry::add(
			'conditions',
			$name,
			$args + array(
				'input'     => true,
				'cacheable' => false,
			),
			array( 'label', 'callback' )
		);
	}
}

if ( ! function_exists( 'pbsw_register_form_action' ) ) {
	/**
	 * Register a form action: something a form built with the form builder can do when a visitor
	 * sends it (beside email, save, webhook and mailing list), switched on per form (Pro).
	 *
	 * @param string               $name Action name, `vendor/name`.
	 * @param array<string, mixed> $args `label` (required); `callback` (required):
	 *                                   fn( array $submission, array $settings ): true|WP_Error, where
	 *                                   $submission holds `rows` (label/value pairs in field order),
	 *                                   `values`, `fields`, `post` and `form`, and $settings the
	 *                                   action's settings in that form (`value`: what the author
	 *                                   typed in the action's text field); `input` (optional
	 *                                   string): the label of that text field, '' for none; `plugin`.
	 * @return bool Registered.
	 */
	function pbsw_register_form_action( string $name, array $args ): bool {
		return Registry::add( 'actions', $name, $args + array( 'input' => '' ), array( 'label', 'callback' ) );
	}
}

if ( ! function_exists( 'pbsw_register_style_control' ) ) {
	/**
	 * Register a style control: one more setting in the Style panel of every block (or of the
	 * blocks you name), stored per breakpoint and compiled to CSS by the builder like its own
	 * controls. No JavaScript is needed: the editor builds the control from this definition.
	 *
	 * @param string               $name Control name, `vendor/name`.
	 * @param array<string, mixed> $args `label` (required); `kind`: `choice` (default), `length` or
	 *                                   `color`; `property` (required): the CSS property, with
	 *                                   logical sides (`margin-inline-start`, never `margin-left`);
	 *                                   `choices` (choice): CSS keywords; `options` (choice):
	 *                                   keyword => label; `group`: the panel section (layout,
	 *                                   spacing, size, typography, colour, border, shadow, position,
	 *                                   effects, advanced; default advanced); `blocks`: block names
	 *                                   (default every block); `plugin` (optional).
	 * @return bool Registered.
	 */
	function pbsw_register_style_control( string $name, array $args ): bool {
		$def = \ZinnDigital\PBS\Dev\Style_Control::definition( $name, $args );
		if ( is_wp_error( $def ) ) {
			_doing_it_wrong( __FUNCTION__, esc_html( $name . ': ' . $def->get_error_message() ), '6.32.0' );
			return false;
		}

		return Registry::add(
			'controls',
			$name,
			$def + array(
				'label'  => (string) ( $args['label'] ?? '' ),
				'plugin' => (string) ( $args['plugin'] ?? '' ),
			),
			array( 'label' )
		);
	}
}

if ( ! function_exists( 'pbsw_get_extensions' ) ) {
	/**
	 * Everything add-ons have registered: blocks, style controls, sources, conditions and actions (name, label and
	 * plugin of each).
	 *
	 * @return array<string, array<int, array<string, string>>>
	 */
	function pbsw_get_extensions(): array {
		return Registry::all();
	}
}
