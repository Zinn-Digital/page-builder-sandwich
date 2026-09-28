/**
 * Booking providers (twin of Blocks\Media::BOOKING and booking_url()).
 */
export const PROVIDERS = {
	calendly: {
		name: 'Calendly',
		pattern: /^https:\/\/calendly\.com\/[A-Za-z0-9_\-/]+$/,
		embed: ( url ) => `${ url }?embed_type=Inline&hide_gdpr_banner=1`,
		example: 'https://calendly.com/your-name/30min',
	},
	cal: {
		name: 'Cal.com',
		pattern: /^https:\/\/cal\.com\/[A-Za-z0-9_\-/]+$/,
		embed: ( url ) => `${ url }/embed?layout=month_view`,
		example: 'https://cal.com/your-name/30min',
	},
};

/**
 * A booking page URL the provider serves, or null.
 *
 * @param {string} provider Provider id.
 * @param {string} url      Candidate.
 * @return {string|null} URL.
 */
export function bookingUrl( provider, url ) {
	const p = PROVIDERS[ provider ];
	if ( ! p ) {
		return null;
	}
	const u = String( url || '' )
		.trim()
		.replace( /\/+$/, '' );
	return u.length <= 300 && p.pattern.test( u ) && ! u.includes( '..' )
		? u
		: null;
}

/**
 * The frame URL.
 *
 * @param {string} provider Provider id.
 * @param {string} url      Valid booking URL.
 * @return {string} URL.
 */
export const embedUrl = ( provider, url ) => PROVIDERS[ provider ].embed( url );
