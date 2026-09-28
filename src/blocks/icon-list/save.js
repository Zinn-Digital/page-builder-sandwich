/**
 * pbs/icon-list saved markup: the footprint-free fallback (a plain list, no classes, no
 * data-wp-*). The live HTML is rebuilt by Blocks\Content::render_icon_list().
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
				<RichText.Content key={ i } tagName="li" value={ item.text } />
			) ) }
		</ul>
	);
}
