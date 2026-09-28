<?php
/**
 * Google Fonts hosted on the site itself (pbs-p6).
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Assets\Perf;

use ZinnDigital\PBS\Rest;
use ZinnDigital\PBS\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Downloads a Google Fonts family ONCE, in wp-admin, into `uploads/<prefix>-assets/fonts/`, and
 * serves it from there: the `@font-face` rules point at the local `.woff2` files and carry
 * `font-display: swap` (text shows at once in a fallback face, then swaps without a layout jump).
 *
 * ⛔ No request to Google ever happens on the front end — the download is triggered by an
 * administrator (REST `POST pbs/v1/fonts`, or `wp pbsw fonts download`), and a page view only
 * ever enqueues the local stylesheet. That is the GDPR point (a visitor's IP never reaches Google)
 * as well as the speed one (no third-party connection before first paint).
 *
 * ⛔ Only `fonts.googleapis.com` is asked for CSS and only `fonts.gstatic.com` for font files; a
 * font file is accepted only if it is a real WOFF2 (magic bytes) under 2 MB.
 */
final class Fonts {

	/** Option: family → local stylesheet. */
	public const OPTION = 'pbsw_local_fonts';

	/** The CSS2 API. */
	private const API = 'https://fonts.googleapis.com/css2';

	/** Where font files may come from. */
	private const FILE_HOST = 'fonts.gstatic.com';

	/** Largest accepted font file. */
	private const MAX_BYTES = 2097152;

	/** A current browser's user agent: the API answers WOFF2 only to browsers that support it. */
	private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36';

