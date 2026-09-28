/**
 * pbs/checklist saved markup: the footprint-free fallback. Each line starts with ✓ (done) or
 * ○ (to do), so the list keeps its meaning with the plugin off. No words here: save() output
 * must not depend on the editor's language, or a block saved in one language would be invalid
 * in another. The live HTML (Blocks\Content::render_checklist()) says the state in words.
 */
import { RichText } from '@wordpress/block-editor';

export default function save( { attributes } ) {
	const items = ( attributes.items || [] ).filter( ( i ) => i && i.text );
	if ( ! items.length ) {
		return null;
	}
	return (
		<ul>
			{ items.map( ( item, i ) => (
				<li key={ i }>
					{ item.done ? '✓ ' : '○ ' }
					<RichText.Content value={ item.text } />
				</li>
			) ) }
		</ul>
	);
}
