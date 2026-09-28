/**
 * pbs/whatsapp saved markup: a plain link to the chat (no classes, no data-wp-*, no translated
 * text). The floating button is rebuilt by Marketing::render_whatsapp().
 */
import { contactHref } from '../contact-buttons/links';

export default function save( { attributes } ) {
	const href = contactHref(
		'whatsapp',
		attributes.phone,
		attributes.message
	);
	if ( ! href ) {
		return null;
	}
	return (
		<p>
			<a href={ href }>{ attributes.label || attributes.phone }</a>
		</p>
	);
}
