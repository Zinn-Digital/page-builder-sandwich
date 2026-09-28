<?php
/**
 * Render callbacks of the "Marketing" group of design-system blocks (lane L09).
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Blocks;

use ZinnDigital\PBS\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-kit.php';
require_once __DIR__ . '/class-forms.php';

/**
 * One public static `render_<slug>()` per block of the group (Registry::callback()). Every
 * callback REBUILDS the live HTML from the attributes, prints classes only through
 * Frontend::cls(), escapes every value it prints, and translates every string it adds.
 */
final class Marketing {

	/**
	 * Social networks: slug => [label, built-in fallback icon]. The drawing a visitor sees is the
	 * brand icon the editor stored with the link (Font Awesome Free brands, sanitised); the
	 * built-in is only a fallback.
	 */
	public const NETWORKS = array(
		'facebook'  => array( 'Facebook', 'link' ),
		'x-twitter' => array( 'X', 'link' ),
		'instagram' => array( 'Instagram', 'link' ),
		'linkedin'  => array( 'LinkedIn', 'link' ),
		'youtube'   => array( 'YouTube', 'link' ),
		'tiktok'    => array( 'TikTok', 'link' ),
		'pinterest' => array( 'Pinterest', 'link' ),
		'github'    => array( 'GitHub', 'link' ),
		'whatsapp'  => array( 'WhatsApp', 'phone' ),
		'telegram'  => array( 'Telegram', 'link' ),
		'reddit'    => array( 'Reddit', 'link' ),
		'mastodon'  => array( 'Mastodon', 'link' ),
		'threads'   => array( 'Threads', 'link' ),
		'bluesky'   => array( 'Bluesky', 'link' ),
		'discord'   => array( 'Discord', 'link' ),
		'twitch'    => array( 'Twitch', 'link' ),
		'snapchat'  => array( 'Snapchat', 'link' ),
		'tumblr'    => array( 'Tumblr', 'link' ),
		'vimeo'     => array( 'Vimeo', 'link' ),
		'dribbble'  => array( 'Dribbble', 'link' ),
		'behance'   => array( 'Behance', 'link' ),
		'medium'    => array( 'Medium', 'link' ),
		'spotify'   => array( 'Spotify', 'link' ),
		'email'     => array( '', 'mail' ),
		'phone'     => array( '', 'phone' ),
		'rss'       => array( '', 'rss' ),
		'website'   => array( '', 'link' ),
	);

	/**
	 * The accessible name of a network link when the author left the label empty.
	 *
	 * @param string $network Network slug.
	 * @return string Unescaped.
	 */
	public static function network_label( string $network ): string {
		switch ( $network ) {
			case 'email':
				return __( 'Email', 'page-builder-sandwich' );
			case 'phone':
				return __( 'Phone', 'page-builder-sandwich' );
			case 'rss':
				return __( 'RSS feed', 'page-builder-sandwich' );
			case 'website':
				return __( 'Website', 'page-builder-sandwich' );
			default:
				return self::NETWORKS[ $network ][0] ?? __( 'Link', 'page-builder-sandwich' );
		}
	}

