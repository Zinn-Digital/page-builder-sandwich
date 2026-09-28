/**
 * pbs/tabs saved markup: the fallback a reader gets with the plugin off — every tab's panel in
 * order, each under its title (pbs/tab's save). No classes, no data-wp-*. The live tabs are
 * rebuilt by Blocks\Interactive\Tabs.
 */
import { InnerBlocks } from '@wordpress/block-editor';

export default function save() {
	return (
		<div>
			<InnerBlocks.Content />
		</div>
	);
}
