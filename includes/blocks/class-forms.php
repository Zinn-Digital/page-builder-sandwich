<?php
/**
 * Form plugins the Form block can embed and style (pbs-m6): Contact Form 7, WPForms, Gravity
 * Forms, Fluent Forms.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Blocks;

use ZinnDigital\PBS\Rest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One table of providers; a provider is ACTIVE when its own shortcode is registered — the one
 * thing the block needs from it, and a test that holds whatever the plugin's version or edition.
 * The block stores the provider and the form's id, and renders the plugin's own shortcode, so
 * the form keeps working exactly as its plugin built it (validation, spam checks, entries).
 */
final class Forms {

	/** Form id: digits, or a Contact Form 7 hash. */
	public const ID_RE = '/^[a-z0-9]{1,40}$/';

	/**
	 * The providers.
	 *
	 * @return array<string, array{label: string, tag: string, shortcode: string}>
	 */
	public static function providers(): array {
		return array(
			'cf7'          => array(
				'label'     => 'Contact Form 7',
				'tag'       => 'contact-form-7',
				'shortcode' => '[contact-form-7 id="%s"]',
			),
			'wpforms'      => array(
				'label'     => 'WPForms',
				'tag'       => 'wpforms',
				'shortcode' => '[wpforms id="%s" title="false"]',
			),
			'gravityforms' => array(
				'label'     => 'Gravity Forms',
				'tag'       => 'gravityform',
				'shortcode' => '[gravityform id="%s" title="false" description="false" ajax="true"]',
			),
			'fluentforms'  => array(
				'label'     => 'Fluent Forms',
				'tag'       => 'fluentform',
				'shortcode' => '[fluentform id="%s"]',
			),
		);
	}

	/**
	 * Is the provider's plugin active?
	 *
	 * @param string $provider Provider key.
	 * @return bool
	 */
	public static function active( string $provider ): bool {
		$p = self::providers()[ $provider ] ?? null;

		return null !== $p && shortcode_exists( $p['tag'] );
	}

	/**
	 * The shortcode for a form, or '' when the provider or id is not valid. Pure.
	 *
	 * @param string $provider Provider key.
	 * @param string $form_id  Form id.
	 * @return string
	 */
	public static function shortcode( string $provider, string $form_id ): string {
		$p = self::providers()[ $provider ] ?? null;
		if ( null === $p || 1 !== preg_match( self::ID_RE, $form_id ) ) {
			return '';
		}

		return sprintf( $p['shortcode'], $form_id );
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
	}

	/**
	 * `GET pbs/v1/forms`: the providers, which are active, and each active one's forms.
	 *
	 * @return void
	 */
	public static function register_routes(): void {
		register_rest_route(
			Rest::NAMESPACE,
			'/forms',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'rest_list' ),
				'permission_callback' => static fn(): bool => current_user_can( 'edit_posts' ),
			)
		);
	}

	/**
	 * The list.
	 *
	 * @return \WP_REST_Response
	 */
	public static function rest_list(): \WP_REST_Response {
		$out = array();
		foreach ( self::providers() as $key => $p ) {
			$active = self::active( $key );
			$out[]  = array(
				'provider' => $key,
				'label'    => $p['label'],
				'active'   => $active,
				'forms'    => $active ? self::forms( $key ) : array(),
			);
		}

		return new \WP_REST_Response( $out );
	}

	/**
	 * A provider's forms (id, title), newest first, at most 200.
	 *
	 * @param string $provider Provider key.
	 * @return array<int, array{id: string, title: string}>
	 */
	public static function forms( string $provider ): array {
		$rows = array();
		switch ( $provider ) {
			case 'cf7':
			case 'wpforms':
				foreach ( get_posts(
					array(
						'post_type'      => 'cf7' === $provider ? 'wpcf7_contact_form' : 'wpforms',
						'post_status'    => 'publish',
						'posts_per_page' => 200, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- a picker list, in wp-admin only.
						'no_found_rows'  => true,
					)
				) as $post ) {
					$rows[] = array( (string) $post->ID, (string) $post->post_title );
				}
				break;
			case 'gravityforms':
				if ( class_exists( '\GFAPI' ) ) {
					foreach ( (array) \GFAPI::get_forms() as $form ) {
						$rows[] = array( (string) ( $form['id'] ?? '' ), (string) ( $form['title'] ?? '' ) );
					}
				}
				break;
			case 'fluentforms':
				// Only reached while Fluent Forms is active (its shortcode is registered), so its table
				// exists; the query is prepared, the table name an identifier placeholder.
				global $wpdb;
				foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT id, title FROM %i ORDER BY id DESC LIMIT %d', $wpdb->prefix . 'fluentform_forms', 200 ) ) as $row ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the plugin's own table, a wp-admin picker.
					$rows[] = array( (string) $row->id, (string) $row->title );
				}
				break;
		}
		$out = array();
		foreach ( $rows as [ $id, $title ] ) {
			if ( 1 === preg_match( self::ID_RE, $id ) ) {
				$out[] = array(
					'id'    => $id,
					'title' => '' === trim( $title ) ? '#' . $id : wp_strip_all_tags( $title ),
				);
			}
		}

		return $out;
	}
}