	/**
	 * `pbs/social-icons`: links to profiles, each an icon with an accessible name.
	 *
	 * @param array<string, mixed> $attributes Attributes: items [{networkSlug, url, label, icon, iconId}], look, shape, size, justify, newTab.
	 * @return string
	 */
	public static function render_social_icons( array $attributes ): string {
		$new   = ! isset( $attributes['newTab'] ) || ! empty( $attributes['newTab'] );
		$links = '';
		foreach ( Kit::items( $attributes['items'] ?? array(), 40 ) as $item ) {
			$url = Kit::url( $item['url'] ?? '' );
			if ( '' === $url ) {
				continue;
			}
			$network = is_string( $item['networkSlug'] ?? null ) && isset( self::NETWORKS[ $item['networkSlug'] ] ) ? $item['networkSlug'] : 'website';
			$label   = is_string( $item['label'] ?? null ) ? trim( wp_strip_all_tags( $item['label'] ) ) : '';
			if ( '' === $label ) {
				$label = self::network_label( $network );
			}
			// mailto:/tel: never open a tab; profiles do when asked.
			$external = $new && 1 === preg_match( '#^(https?:)?//#i', (string) ( $item['url'] ?? '' ) );
			$name     = $external
				/* translators: %s: link name, e.g. "GitHub". */
				? sprintf( __( '%s (opens in a new tab)', 'page-builder-sandwich' ), $label )
				: $label;
			$links .= '<li class="' . esc_attr( Frontend::cls( 'social-icons__item' ) ) . '">'
				. '<a class="' . esc_attr( Frontend::cls( 'social-icons__link', 'social-icons__link--' . $network ) ) . '" href="' . $url . '"'
				. ( $external ? ' target="_blank" rel="noopener me"' : '' )
				. ' aria-label="' . esc_attr( $name ) . '">'
				. Kit::icon( (string) ( $item['icon'] ?? '' ), self::NETWORKS[ $network ][1] )
				. '</a></li>';
		}
		if ( '' === $links ) {
			return '';
		}
		$look    = Kit::choice( $attributes['look'] ?? '', array( 'filled', 'plain', 'outline' ) );
		$shape   = Kit::choice( $attributes['shape'] ?? '', array( 'circle', 'rounded', 'square' ) );
		$size    = Kit::choice( $attributes['size'] ?? '', array( 'medium', 'small', 'large' ) );
		$justify = Kit::choice( $attributes['justify'] ?? '', array( 'start', 'center', 'end' ) );

		return '<ul class="' . esc_attr( Frontend::cls( 'social-icons', 'social-icons--' . $look, 'social-icons--' . $shape, 'social-icons--' . $size, 'social-icons--' . $justify ) ) . '">' . $links . '</ul>';
	}

	/**
	 * `pbs/testimonial`: a customer's words, who said them, and an optional star rating.
	 *
	 * @param array<string, mixed> $attributes Attributes: quote, name, role, imageUrl, imageAlt, rating, layout, align.
	 * @return string
	 */
	public static function render_testimonial( array $attributes ): string {
		$quote = Kit::rich( $attributes['quote'] ?? '' );
		if ( '' === $quote ) {
			return '';
		}
		$layout = Kit::choice( $attributes['layout'] ?? '', array( 'stacked', 'side' ) );
		$align  = Kit::choice( $attributes['align'] ?? '', array( 'start', 'center' ) );
		$rating = is_numeric( $attributes['rating'] ?? null ) ? max( 0, min( 5, (int) $attributes['rating'] ) ) : 0;
		$html   = '<figure class="' . esc_attr( Frontend::cls( 'testimonial', 'testimonial--' . $layout, 'testimonial--' . $align ) ) . '">';

		if ( $rating > 0 ) {
			$stars = '';
			for ( $n = 1; $n <= 5; $n++ ) {
				$stars .= Kit::icon( '', $n <= $rating ? 'star-f' : 'star' );
			}
			$html .= '<p class="' . esc_attr( Frontend::cls( 'testimonial__rating' ) ) . '">'
				. '<span class="' . esc_attr( Frontend::cls( 'testimonial__stars' ) ) . '" aria-hidden="true">' . $stars . '</span>'
				/* translators: %d: number of stars, 1 to 5. */
				. Kit::sr( esc_html( sprintf( __( 'Rated %d out of 5', 'page-builder-sandwich' ), $rating ) ) ) . '</p>';
		}
		$html .= '<blockquote class="' . esc_attr( Frontend::cls( 'testimonial__quote' ) ) . '"><p>' . $quote . '</p></blockquote>';

		$name = Kit::rich( $attributes['name'] ?? '' );
		$role = Kit::rich( $attributes['role'] ?? '' );
		$img  = Kit::url( $attributes['imageUrl'] ?? '' );
		if ( '' !== $name || '' !== $role || '' !== $img ) {
			$html .= '<figcaption class="' . esc_attr( Frontend::cls( 'testimonial__author' ) ) . '">';
			if ( '' !== $img ) {
				// Decorative by default: the name is right next to it. An author may describe it.
				$html .= '<img class="' . esc_attr( Frontend::cls( 'testimonial__photo' ) ) . '" src="' . $img . '" alt="' . esc_attr( Kit::text( $attributes['imageAlt'] ?? '' ) ) . '" width="56" height="56" loading="lazy" decoding="async">';
			}
			$html .= '<span class="' . esc_attr( Frontend::cls( 'testimonial__who' ) ) . '">';
			if ( '' !== $name ) {
				$html .= '<span class="' . esc_attr( Frontend::cls( 'testimonial__name' ) ) . '">' . $name . '</span>';
			}
			if ( '' !== $role ) {
				$html .= '<span class="' . esc_attr( Frontend::cls( 'testimonial__role' ) ) . '">' . $role . '</span>';
			}
			$html .= '</span></figcaption>';
		}

		return $html . '</figure>';
	}

