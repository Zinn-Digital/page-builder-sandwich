<?php
/**
 * The "Blank canvas" page template.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A full-width page template with no theme header, footer or sidebar — the new plugin's
 * replacement for the legacy blank template, so a page built on it keeps its layout.
 *
 * ⭐ The template KEY is neutral (`blank-canvas`) because WordPress prints it into the page as a
 * body class (`page-template-blank-canvas`). The legacy key is a file path inside the old plugin
 * (`page_builder_sandwich/page-templates/template-blank.php`), which would print the plugin's
 * name into every such page — so it is MAPPED on read to the neutral key rather than registered.
 * The stored meta is left alone (the converter's backup/undo depends on it staying as it was);
 * the next save through the editor stores the neutral key, because that is what it reads.
 */
final class Templates {

	/** The neutral template key. */
	public const KEY = 'blank-canvas';

	/** The legacy 5.x template key still stored on pages built with it. */
	public const LEGACY_KEY = 'page_builder_sandwich/page-templates/template-blank.php';

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'theme_page_templates', array( self::class, 'add' ) );
		add_filter( 'get_post_metadata', array( self::class, 'map_legacy' ), 10, 4 );
		add_filter( 'template_include', array( self::class, 'template' ), 99 );
	}

	/**
	 * Offer the template in the page's template picker.
	 *
	 * @param array<string, string> $templates Templates (key => label).
	 * @return array<string, string>
	 */
	public static function add( $templates ): array {
		$templates              = is_array( $templates ) ? $templates : array();
		$templates[ self::KEY ] = __( 'Blank canvas', 'page-builder-sandwich' );

		return $templates;
	}

	/**
	 * Read the legacy blank-template key as the neutral one.
	 *
	 * @param mixed  $value    Short-circuit value (null to continue).
	 * @param int    $post_id  Post id.
	 * @param string $meta_key Meta key.
	 * @param bool   $single   Single value requested.
	 * @return mixed
	 */
	public static function map_legacy( $value, $post_id, $meta_key, $single ) {
		if ( '_wp_page_template' !== $meta_key || null !== $value ) {
			return $value;
		}
		if ( self::LEGACY_KEY !== self::stored( (int) $post_id ) ) {
			return $value;
		}

		return $single ? self::KEY : array( self::KEY );
	}

	/**
	 * Serve the blank canvas for a page that selected it.
	 *
	 * @param string $template Template file WordPress chose.
	 * @return string
	 */
	public static function template( $template ) {
		if ( ! is_singular() ) {
			return $template;
		}
		$id = get_queried_object_id();
		if ( $id <= 0 || self::KEY !== get_page_template_slug( $id ) ) {
			return $template;
		}
		// ⛔ Legacy served its blank template only when is_single() — never on a PAGE, whatever
		// the page had selected (class-blank-page-template.php). A page still carrying the legacy
		// key therefore always rendered in the theme, and an upgrade must not suddenly strip its
		// header and footer (measured: the fixture's built page lost both). Saving the page with
		// "Blank canvas" writes the new key, which is honoured everywhere.
		if ( self::LEGACY_KEY === self::stored( (int) $id ) && ! is_single() ) {
			return $template;
		}

		return PBSW_DIR . 'includes/core/templates/blank-canvas.php';
	}

	/**
	 * The template key as stored, without the legacy→neutral mapping.
	 *
	 * @param int $post_id Post id.
	 * @return string
	 */
	public static function stored( int $post_id ): string {
		$had = remove_filter( 'get_post_metadata', array( self::class, 'map_legacy' ), 10 );
		$raw = (string) get_post_meta( $post_id, '_wp_page_template', true );
		if ( $had ) {
			add_filter( 'get_post_metadata', array( self::class, 'map_legacy' ), 10, 4 );
		}

		return $raw;
	}
}
