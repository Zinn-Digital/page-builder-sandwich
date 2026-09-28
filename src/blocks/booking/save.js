/**
 * pbs/booking saved markup: with the plugin off, a plain link to the booking page (no classes, no
 * data-wp-*, nothing loaded from a third party, no translated text). The live card is rebuilt by
 * Media::render_booking().
 */
import { bookingUrl } from './urls';

export default function save( { attributes } ) {
	const url = bookingUrl( attributes.provider, attributes.url );
	if ( ! url ) {
		return null;
	}
	return (
		<p>
			<a href={ url }>{ attributes.label || url }</a>
		</p>
	);
}