	/**
	 * One call-to-action button (a link styled as a button).
	 *
	 * @param mixed  $text    Label.
	 * @param mixed  $url     Address.
	 * @param bool   $new_tab New tab.
	 * @param string $kind    primary|secondary.
	 * @return string
	 */
	private static function cta_button( $text, $url, bool $new_tab, string $kind ): string {
		$label = Kit::text( $text );
		$href  = Kit::url( $url );
		if ( '' === $label || '' === $href ) {
			return '';
		}

		return '<a class="' . esc_attr( Frontend::cls( 'call-to-action__button', 'call-to-action__button--' . $kind ) ) . '" href="' . $href . '"' . Kit::target( $new_tab ) . '>' . $label
			. ( $new_tab ? Kit::sr( esc_html__( '(opens in a new tab)', 'page-builder-sandwich' ) ) : '' ) . '</a>';
	}

	/**
	 * `pbs/call-to-action`: a heading, a sentence and one or two buttons.
	 *
	 * @param array<string, mixed> $attributes Attributes: title, content, level, primary*, secondary*, layout, align.
	 * @return string
	 */
	public static function render_call_to_action( array $attributes ): string {
		$title   = Kit::rich( $attributes['title'] ?? '' );
		$content = Kit::rich( $attributes['content'] ?? '' );
		$buttons = self::cta_button( $attributes['primaryText'] ?? '', $attributes['primaryUrl'] ?? '', ! empty( $attributes['primaryNewTab'] ), 'primary' )
			. self::cta_button( $attributes['secondaryText'] ?? '', $attributes['secondaryUrl'] ?? '', ! empty( $attributes['secondaryNewTab'] ), 'secondary' );
		if ( '' === $title && '' === $content && '' === $buttons ) {
			return '';
		}
		$level  = Kit::level( $attributes['level'] ?? 2, 2 );
		$layout = Kit::choice( $attributes['layout'] ?? '', array( 'stacked', 'split' ) );
		$align  = Kit::choice( $attributes['align'] ?? '', array( 'center', 'start' ) );

		$html = '<div class="' . esc_attr( Frontend::cls( 'call-to-action', 'call-to-action--' . $layout, 'call-to-action--' . $align ) ) . '"><div class="' . esc_attr( Frontend::cls( 'call-to-action__body' ) ) . '">';
		if ( '' !== $title ) {
			$html .= '<h' . $level . ' class="' . esc_attr( Frontend::cls( 'call-to-action__title' ) ) . '">' . $title . '</h' . $level . '>';
		}
		if ( '' !== $content ) {
			$html .= '<p class="' . esc_attr( Frontend::cls( 'call-to-action__text' ) ) . '">' . $content . '</p>';
		}
		$html .= '</div>';
		if ( '' !== $buttons ) {
			$html .= '<div class="' . esc_attr( Frontend::cls( 'call-to-action__buttons' ) ) . '">' . $buttons . '</div>';
		}

		return $html . '</div>';
	}

