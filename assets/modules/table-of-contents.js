/**
 * pbs/table-of-contents view module (Interactivity API): scroll-spy. The link of the section the
 * visitor is reading gets `aria-current="true"` (and the matching style): the last heading that
 * has scrolled past the upper third of the window, or the last heading once the page's end is
 * reached (a short final section never gets to the top). One passive scroll listener, at most
 * one update per frame. `__PREFIX__` is the store namespace.
 */
import { store, getElement } from '@wordpress/interactivity';

store( '__PREFIX__', {
	callbacks: {
		tocSpy() {
			const { ref } = getElement();
			const links = [ ...ref.querySelectorAll( 'a[href^="#"]' ) ];
			const pairs = links
				.map( ( a ) => [
					a,
					document.getElementById(
						decodeURIComponent( a.hash.slice( 1 ) )
					),
				] )
				.filter( ( [ , target ] ) => target );
			if ( ! pairs.length ) {
				return;
			}
			let queued = false;
			const mark = () => {
				queued = false;
				const line = window.innerHeight / 3;
				const doc = document.documentElement;
				const atEnd =
					window.scrollY + window.innerHeight >= doc.scrollHeight - 2;
				let current = null;
				for ( const pair of pairs ) {
					if ( pair[ 1 ].getBoundingClientRect().top <= line ) {
						current = pair;
					}
				}
				if ( atEnd && window.scrollY > 0 ) {
					current = pairs[ pairs.length - 1 ];
				}
				for ( const [ a ] of pairs ) {
					if ( current && a === current[ 0 ] ) {
						a.setAttribute( 'aria-current', 'true' );
					} else {
						a.removeAttribute( 'aria-current' );
					}
				}
			};
			const onScroll = () => {
				if ( ! queued ) {
					queued = true;
					window.requestAnimationFrame( mark );
				}
			};
			mark();
			window.addEventListener( 'scroll', onScroll, { passive: true } );
			window.addEventListener( 'resize', onScroll, { passive: true } );
			return () => {
				window.removeEventListener( 'scroll', onScroll );
				window.removeEventListener( 'resize', onScroll );
			};
		},
	},
} );
