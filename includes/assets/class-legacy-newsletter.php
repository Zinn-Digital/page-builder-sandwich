<?php
/**
 * The signup endpoint behind legacy (5.x) newsletter forms.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Assets;

use ZinnDigital\PBS\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A 5.x premium newsletter form saved on a page (`[data-ce-tag="newsletter"]`) kept its service
 * settings — service, list and API key — in the post's `pbs_newsletter_settings` meta, keyed by
 * the form's id, and posted the visitor's address to a 5.x admin-ajax handler that no longer
 * exists. This is its replacement: one public REST route that only ever subscribes an address to
 * a list the post's OWN stored settings name.
 *
 * ⛔ NOT AN OPEN RELAY. The request carries an address, a post and a form id — never a service,
 * list, key or URL. It is refused unless: the per-post nonce printed on that page verifies; the
 * post is published, publicly viewable and not password-protected; its stored settings have that
 * form with an allow-listed service; the address is an email address; and the client has not
 * exceeded RATE_LIMIT signups in RATE_WINDOW for that post.
 *
 * The providers themselves (MailChimp, AWeber, MailPoet) are the premium layer's, as they were in
 * 5.x: it adds them through `pbsw_newsletter_providers`. Without it the route answers "not
 * available" — a refusal the visitor sees, never a signup silently dropped. The route lives here
 * rather than in the premium layer only so that it exists in both packages with one security
 * surface, which the access gate checks against one allow-list entry.
 */
final class Legacy_Newsletter {

	/** Where 5.x stored each form's settings (JSON: form id ⇒ data-* ⇒ value). */
	public const META = 'pbs_newsletter_settings';

	/** Signups per client per post, and the window they are counted over (seconds). */
	public const RATE_LIMIT  = 5;
	public const RATE_WINDOW = 600;

	/**
	 * Hook the route.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
	}

	/**
	 * The route's namespace: the neutral prefix, so the page that prints its URL names no product.
	 *
	 * @return string
	 */
	public static function rest_namespace(): string {
		return Settings::prefix() . '-l/v1';
	}

	/**
	 * The nonce action for a post's forms.
	 *
	 * @param int $post_id Post id.
	 * @return string
	 */
	public static function nonce_action( int $post_id ): string {
		return 'pbsw-newsletter-' . $post_id;
	}

