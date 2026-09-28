/**
 * pbs/testimonial saved markup: the footprint-free fallback (a quote and who said it, no
 * classes). The live HTML is rebuilt by Blocks\Marketing::render_testimonial().
 */
import { RichText } from '@wordpress/block-editor';

export default function save( { attributes } ) {
	const { quote, name, role } = attributes;
	if ( ! quote ) {
		return null;
	}
	return (
		<figure>
			<blockquote>
				<RichText.Content tagName="p" value={ quote } />
			</blockquote>
			{ ( name || role ) && (
				<figcaption>
					{ name && <RichText.Content value={ name } /> }
					{ name && role && ', ' }
					{ role && <RichText.Content value={ role } /> }
				</figcaption>
			) }
		</figure>
	);
}
