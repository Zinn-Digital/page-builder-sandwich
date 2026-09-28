/**
 * pbs/accordion saved markup: its items (native <details>, pbs/accordion-item's save), so the
 * accordion still opens and closes with the plugin off. No classes, no data-wp-*.
 */
import { InnerBlocks } from '@wordpress/block-editor';

export default function save() {
	return (
		<div>
			<InnerBlocks.Content />
		</div>
	);
}