	/**
	 * `pbs/comparison-table`: plans or products side by side. Yes/no cells are drawn AND said in
	 * words ("Included" / "Not included"); the table scrolls sideways on its own, never the page.
	 *
	 * @param array<string, mixed> $attributes Attributes: caption, columns [{title, highlight}], rows [{label, cells [{type, text}]}].
	 * @return string
	 */
	public static function render_comparison_table( array $attributes ): string {
		static $count = 0;

		$columns = Kit::items( $attributes['columns'] ?? array(), 8 );
		$rows    = Kit::items( $attributes['rows'] ?? array(), 200 );
		if ( array() === $columns || array() === $rows ) {
			return '';
		}
		++$count;
		$id      = Frontend::cls( 'ct-' . $count );
		$caption = Kit::rich( $attributes['caption'] ?? '' );

		$head = '<tr><td></td>';
		foreach ( $columns as $col ) {
			$hi    = ! empty( $col['highlight'] );
			$head .= '<th scope="col" class="' . esc_attr( Frontend::cls( 'comparison-table__plan', $hi ? 'comparison-table__plan--highlight' : '' ) ) . '">' . Kit::rich( $col['title'] ?? '' )
				. ( $hi ? Kit::sr( esc_html__( '(recommended)', 'page-builder-sandwich' ) ) : '' ) . '</th>';
		}
		$head .= '</tr>';

		$body = '';
		foreach ( $rows as $row ) {
			$label = Kit::rich( $row['label'] ?? '' );
			if ( '' === $label ) {
				continue;
			}
			$cells = Kit::items( $row['cells'] ?? array(), 8 );
			$body .= '<tr><th scope="row" class="' . esc_attr( Frontend::cls( 'comparison-table__feature' ) ) . '">' . $label . '</th>';
			foreach ( $columns as $i => $col ) {
				$cell  = $cells[ $i ] ?? array();
				$type  = Kit::choice( $cell['type'] ?? '', array( 'text', 'yes', 'no' ) );
				$hi    = ! empty( $col['highlight'] ) ? 'comparison-table__cell--highlight' : '';
				$body .= '<td class="' . esc_attr( Frontend::cls( 'comparison-table__cell', 'comparison-table__cell--' . $type, $hi ) ) . '">';
				if ( 'yes' === $type ) {
					$body .= Kit::icon( '', 'check' ) . Kit::sr( esc_html__( 'Included', 'page-builder-sandwich' ) );
				} elseif ( 'no' === $type ) {
					$body .= Kit::icon( '', 'cross' ) . Kit::sr( esc_html__( 'Not included', 'page-builder-sandwich' ) );
				} else {
					$body .= Kit::rich( $cell['text'] ?? '' );
				}
				$body .= '</td>';
			}
			$body .= '</tr>';
		}
		if ( '' === $body ) {
			return '';
		}

		// A scrolling region must be reachable by keyboard and named (WCAG 2.1.1, axe
		// scrollable-region-focusable): tabindex=0 and labelled by the caption.
		$name = '' !== $caption ? ' aria-labelledby="' . esc_attr( $id ) . '"' : ' aria-label="' . esc_attr__( 'Comparison table', 'page-builder-sandwich' ) . '"';

		return '<div class="' . esc_attr( Frontend::cls( 'comparison-table' ) ) . '" role="region"' . $name . ' tabindex="0">'
			. '<table class="' . esc_attr( Frontend::cls( 'comparison-table__table' ) ) . '">'
			. ( '' !== $caption ? '<caption id="' . esc_attr( $id ) . '" class="' . esc_attr( Frontend::cls( 'comparison-table__caption' ) ) . '">' . $caption . '</caption>' : '' )
			. '<thead>' . $head . '</thead><tbody>' . $body . '</tbody></table></div>';
	}

	/** Contact channels: type → SVG drawing (24×24, stroke). */
	public const ICONS = array(
		'phone'    => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/>',
		'email'    => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/>',
		'sms'      => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
		'whatsapp' => '<path d="M3 21l1.7-5A9 9 0 1 1 8 19.4L3 21z"/><path d="M9 10a.5.5 0 0 0 1 0V9a.5.5 0 0 0-1 0v1a5 5 0 0 0 5 5h1a.5.5 0 0 0 0-1h-1a.5.5 0 0 0 0 1"/>',
	);

