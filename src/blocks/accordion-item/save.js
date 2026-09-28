/**
 * pbs/accordion-item saved markup: a native <details>, which keeps working with the plugin off.
 * No classes, no data-wp-*.
 */
import { InnerBlocks, RichText } from '@wordpress/block-editor';

export default function save( { attributes } ) {
	return (
		<details open={ attributes.open || undefined }>
			<RichText.Content tagName="summary" value={ attributes.title } />
			<InnerBlocks.Content />
		</details>
	);
}
