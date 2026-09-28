/**
 * pbs/code view module (Interactivity API), published through the neutral path by
 * Assets\Perf\Modules. `__PREFIX__` is the store namespace.
 *
 * - Highlighting: highlight.js (bundled, published through the neutral path, loaded only on pages
 *   with a code block) colours the code the server already printed escaped. Its classes are
 *   neutral (`__PREFIX__-hl-…`), and without script the code simply stays plain.
 * - Copy: the button is rendered `hidden` and only shown once this module runs.
 */
import { store, getContext, getElement } from '@wordpress/interactivity';

/**
 * highlight.js, once its (deferred) script has run.
 *
 * @return {Promise<Object|null>} The library, or null when it never arrives.
 */
function library() {
	if ( window.hljs ) {
		return Promise.resolve( window.hljs );
	}
	return new Promise( ( resolve ) => {
		const done = () => resolve( window.hljs || null );
		if ( document.readyState === 'complete' ) {
			done();
		} else {
			window.addEventListener( 'load', done, { once: true } );
		}
	} );
}

/**
 * Copy text: the Clipboard API where the page is a secure context, else a selected text area and
 * the copy command (a site still served over http).
 *
 * @param {string} text Text.
 * @return {Promise<void>} Done.
 */
function copyText( text ) {
	if ( navigator.clipboard ) {
		return navigator.clipboard.writeText( text );
	}
	const area = document.createElement( 'textarea' );
	area.value = text;
	area.setAttribute( 'readonly', '' );
	area.style.position = 'fixed';
	area.style.opacity = '0';
	document.body.appendChild( area );
	area.select();
	const ok = document.execCommand( 'copy' );
	area.remove();
	return ok ? Promise.resolve() : Promise.reject( new Error( 'copy' ) );
}

store( '__PREFIX__', {
	actions: {
		*codeCopy() {
			const { ref } = getElement();
			const code = ref.closest( 'figure' ).querySelector( 'code' );
			try {
				yield copyText( code.textContent );
			} catch {
				return; // Nothing was copied; the code is still there to select by hand.
			}
			const context = getContext();
			context.copied = true;
			yield new Promise( ( r ) => setTimeout( r, 2000 ) );
			context.copied = false;
		},
	},
	callbacks: {
		codeReady() {
			const context = getContext();
			context.ready = true;
			const { ref } = getElement();
			const code = ref.querySelector( 'code[data-lang]' );
			if ( ! code ) {
				return;
			}
			library().then( ( hljs ) => {
				if ( ! hljs ) {
					return;
				}
				hljs.configure( { classPrefix: '__PREFIX__-hl-' } );
				const lang = code.dataset.lang;
				const text = code.textContent;
				const result =
					lang === 'auto' || ! hljs.getLanguage( lang )
						? hljs.highlightAuto( text )
						: hljs.highlight( text, {
								language: lang,
								ignoreIllegals: true,
							} );
				// highlight.js escapes the text it returns; nothing else is inserted.
				code.innerHTML = result.value;
			} );
		},
	},
} );
