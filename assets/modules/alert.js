/**
 * The alert block's close button (Interactivity API view module, published through the neutral
 * path by Assets\Perf\Modules — never from the plugin directory). `__PREFIX__` is the store
 * namespace, the same neutral prefix the classes use.
 *
 * The button is rendered `hidden` and only shown here, once this module runs: without script a
 * close button would do nothing, so it is not offered.
 */
import { store, getContext } from '@wordpress/interactivity';

store( '__PREFIX__', {
	actions: {
		alertDismiss() {
			getContext().open = false;
		},
	},
	callbacks: {
		alertReady() {
			getContext().ready = true;
		},
	},
} );
