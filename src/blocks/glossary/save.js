/**
 * pbs/glossary saved markup: the footprint-free fallback (a description list in the order the
 * author wrote it). The live HTML — sorted, anchored, with the optional index — is rebuilt by
 * Blocks\Content::render_glossary().
 */
import { RichText } from '@wordpress/block-editor';

export default function save( { attributes } ) {
	const items = ( attributes.items || [] ).filter(
		( i ) => i && i.term && i.definition
	);
	if ( ! items.length ) {
		return null;
	}
	return (
		<dl>
			{ items.map( ( item, i ) => (
				<div key={ i }>
					<RichText.Content tagName="dt" value={ item.term } />
					<RichText.Content tagName="dd" value={ item.definition } />
				</div>
			) ) }
		</dl>
	);
}
