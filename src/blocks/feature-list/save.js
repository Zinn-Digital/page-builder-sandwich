/**
 * pbs/feature-list saved markup: the footprint-free fallback (a plain list of headings and
 * texts, no classes, no data-wp-*). The live HTML is rebuilt by Blocks\Content::render_feature_list().
 */
import { RichText } from '@wordpress/block-editor';

export default function save( { attributes } ) {
	const items = ( attributes.items || [] ).filter(
		( i ) => i && ( i.title || i.text )
	);
	if ( ! items.length ) {
		return null;
	}
	const Tag = `h${ Math.max( 2, Math.min( 6, attributes.level || 2 ) ) }`;
	return (
		<ul>
			{ items.map( ( item, i ) => (
				<li key={ i }>
					{ item.title && (
						<RichText.Content
							tagName={ Tag }
							value={ item.title }
						/>
					) }
					{ item.text && (
						<RichText.Content tagName="p" value={ item.text } />
					) }
				</li>
			) ) }
		</ul>
	);
}