	/**
	 * Register `POST /<prefix>-l/v1/newsletter`.
	 *
	 * @return void
	 */
	public static function routes(): void {
		register_rest_route(
			self::rest_namespace(),
			'/newsletter',
			array(
				'methods'             => 'POST',
				// Public by design (a visitor signing up); every refusal is in subscribe(), and the
				// route is allow-listed with its reason in wp/tests/wp-integration/access-allowlist.json.
				'permission_callback' => '__return_true',
				'callback'            => array( self::class, 'subscribe' ),
				'args'                => array(
					'post'  => array(
						'type'     => 'integer',
						'required' => true,
						'minimum'  => 1,
					),
					'form'  => array(
						'type'     => 'string',
						'required' => true,
						'pattern'  => '^[A-Za-z0-9_-]{1,64}$',
					),
					'email' => array(
						'type'      => 'string',
						'required'  => true,
						'maxLength' => 254,
					),
					'nonce' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * The route callback.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function subscribe( \WP_REST_Request $request ): \WP_REST_Response {
		$post_id = (int) $request->get_param( 'post' );
		$result  = self::handle(
			$post_id,
			(string) $request->get_param( 'form' ),
			(string) $request->get_param( 'email' ),
			(string) $request->get_param( 'nonce' ),
			self::client_key( $post_id )
		);

		return new \WP_REST_Response(
			array(
				'ok'      => $result['ok'],
				'message' => $result['message'],
			),
			$result['status']
		);
	}

	/**
	 * Every check, then the provider. Pure of the request object so tests can drive it.
	 *
	 * @param int    $post_id Post id.
	 * @param string $form    The form's id as the page printed it (neutral or 5.x).
	 * @param string $email   The visitor's address.
	 * @param string $nonce   The nonce the page printed.
	 * @param string $client  Rate-limit bucket for this client and post.
	 * @return array{ok: bool, status: int, message: string}
	 */
	public static function handle( int $post_id, string $form, string $email, string $nonce, string $client ): array {
		if ( ! wp_verify_nonce( $nonce, self::nonce_action( $post_id ) ) ) {
			return self::refuse( 403, __( 'Security error, please refresh the page and try again.', 'page-builder-sandwich' ) );
		}
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post || 'publish' !== $post->post_status || ! is_post_publicly_viewable( $post ) || post_password_required( $post ) ) {
			return self::refuse( 404, __( 'Missing newsletter settings. Please contact the admin.', 'page-builder-sandwich' ) );
		}
		$settings = self::form_settings( $post_id, $form );
		$service  = (string) ( $settings['data-service'] ?? '' );
		/**
		 * The newsletter services this site can subscribe with: service ⇒ callable( string $email,
		 * array $settings ): array{ok: bool, message: string}. The premium layer adds 5.x's three.
		 *
		 * @param array<string, callable> $providers Providers.
		 */
		$providers = (array) apply_filters( 'pbsw_newsletter_providers', array() );
		if ( array() === $settings || ! isset( $providers[ $service ] ) || ! is_callable( $providers[ $service ] ) ) {
			return self::refuse( 400, __( 'Invalid newsletter settings. Please contact the admin.', 'page-builder-sandwich' ) );
		}
		$email = trim( $email );
		if ( ! is_email( $email ) ) {
			return self::refuse( 400, __( 'Invalid email address.', 'page-builder-sandwich' ) );
		}
		if ( ! self::within_rate( $client ) ) {
			return self::refuse( 429, __( 'Too many attempts. Please try again in a few minutes.', 'page-builder-sandwich' ) );
		}

		$outcome = call_user_func( $providers[ $service ], $email, $settings );
		$ok      = is_array( $outcome ) && true === ( $outcome['ok'] ?? false );
		$message = is_array( $outcome ) && is_string( $outcome['message'] ?? null ) ? $outcome['message'] : '';
		if ( $ok ) {
			return array(
				'ok'      => true,
				'status'  => 200,
				'message' => $message,
			);
		}
		return self::refuse( 502, '' !== $message ? $message : __( 'Something went wrong. Please contact the admin.', 'page-builder-sandwich' ) );
	}

	/**
	 * A form's stored settings, found by the id the page printed: the render rewrite turned
	 * `pbsguid-X` into `<prefix>-l-guid-X` and `pbs-X` into `<prefix>-l-X`; the meta keeps 5.x's.
	 *
	 * @param int    $post_id Post id.
	 * @param string $form    Printed form id.
	 * @return array<string, string> Empty when the post has no such form.
	 */
	public static function form_settings( int $post_id, string $form ): array {
		$all = json_decode( (string) get_post_meta( $post_id, self::META, true ), true );
		if ( ! is_array( $all ) || '' === $form ) {
			return array();
		}
		$lead = Settings::prefix() . '-l-';
		$keys = array( $form );
		if ( str_starts_with( $form, $lead . 'guid-' ) ) {
			$keys[] = 'pbsguid-' . substr( $form, strlen( $lead . 'guid-' ) );
		} elseif ( str_starts_with( $form, $lead ) ) {
			$keys[] = 'pbs-' . substr( $form, strlen( $lead ) );
		}
		foreach ( $keys as $key ) {
			if ( isset( $all[ $key ] ) && is_array( $all[ $key ] ) ) {
				return array_map( 'strval', array_filter( $all[ $key ], 'is_scalar' ) );
			}
		}
		return array();
	}

	/**
	 * Count one attempt for this client; false once it is over the limit for the window.
	 *
	 * @param string $client Bucket.
	 * @return bool
	 */
	public static function within_rate( string $client ): bool {
		$key   = 'pbsw_nl_' . md5( $client );
		$count = (int) get_transient( $key );
		if ( $count >= self::RATE_LIMIT ) {
			return false;
		}
		set_transient( $key, $count + 1, self::RATE_WINDOW );
		return true;
	}

	/**
	 * The rate-limit bucket: the connecting address (never a forwarded header a client can set)
	 * and the post.
	 *
	 * @param int $post_id Post id.
	 * @return string
	 */
	private static function client_key( int $post_id ): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return $ip . '|' . $post_id;
	}

	/**
	 * A refusal.
	 *
	 * @param int    $status  HTTP status.
	 * @param string $message What the visitor is told.
	 * @return array{ok: bool, status: int, message: string}
	 */
	private static function refuse( int $status, string $message ): array {
		return array(
			'ok'      => false,
			'status'  => $status,
			'message' => $message,
		);
	}
}
