/**
 * pbs/reading-progress view module (Interactivity API): how far down the page the visitor is,
 * written as a 0–1 custom property the stylesheet scales the bar by. One passive scroll listener,
 * at most one update per frame. `__PREFIX__` is the store namespace (and the property's prefix).
 */
import { store, getElement } from '@wordpress/interactivity';

store( '__PREFIX__', {
	callbacks: {
		readingInit() {
			const { ref } = getElement();
			let queued = false;
			const update = () => {
				queued = false;
				const doc = document.documentElement;
				const room = doc.scrollHeight - window.innerHeight;
				const p =
					room > 0
						? Math.min( 1, Math.max( 0, window.scrollY / room ) )
						: 1;
				ref.style.setProperty( '--__PREFIX__-reading', String( p ) );
			};
			const onScroll = () => {
				if ( ! queued ) {
					queued = true;
					window.requestAnimationFrame( update );
				}
			};
			update();
			window.addEventListener( 'scroll', onScroll, { passive: true } );
			window.addEventListener( 'resize', onScroll, { passive: true } );
			return () => {
				window.removeEventListener( 'scroll', onScroll );
				window.removeEventListener( 'resize', onScroll );
			};
		},
	},
} );
