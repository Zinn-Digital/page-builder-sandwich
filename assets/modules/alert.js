/**
 * The alert block's close button (Interactivity API view module, published through the neutral
 * path by Assets\Perf\Modules — never from the plugin directory). `__PREFIX__` is the store
 * namespace, the same neutral prefix the classes use.
 *
 * The button is rendered `hidden` and only shown here, once this module runs: without script a
 * close button would do nothing, so it is not offered.
 */
import { store, getContext } from '@wordpress/interactivity';

// The class prefix: written into the copy published under uploads, or read from this
// module's own URL when it is served from the plugin folder (uploads not writable).
const PFX = '__PREFIX__'.startsWith( '__' )
	? new URL( import.meta.url ).searchParams.get( 'pfx' ) || ''
	: '__PREFIX__';

store( PFX, {
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
