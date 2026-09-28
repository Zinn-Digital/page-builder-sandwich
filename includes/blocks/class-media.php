<?php
/**
 * Render callbacks of the "Media" group of design-system blocks (lane L09).
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Blocks;

use ZinnDigital\PBS\Assets\Perf\Modules;
use ZinnDigital\PBS\Core\Svg;
use ZinnDigital\PBS\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One public static `render_<slug>()` per block of the group (Registry::callback()). Every
 * callback REBUILDS the live HTML from the attributes, prints classes only through
 * Frontend::cls(), escapes every value it prints, and translates every string it adds.
 *
 * ⭐ Third-party embeds (the map, booking calendars) are CLICK-TO-LOAD: the page carries only a
 * notice, a "Show" button and a plain link. Nothing is requested from the third party until the
 * visitor asks for it (the view module sets the frame's `src` then). Without script the button
 * stays hidden and the link still works.
 */
final class Media {

	/** Booking providers: id → [label, URL pattern, embed URL format]. */
	public const BOOKING = array(
		'calendly' => array( 'Calendly', '#^https://calendly\.com/[A-Za-z0-9_\-/]+$#', '%s?embed_type=Inline&hide_gdpr_banner=1' ),
		'cal'      => array( 'Cal.com', '#^https://cal\.com/[A-Za-z0-9_\-/]+$#', '%s/embed?layout=month_view' ),
	);

	/**
	 * `pbs/map`: an OpenStreetMap map (no key, no account), loaded when the visitor asks.
	 *
	 * @param array<string, mixed> $attributes Attributes: lat, lng, zoom, label.
	 * @return string
	 */
	public static function render_map( array $attributes ): string {
		$lat = self::number( $attributes['lat'] ?? null, -85, 85 );
		$lng = self::number( $attributes['lng'] ?? null, -180, 180 );
		if ( null === $lat || null === $lng ) {
			return '';
		}
		$zoom  = (int) ( self::number( $attributes['zoom'] ?? 14, 1, 19 ) ?? 14 );
		$label = trim( sanitize_text_field( (string) ( $attributes['label'] ?? '' ) ) );
		$urls  = self::map_urls( $lat, $lng, $zoom );
		/* translators: %s: the place the map shows, as the author named it. */
		$title = '' === $label ? __( 'Map', 'page-builder-sandwich' ) : sprintf( __( 'Map: %s', 'page-builder-sandwich' ), $label );

		return self::consent_frame(
			'map',
			$urls['embed'],
			$title,
			__( 'Showing the map loads it from OpenStreetMap.', 'page-builder-sandwich' ),
			__( 'Show the map', 'page-builder-sandwich' ),
			$urls['link'],
			__( 'Open the map on OpenStreetMap', 'page-builder-sandwich' ),
			$label
		);
	}

	/**
	 * The embed and the plain-link URLs for a map centred on a point.
	 *
	 * @param float $lat  Latitude.
	 * @param float $lng  Longitude.
	 * @param int   $zoom Zoom (1–19).
	 * @return array{embed: string, link: string}
	 */
	public static function map_urls( float $lat, float $lng, int $zoom ): array {
		$span = 360 / ( 2 ** $zoom ) * 1.2;
		$bbox = array(
			self::num( max( -180, $lng - $span ) ),
			self::num( max( -85, $lat - $span / 2 ) ),
			self::num( min( 180, $lng + $span ) ),
			self::num( min( 85, $lat + $span / 2 ) ),
		);
		$pt   = self::num( $lat ) . ',' . self::num( $lng );

		return array(
			'embed' => 'https://www.openstreetmap.org/export/embed.html?bbox=' . rawurlencode( implode( ',', $bbox ) ) . '&layer=mapnik&marker=' . rawurlencode( $pt ),
			'link'  => 'https://www.openstreetmap.org/?mlat=' . self::num( $lat ) . '&mlon=' . self::num( $lng ) . '#map=' . $zoom . '/' . self::num( $lat ) . '/' . self::num( $lng ),
		);
	}

	/**
	 * `pbs/booking`: a Calendly or Cal.com booking page, loaded when the visitor asks.
	 *
	 * @param array<string, mixed> $attributes Attributes: provider, url, label.
	 * @return string
	 */
	public static function render_booking( array $attributes ): string {
		$provider = (string) ( $attributes['provider'] ?? 'calendly' );
		$url      = self::booking_url( $provider, (string) ( $attributes['url'] ?? '' ) );
		if ( null === $url ) {
			return '';
		}
		$name  = self::BOOKING[ $provider ][0];
		$label = trim( sanitize_text_field( (string) ( $attributes['label'] ?? '' ) ) );
		/* translators: %s: the booking service, e.g. "Calendly". */
		$title = '' === $label ? sprintf( __( 'Booking calendar from %s', 'page-builder-sandwich' ), $name ) : $label;

		return self::consent_frame(
			'booking',
			sprintf( self::BOOKING[ $provider ][2], $url ),
			$title,
			/* translators: %s: the booking service, e.g. "Calendly". */
			sprintf( __( 'Showing the booking calendar loads it from %s.', 'page-builder-sandwich' ), $name ),
			__( 'Show the booking calendar', 'page-builder-sandwich' ),
			$url,
			/* translators: %s: the booking service, e.g. "Calendly". */
			sprintf( __( 'Book on %s', 'page-builder-sandwich' ), $name ),
			$label
		);
	}