	/**
	 * Hooks: the REST routes and the WP-CLI command.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
		if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( '\WP_CLI' ) ) {
			\WP_CLI::add_command( 'pbsw fonts', Fonts_Command::class );
		}
	}

	/**
	 * `pbs/v1/fonts`: list (GET), download (POST), remove (DELETE). Administrators only.
	 *
	 * @return void
	 */
	public static function register_routes(): void {
		$family = array(
			'type'     => 'string',
			'required' => true,
		);
		register_rest_route(
			Rest::NAMESPACE,
			'/fonts',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => static fn(): \WP_REST_Response => new \WP_REST_Response( self::stored() ),
					'permission_callback' => array( Rest::class, 'can_manage' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( self::class, 'rest_download' ),
					'permission_callback' => array( Rest::class, 'can_manage' ),
					'args'                => array(
						'family'  => $family,
						'weights' => array(
							'type'    => 'array',
							'items'   => array( 'type' => 'integer' ),
							'default' => array( 400, 700 ),
						),
						'italic'  => array(
							'type'    => 'boolean',
							'default' => false,
						),
					),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => static fn( \WP_REST_Request $r ): \WP_REST_Response => new \WP_REST_Response( array( 'removed' => self::remove( (string) $r->get_param( 'family' ) ) ) ),
					'permission_callback' => array( Rest::class, 'can_manage' ),
					'args'                => array( 'family' => $family ),
				),
			)
		);
	}

	/**
	 * REST POST handler.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function rest_download( \WP_REST_Request $request ) {
		$result = self::download(
			(string) $request->get_param( 'family' ),
			array_map( 'intval', (array) $request->get_param( 'weights' ) ),
			(bool) $request->get_param( 'italic' )
		);

		return is_wp_error( $result ) ? $result : new \WP_REST_Response( $result, 201 );
	}

	/**
	 * Download a family and store it locally.
	 *
	 * @param string          $family  Family name, e.g. `Open Sans`.
	 * @param array<int, int> $weights Weights (100–900).
	 * @param bool            $italic  Also the italic faces.
	 * @return array<string, mixed>|\WP_Error The stored entry.
	 */
	public static function download( string $family, array $weights = array( 400, 700 ), bool $italic = false ) {
		$family = trim( $family );
		if ( 1 !== preg_match( '/^[A-Za-z0-9][A-Za-z0-9 ]{0,59}$/', $family ) ) {
			return new \WP_Error( 'pbsw_font_family', __( 'That is not a valid font family name.', 'page-builder-sandwich' ), array( 'status' => 400 ) );
		}
		$weights = array_values( array_unique( array_filter( $weights, static fn( int $w ): bool => $w >= 100 && $w <= 900 && 0 === $w % 100 ) ) );
		sort( $weights );
		if ( array() === $weights ) {
			$weights = array( 400 );
		}

		$response = wp_safe_remote_get(
			self::css_url( $family, $weights, $italic ),
			array(
				'timeout'    => 20,
				'user-agent' => self::USER_AGENT,
			)
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return new \WP_Error( 'pbsw_font_fetch', __( 'Google Fonts did not return that family. Check the name and try again.', 'page-builder-sandwich' ), array( 'status' => 502 ) );
		}

		$files = array();
		$css   = self::localise(
			(string) wp_remote_retrieve_body( $response ),
			static function ( string $url ) use ( &$files ): ?string {
				$local = self::fetch_font_file( $url );
				if ( null !== $local ) {
					$files[] = $local['path'];
				}
				return null === $local ? null : $local['url'];
			}
		);
		if ( null === $css || array() === $files ) {
			return new \WP_Error( 'pbsw_font_files', __( 'The font files could not be downloaded or saved. Check that the uploads folder is writable.', 'page-builder-sandwich' ), array( 'status' => 500 ) );
		}
		$sheet = Files::write( $css, 'css', 'fonts' );
		if ( null === $sheet ) {
			return new \WP_Error( 'pbsw_font_save', __( 'The font stylesheet could not be saved. Check that the uploads folder is writable.', 'page-builder-sandwich' ), array( 'status' => 500 ) );
		}

		$all            = self::stored();
		$previous       = $all[ $family ] ?? null;
		$entry          = array(
			'family'  => $family,
			'weights' => $weights,
			'italic'  => $italic,
			'url'     => $sheet['url'],
			'path'    => $sheet['path'],
			'hash'    => $sheet['hash'],
			'files'   => $files,
			'updated' => time(),
		);
		$all[ $family ] = $entry;
		update_option( self::OPTION, $all, false );
		if ( is_array( $previous ) ) {
			self::delete_files( $previous, $entry );
		}

		return $entry;
	}

	/**
	 * The CSS2 API URL for a family.
	 *
	 * @param string          $family  Family.
	 * @param array<int, int> $weights Weights.
	 * @param bool            $italic  Italic too.
	 * @return string
	 */
	public static function css_url( string $family, array $weights, bool $italic ): string {
		$axes = array();
		foreach ( $italic ? array( 0, 1 ) : array( 0 ) as $ital ) {
			foreach ( $weights as $weight ) {
				$axes[] = $ital . ',' . $weight;
			}
		}

		return self::API . '?family=' . str_replace( ' ', '+', $family ) . ':ital,wght@' . implode( ';', $axes ) . '&display=swap';
	}

	/**
	 * The pure half: rewrite a CSS2 API stylesheet so every `@font-face` points at local files
	 * and swaps. Faces whose file could not be stored are dropped.
	 *
	 * @param string   $css   The API's stylesheet.
	 * @param callable $store `fn( string $remote_url ): ?string` → the local URL, or null.
	 * @return string|null Null when no face survived.
	 */
	public static function localise( string $css, callable $store ): ?string {
		if ( ! preg_match_all( '/@font-face\s*\{[^}]*\}/i', $css, $m ) ) {
			return null;
		}
		$out = array();
		foreach ( $m[0] as $face ) {
			if ( ! preg_match( '/url\(\s*[\'"]?(https:\/\/[^\'")\s]+)[\'"]?\s*\)/i', $face, $u ) ) {
				continue;
			}
			$host = (string) wp_parse_url( $u[1], PHP_URL_HOST );
			if ( self::FILE_HOST !== strtolower( $host ) ) {
				continue;
			}
			$local = $store( $u[1] );
			if ( null === $local ) {
				continue;
			}
			$face  = (string) preg_replace( '/src\s*:[^;}]*/i', "src:url('" . esc_url_raw( $local ) . "') format('woff2')", $face, 1 );
			$face  = preg_match( '/font-display\s*:/i', $face )
				? (string) preg_replace( '/font-display\s*:[^;}]*/i', 'font-display:swap', $face )
				: (string) preg_replace( '/\}\s*$/', ';font-display:swap}', $face );
			$out[] = Page_Css::minify( $face );
		}

		return array() === $out ? null : implode( '', $out );
	}

	/**
	 * Enqueue a stored family's local stylesheet on the current page.
	 *
	 * @param string $family Family.
	 * @return bool Whether it was stored and enqueued.
	 */
	public static function enqueue( string $family ): bool {
		$entry = self::stored()[ $family ] ?? null;
		if ( ! is_array( $entry ) || ! is_string( $entry['url'] ?? null ) ) {
			return false;
		}
		wp_enqueue_style( Settings::prefix() . '-f' . substr( (string) $entry['hash'], 0, 8 ), Files::current_url( (string) $entry['url'] ), array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- the file name is its content hash.

		return true;
	}

	/**
	 * Stored families.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function stored(): array {
		$all = get_option( self::OPTION, array() );

		return is_array( $all ) ? $all : array();
	}

	/**
	 * Remove a stored family and its files.
	 *
	 * @param string $family Family.
	 * @return bool Whether it was stored.
	 */
	public static function remove( string $family ): bool {
		$all = self::stored();
		if ( ! isset( $all[ $family ] ) ) {
			return false;
		}
		$entry = $all[ $family ];
		unset( $all[ $family ] );
		update_option( self::OPTION, $all, false );
		self::delete_files( $entry, array() );

		return true;
	}

	/**
	 * Download one font file (fonts.gstatic.com, real WOFF2, size-capped) into the fonts folder.
	 *
	 * @param string $url Remote URL.
	 * @return array{url: string, path: string, hash: string}|null
	 */
	private static function fetch_font_file( string $url ): ?array {
		if ( self::FILE_HOST !== strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ) ) {
			return null;
		}
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'             => 20,
				'user-agent'          => self::USER_AGENT,
				'limit_response_size' => self::MAX_BYTES + 1,
			)
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}
		$body = (string) wp_remote_retrieve_body( $response );
		if ( strlen( $body ) > self::MAX_BYTES || ! str_starts_with( $body, 'wOF2' ) ) {
			return null;
		}

		return Files::write( $body, 'woff2', 'fonts' );
	}

	/**
	 * Delete an entry's files that the replacement does not also use.
	 *
	 * @param array<string, mixed> $old  Old entry.
	 * @param array<string, mixed> $keep New entry (its files are kept).
	 * @return void
	 */
	private static function delete_files( array $old, array $keep ): void {
		$kept = array_merge( (array) ( $keep['files'] ?? array() ), array( (string) ( $keep['path'] ?? '' ) ) );
		foreach ( array_merge( (array) ( $old['files'] ?? array() ), array( (string) ( $old['path'] ?? '' ) ) ) as $path ) {
			if ( is_string( $path ) && '' !== $path && ! in_array( $path, $kept, true ) ) {
				Files::delete( $path );
			}
		}
	}
}
