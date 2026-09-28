/**
 * pbs/tab saved markup: the panel's content under its title as a heading — what a reader gets
 * with the plugin off. No classes, no data-wp-*.
 */
import { InnerBlocks, RichText } from '@wordpress/block-editor';

export default function save( { attributes } ) {
	return (
		<section>
			{ attributes.title && (
				<RichText.Content tagName="h3" value={ attributes.title } />
			) }
			<InnerBlocks.Content />
		</section>
	);
}
