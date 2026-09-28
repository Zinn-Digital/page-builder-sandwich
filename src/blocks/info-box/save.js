/**
 * pbs/info-box saved markup: the footprint-free fallback (no classes, no data-wp-*). The live
 * HTML is rebuilt by Blocks\Content::render_info_box().
 */
import { RichText } from '@wordpress/block-editor';

import { safeUrl } from '../shared/kit-url';

export default function save( { attributes } ) {
	const { title, content, linkUrl, linkText, level } = attributes;
	if ( ! title && ! content ) {
		return null;
	}
	const url = safeUrl( linkUrl );
	const Tag = `h${ Math.max( 2, Math.min( 6, level || 2 ) ) }`;
	return (
		<div>
			{ title && <RichText.Content tagName={ Tag } value={ title } /> }
			{ content && <RichText.Content tagName="p" value={ content } /> }
			{ url && linkText && (
				<p>
					<a href={ url }>{ linkText }</a>
				</p>
			) }
		</div>
	);
}
