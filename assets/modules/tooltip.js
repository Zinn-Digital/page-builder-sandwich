/**
 * pbs/tooltip view module (Interactivity API): Escape hides the tip while the pointer or focus
 * stays on the trigger (WAI-ARIA Tooltip pattern); leaving resets it. Showing and hiding is CSS.
 * `__PREFIX__` is the store namespace.
 */
import { store, getContext } from '@wordpress/interactivity';

// The class prefix: written into the copy published under uploads, or read from this
// module's own URL when it is served from the plugin folder (uploads not writable).
const PFX = '__PREFIX__'.startsWith( '__' )
	? new URL( import.meta.url ).searchParams.get( 'pfx' ) || ''
	: '__PREFIX__';

store( PFX, {
	actions: {
		tipKey( event ) {
			if ( event.key === 'Escape' ) {
				getContext().tipHidden = true;
			}
		},
		tipReset() {
			getContext().tipHidden = false;
		},
	},
} );
