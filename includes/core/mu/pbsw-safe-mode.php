<?php
/**
 * Plugin Name: Page Builder Sandwich — Studio safe mode
 * Description: Lets an administrator open Sandwich Studio with every other plugin switched off, for their own Studio requests only. Installed and removed by Page Builder Sandwich; does nothing unless it is on for you.
 * Version: 1
 * Author: Neil Lock — CEO, Zinn Digital® Ltd
 * License: GPL-2.0-or-later
 *
 * ⛔ This file is COPIED into wp-content/mu-plugins/ by Page Builder Sandwich (Core\Safe_Mode) and
 * removed again when the plugin is deactivated. It must not use anything from the plugin: it runs
 * before any plugin loads, and it must stay harmless if the plugin's files are ever deleted.
 *
 * It narrows the active plugin list to Page Builder Sandwich alone ONLY when all of these hold:
 *   1. the request carries the `pbsw_safe_mode` cookie, signed (HMAC-SHA256) with the site's own
 *      safe-mode key AND bound to the browser's current logged-in session cookie;
 *   2. the cookie has not expired;
 *   3. the request is the Studio screen (wp-admin/admin.php?page=pbs-studio) or a REST request
 *      sent BY Studio (it carries the `X-PBSW-Studio: 1` header).
 * Everything else — the live site, the normal editor, other users — loads every plugin as usual.
 * Page Builder Sandwich re-checks the user once WordPress knows who they are (Core\Safe_Mode::verify).
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

( static function (): void {
	$applies = null;

	$check = static function () use ( &$applies ): bool {
		if ( null !== $applies ) {
			return $applies;
		}
		$applies = false;

		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Recommended -- compared against an HMAC, never stored or printed.
		$cookie = isset( $_COOKIE['pbsw_safe_mode'] ) && is_string( $_COOKIE['pbsw_safe_mode'] ) ? wp_unslash( $_COOKIE['pbsw_safe_mode'] ) : '';
		if ( '' === $cookie || ! defined( 'LOGGED_IN_COOKIE' ) ) {
			return false;
		}
		$conf = get_option( 'pbsw_safe_mode' );
		if ( ! is_array( $conf ) || empty( $conf['key'] ) || ! is_string( $conf['key'] ) || empty( $conf['basename'] ) ) {
			return false;
		}
		$parts = explode( '|', $cookie );
		if ( 3 !== count( $parts ) || ! ctype_digit( $parts[0] ) || ! ctype_digit( $parts[1] ) || (int) $parts[1] < time() ) {
			return false;
		}
		$session = isset( $_COOKIE[ LOGGED_IN_COOKIE ] ) && is_string( $_COOKIE[ LOGGED_IN_COOKIE ] ) ? wp_unslash( $_COOKIE[ LOGGED_IN_COOKIE ] ) : '';
		if ( '' === $session ) {
			return false;
		}
		$want = hash_hmac( 'sha256', $parts[0] . '|' . $parts[1] . '|' . hash( 'sha256', $session ), $conf['key'] );
		if ( ! hash_equals( $want, $parts[2] ) ) {
			return false;
		}

		$script = isset( $_SERVER['SCRIPT_NAME'] ) ? basename( sanitize_text_field( wp_unslash( (string) $_SERVER['SCRIPT_NAME'] ) ) ) : '';
		$page   = isset( $_GET['page'] ) && is_string( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$studio = defined( 'WP_ADMIN' ) && WP_ADMIN && 'admin.php' === $script && 'pbs-studio' === $page;

		$header = isset( $_SERVER['HTTP_X_PBSW_STUDIO'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_X_PBSW_STUDIO'] ) ) : '';
		$uri    = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) ) : '';
		$prefix = '/' . trim( function_exists( 'rest_get_url_prefix' ) ? rest_get_url_prefix() : 'wp-json', '/' ) . '/';
		$rest   = '1' === $header && ( false !== strpos( $uri, $prefix ) || isset( $_GET['rest_route'] ) );
		// phpcs:enable

		if ( ! $studio && ! $rest ) {
			return false;
		}
		$applies = true;
		if ( ! defined( 'PBSW_SAFE_MODE_USER' ) ) {
			define( 'PBSW_SAFE_MODE_USER', (int) $parts[0] );
		}
		return true;
	};

	$keep = static function () {
		$conf = get_option( 'pbsw_safe_mode' );
		return is_array( $conf ) && ! empty( $conf['basename'] ) ? (string) $conf['basename'] : '';
	};

	add_filter(
		'option_active_plugins',
		static function ( $plugins ) use ( $check, $keep ) {
			if ( ! is_array( $plugins ) ) {
				return $plugins;
			}
			$base = $keep();
			if ( '' === $base ) {
				return $plugins;
			}
			$network = is_multisite() ? (array) get_site_option( 'active_sitewide_plugins', array() ) : array();
			// Our plugin is not active here: this file has nothing to do.
			if ( ( ! in_array( $base, $plugins, true ) && ! isset( $network[ $base ] ) ) || ! $check() ) {
				return $plugins;
			}
			if ( ! defined( 'PBSW_SAFE_MODE_DISABLED' ) ) {
				define( 'PBSW_SAFE_MODE_DISABLED', wp_json_encode( array_values( array_diff( $plugins, array( $base ) ) ) ) );
			}
			return in_array( $base, $plugins, true ) ? array( $base ) : array();
		},
		PHP_INT_MAX
	);

	add_filter(
		'site_option_active_sitewide_plugins',
		static function ( $plugins ) use ( $check, $keep ) {
			if ( ! is_array( $plugins ) ) {
				return $plugins;
			}
			$base = $keep();
			if ( '' === $base || ! $check() ) {
				return $plugins;
			}
			return isset( $plugins[ $base ] ) ? array( $base => $plugins[ $base ] ) : array();
		},
		PHP_INT_MAX
	);
} )();
