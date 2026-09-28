/**
 * pbs/call-to-action saved markup: the footprint-free fallback (heading, text and the buttons as
 * plain links, no classes). The live HTML is rebuilt by Blocks\Marketing::render_call_to_action().
 */
import { RichText } from '@wordpress/block-editor';

import { safeUrl } from '../shared/kit-url';

export default function save( { attributes } ) {
	const { title, content, level } = attributes;
	const buttons = [ 'primary', 'secondary' ]
		.map( ( k ) => ( {
			text: ( attributes[ `${ k }Text` ] || '' ).replace(
				/<[^>]*>/g,
				''
			),
			url: safeUrl( attributes[ `${ k }Url` ] ),
		} ) )
		.filter( ( b ) => b.text && b.url );
	if ( ! title && ! content && ! buttons.length ) {
		return null;
	}
	const Tag = `h${ Math.max( 2, Math.min( 6, level || 2 ) ) }`;
	return (
		<div>
			{ title && <RichText.Content tagName={ Tag } value={ title } /> }
			{ content && <RichText.Content tagName="p" value={ content } /> }
			{ buttons.length > 0 && (
				<p>
					{ buttons.map( ( b, i ) => (
						<span key={ i }>
							{ i > 0 && ' · ' }
							<a href={ b.url }>{ b.text }</a>
						</span>
					) ) }
				</p>
			) }
		</div>
	);
}
