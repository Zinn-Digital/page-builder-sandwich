/**
 * pbs/protected saved markup: the inner blocks, in a plain <div> (no classes, no data-wp-*). They
 * must be saved to be kept; while the plugin is active Site::render_protected() never prints them
 * to a visitor who has not entered the password.
 */
import { InnerBlocks } from '@wordpress/block-editor';

export default function save() {
	return (
		<div>
			<InnerBlocks.Content />
		</div>
	);
}
