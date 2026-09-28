/**
 * pbs/modal and pbs/off-canvas saved markup: the window's title and blocks shown in place — what
 * a reader gets with the plugin off. No classes, no data-wp-*. The live button and dialog are
 * rebuilt by Blocks\Interactive\Dialog.
 */
import { InnerBlocks, RichText } from '@wordpress/block-editor';

export default function save( { attributes } ) {
	return (
		<div>
			{ attributes.title && (
				<RichText.Content tagName="h2" value={ attributes.title } />
			) }
			<InnerBlocks.Content />
		</div>
	);
}
