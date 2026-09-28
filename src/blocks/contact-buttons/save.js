/**
 * pbs/contact-buttons saved markup: plain links (no classes, no data-wp-*, no translated text — the
 * link shows the number or address itself). Buttons are rebuilt by Marketing::render_contact_buttons().
 */
import { contactHref } from './links';

export default function save( { attributes } ) {
	const items = ( attributes.items || [] ).filter( ( it ) =>
		contactHref( it.type, it.link, attributes.message )
	);
	if ( ! items.length ) {
		return null;
	}
	return (
		<ul>
			{ items.map( ( it, i ) => (
				<li key={ i }>
					<a
						href={ contactHref(
							it.type,
							it.link,
							attributes.message
						) }
					>
						{ it.label || it.link }
					</a>
				</li>
			) ) }
		</ul>
	);
}
