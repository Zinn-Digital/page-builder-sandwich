<?php
/**
 * One question every part of the plugin asks before offering a builder capability: may this user
 * use it? The free plugin answers yes for anyone WordPress already lets edit the content; the Pro
 * role manager (pbs-g2) narrows it per role through the `pbsw_permission` filter.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Workflow;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builder permissions.
 */
final class Permissions {

	/**
	 * The permissions the role manager controls, and what each covers.
	 *
	 * Keys:
	 *   studio       open pages in Sandwich Studio
	 *   design       change styles (the design panel, global classes, site design)
	 *   code         custom CSS and HTML/code blocks
	 *   templates    save, edit and delete templates, patterns and kits
	 *   ai           use the AI features inside the builder
	 *   content_only (a restriction, not a permission) edit only text and images: layout locked
	 */
	public const KEYS = array( 'studio', 'design', 'code', 'templates', 'ai' );

	/**
	 * May the user use this builder capability?
	 *
	 * @param string   $what    One of self::KEYS.
	 * @param int|null $user_id User (default: current).
	 * @return bool
	 */
	public static function can( string $what, ?int $user_id = null ): bool {
		$user_id = $user_id ?? get_current_user_id();

		/**
		 * Filters whether a user may use a builder capability. The free plugin allows every one;
		 * the Pro role manager answers per role. Administrators should always be allowed.
		 *
		 * @param bool   $allowed Whether it is allowed.
		 * @param string $what    The capability: studio, design, code, templates or ai.
		 * @param int    $user_id The user.
		 */
		return (bool) apply_filters( 'pbsw_permission', true, $what, $user_id );
	}

	/**
	 * Is the user limited to editing text and images (client mode)?
	 *
	 * @param int|null $user_id User (default: current).
	 * @return bool
	 */
	public static function content_only( ?int $user_id = null ): bool {
		$user_id = $user_id ?? get_current_user_id();

		/**
		 * Filters whether a user may only edit text and images, with the layout locked.
		 *
		 * @param bool $content_only Whether the user is in client mode.
		 * @param int  $user_id      The user.
		 */
		return (bool) apply_filters( 'pbsw_content_only', false, $user_id );
	}

	/**
	 * The current user's permissions as a map, for editor scripts (block editor setting
	 * `pbsPermissions`).
	 *
	 * @return array<string, bool>
	 */
	public static function map(): array {
		$map = array();
		foreach ( self::KEYS as $key ) {
			$map[ $key ] = self::can( $key );
		}
		$map['contentOnly'] = self::content_only();

		return $map;
	}
}