	/**
	 * The link for a contact channel, or '' when the value is not usable.
	 *
	 * @param string $type    phone | email | sms | whatsapp.
	 * @param string $value   Number or address.
	 * @param string $message WhatsApp prefilled message.
	 * @return string
	 */
	public static function contact_href( string $type, string $value, string $message = '' ): string {
		$value  = trim( $value );
		$digits = (string) preg_replace( '/\D+/', '', $value );
		switch ( $type ) {
			case 'phone':
			case 'sms':
				$plus = str_starts_with( $value, '+' ) ? '+' : '';
				return strlen( $digits ) >= 3 && strlen( $digits ) <= 17 ? ( 'phone' === $type ? 'tel:' : 'sms:' ) . $plus . $digits : '';
			case 'email':
				$email = sanitize_email( $value );
				return '' !== $email && false !== filter_var( $email, FILTER_VALIDATE_EMAIL ) ? 'mailto:' . $email : '';
			case 'whatsapp':
				if ( strlen( $digits ) < 7 || strlen( $digits ) > 15 ) {
					return '';
				}
				$message = trim( sanitize_textarea_field( $message ) );
				return 'https://wa.me/' . $digits . ( '' !== $message ? '?text=' . rawurlencode( $message ) : '' );
		}

		return '';
	}

	/**
	 * The accessible default label of a channel.
	 *
	 * @param string $type  Type.
	 * @param string $value Value.
	 * @return string
	 */
	private static function default_label( string $type, string $value ): string {
		switch ( $type ) {
			case 'phone':
				/* translators: %s: a phone number. */
				return sprintf( __( 'Call %s', 'page-builder-sandwich' ), $value );
			case 'email':
				/* translators: %s: an email address. */
				return sprintf( __( 'Email %s', 'page-builder-sandwich' ), $value );
			case 'sms':
				/* translators: %s: a phone number. */
				return sprintf( __( 'Text %s', 'page-builder-sandwich' ), $value );
			default:
				return __( 'Chat on WhatsApp', 'page-builder-sandwich' );
		}
	}

	/**
	 * An icon.
	 *
	 * @param string $type Type.
	 * @param string $cls  Class attribute value (escaped).
	 * @return string
	 */
	private static function icon( string $type, string $cls ): string {
		return '<svg class="' . $cls . '" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
			. self::ICONS[ $type ] . '</svg>';
	}

	/**
	 * `pbs/contact-buttons`: call / email / text / WhatsApp buttons.
	 *
	 * @param array<string, mixed> $attributes Attributes: items [{type, value, label}], message.
	 * @return string
	 */
	public static function render_contact_buttons( array $attributes ): string {
		$c    = static fn( string ...$n ): string => esc_attr( Frontend::cls( ...$n ) );
		$html = '';
		foreach ( is_array( $attributes['items'] ?? null ) ? array_slice( $attributes['items'], 0, 8 ) : array() as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$type = (string) ( $item['type'] ?? '' );
			if ( ! isset( self::ICONS[ $type ] ) ) {
				continue;
			}
			$value = trim( sanitize_text_field( (string) ( $item['link'] ?? '' ) ) );
			$href  = self::contact_href( $type, $value, (string) ( $attributes['message'] ?? '' ) );
			if ( '' === $href ) {
				continue;
			}
			$label = trim( sanitize_text_field( (string) ( $item['label'] ?? '' ) ) );
			$label = '' === $label ? self::default_label( $type, $value ) : $label;
			$ext   = 'whatsapp' === $type ? ' rel="noopener"' : '';
			$html .= '<li class="' . $c( 'contact-buttons__item' ) . '"><a class="' . $c( 'contact-buttons__link', 'contact-buttons__link--' . $type ) . '" href="' . esc_url( $href, array( 'tel', 'sms', 'mailto', 'https' ) ) . '"' . $ext . '>'
				. self::icon( $type, $c( 'contact-buttons__icon' ) )
				. '<span class="' . $c( 'contact-buttons__label' ) . '">' . esc_html( $label ) . '</span></a></li>';
		}
		if ( '' === $html ) {
			return '';
		}

		return '<ul class="' . $c( 'contact-buttons' ) . '" aria-label="' . esc_attr__( 'Contact us', 'page-builder-sandwich' ) . '">' . $html . '</ul>';
	}