	/**
	 * A booking page URL the provider serves, or null.
	 *
	 * @param string $provider Provider id.
	 * @param string $url      Candidate.
	 * @return string|null
	 */
	public static function booking_url( string $provider, string $url ): ?string {
		if ( ! isset( self::BOOKING[ $provider ] ) ) {
			return null;
		}
		$url = rtrim( trim( $url ), '/' );

		return strlen( $url ) <= 300 && 1 === preg_match( self::BOOKING[ $provider ][1], $url ) && ! str_contains( $url, '..' ) ? $url : null;
	}

	/**
	 * A finite number in a range, or null (numeric strings accepted).
	 *
	 * @param mixed $value Candidate.
	 * @param float $min   Minimum.
	 * @param float $max   Maximum.
	 * @return float|null
	 */
	public static function number( $value, float $min, float $max ): ?float {
		if ( is_string( $value ) && is_numeric( trim( $value ) ) ) {
			$value = (float) trim( $value );
		}
		if ( ! is_int( $value ) && ! is_float( $value ) ) {
			return null;
		}
		$value = (float) $value;

		return is_finite( $value ) && $value >= $min && $value <= $max ? $value : null;
	}

	/**
	 * A number with at most six decimals and no trailing zeros (coordinates).
	 *
	 * @param float $n Number.
	 * @return string
	 */
	public static function num( float $n ): string {
		$s = rtrim( rtrim( sprintf( '%.6F', $n ), '0' ), '.' );

		return '-0' === $s ? '0' : $s;
	}

	/**
	 * A click-to-load frame: notice + button + fallback link now, the frame only when asked.
	 *
	 * @param string $slug    Block slug (class root and module).
	 * @param string $embed   Frame URL.
	 * @param string $title   Frame title (its accessible name).
	 * @param string $notice  What loading it will do.
	 * @param string $button  Button text.
	 * @param string $link    Plain link URL (works without script).
	 * @param string $linktext Link text.
	 * @param string $caption Caption ('' for none).
	 * @return string
	 */
	private static function consent_frame( string $slug, string $embed, string $title, string $notice, string $button, string $link, string $linktext, string $caption ): string {
		Modules::enqueue( $slug );
		$verb    = lcfirst( str_replace( ' ', '', ucwords( str_replace( '-', ' ', $slug ) ) ) );
		$c       = static fn( string $name ): string => esc_attr( Frontend::cls( $name ) );
		$context = (string) wp_json_encode(
			array(
				'src'   => null,
				'url'   => $embed,
				'ready' => false,
			)
		);

		return '<figure class="' . $c( $slug ) . '" data-wp-interactive="' . esc_attr( Modules::store() ) . '"'
			. " data-wp-context='" . esc_attr( $context ) . "' data-wp-init=\"callbacks." . $verb . 'Ready">'
			. '<div class="' . $c( $slug . '__frame' ) . '">'
			. '<iframe class="' . $c( $slug . '__iframe' ) . '" title="' . esc_attr( $title ) . '" hidden loading="lazy"'
			. ' data-wp-bind--hidden="!context.src" data-wp-bind--src="context.src"></iframe>'
			. '<div class="' . $c( $slug . '__consent' ) . '" data-wp-bind--hidden="context.src">'
			. '<p class="' . $c( $slug . '__notice' ) . '">' . esc_html( $notice ) . '</p>'
			. '<button type="button" class="' . $c( $slug . '__load' ) . '" hidden data-wp-bind--hidden="!context.ready" data-wp-on--click="actions.' . $verb . 'Load">' . esc_html( $button ) . '</button>'
			. '<a class="' . $c( $slug . '__link' ) . '" href="' . esc_url( $link ) . '" rel="noopener">' . esc_html( $linktext ) . '</a>'
			. '</div></div>'
			. ( '' !== $caption ? '<figcaption class="' . $c( $slug . '__caption' ) . '">' . esc_html( $caption ) . '</figcaption>' : '' )
			. '</figure>';
	}

	/**
	 * `pbs/qr-code`: a QR code the editor generated locally (no service), as sanitised inline SVG.
	 *
	 * @param array<string, mixed> $attributes Attributes: text, svg, caption.
	 * @return string
	 */
	public static function render_qr_code( array $attributes ): string {
		$text = trim( sanitize_text_field( (string) ( $attributes['text'] ?? '' ) ) );
		$svg  = Svg::sanitize( (string) ( $attributes['svg'] ?? '' ) );
		if ( '' === $text || '' === $svg ) {
			return '';
		}
		$caption = trim( wp_kses_post( (string) ( $attributes['caption'] ?? '' ) ) );
		$alt     = trim( sanitize_text_field( (string) ( $attributes['alt'] ?? '' ) ) );
		/* translators: %s: what the QR code encodes, e.g. a web address. */
		$alt = '' === $alt ? sprintf( __( 'QR code for %s', 'page-builder-sandwich' ), $text ) : $alt;

		$html = '<figure class="' . esc_attr( Frontend::cls( 'qr-code' ) ) . '">'
			. '<span class="' . esc_attr( Frontend::cls( 'qr-code__image' ) ) . '" role="img" aria-label="' . esc_attr( $alt ) . '">' . $svg . '</span>';
		if ( '' !== $caption ) {
			$html .= '<figcaption class="' . esc_attr( Frontend::cls( 'qr-code__caption' ) ) . '">' . $caption . '</figcaption>';
		}

		return $html . '</figure>';
	}
}
