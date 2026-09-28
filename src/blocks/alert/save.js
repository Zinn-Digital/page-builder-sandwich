/**
 * pbs/alert saved markup: the footprint-free fallback a reader gets if the plugin is switched
 * off (no classes, no data-wp-* — the test bar asserts both). The live HTML is rebuilt by
 * Blocks\Content::render_alert().
 */
import { RichText } from '@wordpress/block-editor';

/**
 * The ARIA role of a variant: errors and warnings interrupt, the rest wait (same as the PHP).
 *
 * @param {string} variant Variant.
 * @return {string} Role.
 */
export function alertRole( variant ) {
	return 'warning' === variant || 'error' === variant ? 'alert' : 'status';
}

export default function save( { attributes } ) {
	const { variant, title, content } = attributes;
	if ( ! title && ! content ) {
		return null;
	}
	return (
		<div role={ alertRole( variant ) }>
			{ title && (
				<p>
					<strong>
						<RichText.Content value={ title } />
					</strong>
				</p>
			) }
			{ content && <RichText.Content tagName="p" value={ content } /> }
		</div>
	);
}