	/**
	 * `pbs/whatsapp`: a floating "chat on WhatsApp" button, fixed to a bottom corner (the logical
	 * end corner by default, so it sits on the left in a right-to-left page). It is a plain link:
	 * nothing is loaded from WhatsApp until the visitor presses it.
	 *
	 * @param array<string, mixed> $attributes Attributes: phone, message, label, showLabel, position (start|end).
	 * @return string
	 */
	public static function render_whatsapp( array $attributes ): string {
		$href = self::contact_href( 'whatsapp', (string) ( $attributes['phone'] ?? '' ), (string) ( $attributes['message'] ?? '' ) );
		if ( '' === $href ) {
			return '';
		}
		$c        = static fn( string ...$n ): string => esc_attr( Frontend::cls( ...$n ) );
		$label    = trim( sanitize_text_field( (string) ( $attributes['label'] ?? '' ) ) );
		$label    = '' === $label ? __( 'Chat with us on WhatsApp', 'page-builder-sandwich' ) : $label;
		$position = 'start' === ( $attributes['position'] ?? 'end' ) ? 'start' : 'end';
		$show     = ! empty( $attributes['showLabel'] );

		return '<a class="' . $c( 'whatsapp', 'whatsapp--' . $position, $show ? 'whatsapp--labelled' : '' ) . '" href="' . esc_url( $href ) . '" rel="noopener"'
			. ( $show ? '' : ' aria-label="' . esc_attr( $label ) . '"' ) . '>'
			. '<svg class="' . $c( 'whatsapp__icon' ) . '" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="28" height="28" fill="currentColor" aria-hidden="true" focusable="false">'
			. '<path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8s-.4-.1-.6.1-.7.8-.8 1-.3.2-.5.1a6.7 6.7 0 0 1-2-1.2 7.4 7.4 0 0 1-1.4-1.7c-.1-.3 0-.4.1-.5l.4-.4.2-.4a.5.5 0 0 0 0-.4l-.8-1.9c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.7 11.8 11.8 0 0 0 4.5 4c.6.3 1.1.4 1.5.5a3.6 3.6 0 0 0 1.6.1 2.7 2.7 0 0 0 1.8-1.3 2.2 2.2 0 0 0 .2-1.3c-.1-.1-.3-.2-.5-.3z"/></svg>'
			. ( $show ? '<span class="' . $c( 'whatsapp__label' ) . '">' . esc_html( $label ) . '</span>' : '' )
			. '</a>';
	}

	/** Field styles (block.json enum). */
	private const FIELD_STYLES = array( 'outlined', 'filled', 'underlined' );

	/**
	 * `pbs/form` (pbs-m6): the chosen form plugin's form, rendered by that plugin (its own
	 * shortcode) and styled by the design system through the block's classes. When the plugin is
	 * not active or no form is chosen, a short notice replaces it rather than the raw shortcode
	 * text WordPress would otherwise print.
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 * @return string
	 */
	public static function render_form( array $attributes ): string {
		$provider  = (string) ( $attributes['provider'] ?? '' );
		$form_id   = (string) ( $attributes['formId'] ?? '' );
		$shortcode = Forms::shortcode( $provider, $form_id );
		$style     = in_array( $attributes['fieldStyle'] ?? '', self::FIELD_STYLES, true ) ? (string) $attributes['fieldStyle'] : 'outlined';
		$classes   = array( 'form', 'form--' . $style );
		if ( 'full' === ( $attributes['buttonWidth'] ?? '' ) ) {
			$classes[] = 'form--btn-full';
		}

		if ( '' === $shortcode || ! Forms::active( $provider ) ) {
			$message = __( 'This form is not available right now.', 'page-builder-sandwich' );
			if ( '' !== $shortcode && current_user_can( 'edit_posts' ) ) {
				$message = sprintf(
					/* translators: %s: form plugin name. */
					__( 'This form cannot be shown because %s is not active.', 'page-builder-sandwich' ),
					Forms::providers()[ $provider ]['label']
				);
			}

			return '<div class="' . esc_attr( Frontend::cls( 'form', 'form--missing' ) ) . '"><p class="' . esc_attr( Frontend::cls( 'form__missing' ) ) . '">' . esc_html( $message ) . '</p></div>';
		}

		return '<div class="' . esc_attr( Frontend::cls( ...$classes ) ) . '">' . do_shortcode( $shortcode ) . '</div>';
	}
}
