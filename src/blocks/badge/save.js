/**
 * pbs/badge saved markup: the footprint-free fallback (the label as bold text, no classes). The
 * live HTML is rebuilt by Blocks\Content::render_badge().
 */
import { RichText } from '@wordpress/block-editor';

export default function save( { attributes } ) {
	if ( ! attributes.content ) {
		return null;
	}
	return (
		<p>
			<strong>
				<RichText.Content value={ attributes.content } />
			</strong>
		</p>
	);
}
