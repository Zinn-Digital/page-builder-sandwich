/**
 * pbs/tooltip view module (Interactivity API): Escape hides the tip while the pointer or focus
 * stays on the trigger (WAI-ARIA Tooltip pattern); leaving resets it. Showing and hiding is CSS.
 * `__PREFIX__` is the store namespace.
 */
import { store, getContext } from '@wordpress/interactivity';

store( '__PREFIX__', {
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
