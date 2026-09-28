/**
 * pbs/booking view module (Interactivity API), published through the neutral path by
 * Assets\Perf\Modules. `__PREFIX__` is the store namespace.
 *
 * Click-to-load: the frame gets its `src` only when the visitor presses the button, so nothing
 * is requested from the third party before that. The button is rendered `hidden` and shown once
 * this module runs (without script, the plain link next to it still works).
 */
import { store, getContext, getElement } from '@wordpress/interactivity';

store( '__PREFIX__', {
	actions: {
		bookingLoad() {
			const context = getContext();
			context.src = context.url;
			// Move focus to the frame that replaced the button, so keyboard users are not lost.
			const { ref } = getElement();
			const frame = ref?.closest( 'figure' )?.querySelector( 'iframe' );
			window.requestAnimationFrame( () => frame?.focus() );
		},
	},
	callbacks: {
		bookingReady() {
			getContext().ready = true;
		},
	},
} );
