/**
 * pbs/tooltip saved markup: the word and its explanation in brackets — what a reader gets with
 * the plugin off. No classes, no data-wp-*.
 */
import { RichText } from '@wordpress/block-editor';

export default function save( { attributes } ) {
	const { text, tip } = attributes;
	if ( ! text ) {
		return null;
	}
	return (
		<p>
			<RichText.Content value={ text } />
			{ tip && <small>{ ` (${ tip })` }</small> }
		</p>
	);
}
