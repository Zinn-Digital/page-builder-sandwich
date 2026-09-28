/**
 * pbs/modal and pbs/off-canvas view module (Interactivity API): opens the block's native
 * <dialog> as a MODAL, so the browser traps focus, makes the page behind inert, closes on Escape
 * and returns focus to the button. A click on the backdrop closes it when the block allows.
 * `__PREFIX__` is the store namespace.
 */
import { store, getContext, getElement } from '@wordpress/interactivity';

store( '__PREFIX__', {
	actions: {
		dialogOpen() {
			const dialog = document.getElementById( getContext().dialogId );
			if (
				dialog &&
				! dialog.open &&
				typeof dialog.showModal === 'function'
			) {
				dialog.showModal();
			}
		},
		dialogBackdrop( event ) {
			const { ref } = getElement();
			// The backdrop is the dialog element itself; its content is an inner wrapper.
			if ( event.target === ref ) {
				ref.close();
			}
		},
	},
} );
