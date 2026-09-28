/**
 * Contact links (twin of Blocks\Marketing::contact_href()).
 */

/**
 * The link for a channel, or '' when the value is not usable.
 *
 * @param {string} type    phone | email | sms | whatsapp.
 * @param {string} value   Number or address.
 * @param {string} message WhatsApp prefilled message.
 * @return {string} Link.
 */
export function contactHref( type, value, message = '' ) {
	const v = String( value || '' ).trim();
	const digits = v.replace( /\D+/g, '' );
	switch ( type ) {
		case 'phone':
		case 'sms': {
			const plus = v.startsWith( '+' ) ? '+' : '';
			return digits.length >= 3 && digits.length <= 17
				? `${ type === 'phone' ? 'tel:' : 'sms:' }${ plus }${ digits }`
				: '';
		}
		case 'email':
			return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( v )
				? `mailto:${ v }`
				: '';
		case 'whatsapp': {
			if ( digits.length < 7 || digits.length > 15 ) {
				return '';
			}
			const m = String( message || '' ).trim();
			return `https://wa.me/${ digits }${ m ? `?text=${ encodeURIComponent( m ) }` : '' }`;
		}
	}
	return '';
}
