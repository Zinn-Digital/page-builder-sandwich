/**
 * pbs/nav-menu view module (Interactivity API), published through the neutral path by
 * Assets\Perf\Modules. `__PREFIX__` is the store namespace and the class prefix; every name is
 * `navMenu…`.
 *
 * WAI-ARIA disclosure navigation: each item with a submenu has a button (`aria-expanded`,
 * `aria-controls`); opening one closes the others that are not its ancestors; Escape closes the
 * innermost open submenu and returns focus to its button; a click or focus outside closes them
 * all. Below the chosen width the list sits in the mobile menu opened by the Menu button. A side
 * drawer or a full-screen menu is a dialog (`role="dialog"`, `aria-modal`): focus moves into it,
 * Tab is kept inside it, Escape or the close button shuts it and focus returns to the Menu button,
 * and the page does not scroll behind it. A dropdown (`context.modal` false) is a disclosure: no
 * trap and no scroll lock, and a click or focus outside closes it. With "close on link" a click on
 * a link inside the mobile menu closes it (a same-page link would otherwise leave it open over the
 * section it scrolled to). Without this module submenus open on hover and keyboard focus (CSS),
 * and the list stays in the page.
 *
 * A mega panel whose item shows its own links on phones has TWO submenus (the panel and a
 * `__sub--mobile` list carrying `data-sub` = the panel's id): the button controls both, and the
 * open state is kept under the panel's id.
 */
import {
	store,
	getContext,
	getElement,
	withScope,
	withSyncEvent,
} from '@wordpress/interactivity';

// The class prefix: written into the copy published under uploads, or read from this
// module's own URL when it is served from the plugin folder (uploads not writable).
const PFX = '__PREFIX__'.startsWith( '__' )
	? new URL( import.meta.url ).searchParams.get( 'pfx' ) || ''
	: '__PREFIX__';

const SUB = '.' + PFX + '-nav-menu__sub';
const FOCUSABLE =
	'a[href], button:not([disabled]):not([hidden]), input:not([disabled]), select, textarea, [tabindex]:not([tabindex="-1"])';

/**
 * The ids of the submenus that contain an element (its ancestors), outermost first.
 *
 * @param {Element} el Element.
 * @return {string[]} Ids.
 */
function ancestors( el ) {
	const out = [];
	let sub = el.parentElement?.closest( SUB );
	while ( sub ) {
		out.unshift( sub.dataset.sub || sub.id );
		sub = sub.parentElement?.closest( SUB );
	}
	return out;
}

/**
 * Whether the drawer is a dialog at this width (its Menu button is showing).
 *
 * @param {Element} nav The nav element.
 * @return {boolean} Off-canvas mode.
 */
function offCanvas( nav ) {
	const toggle = nav.querySelector( '.' + PFX + '-nav-menu__toggle' );
	return !! toggle && window.getComputedStyle( toggle ).display !== 'none';
}

/**
 * The elements a submenu button controls (one, or a mega panel and its phone list).
 *
 * @param {string} ids `aria-controls` value.
 * @return {Element[]} Elements.
 */
function controlled( ids ) {
	return String( ids || '' )
		.split( /\s+/ )
		.map( ( id ) => document.getElementById( id ) )
		.filter( Boolean );
}

/**
 * Open or close the mobile menu, moving focus and (for a dialog) locking the page scroll.
 *
 * @param {Object}  ctx     Context.
 * @param {Element} nav     The nav element.
 * @param {boolean} open    Open.
 * @param {boolean} refocus On close, return focus to the Menu button.
 */
function setDrawer( ctx, nav, open, refocus = true ) {
	ctx.drawer = open;
	ctx.open = [];
	if ( ctx.modal ) {
		document.documentElement.style.overflow = open ? 'hidden' : '';
	}
	window.requestAnimationFrame( () => {
		if ( open ) {
			nav.querySelector(
				'.' + PFX + '-nav-menu__drawer .' + PFX + '-nav-menu__link'
			)?.focus();
		} else if ( refocus ) {
			nav.querySelector( '.' + PFX + '-nav-menu__toggle' )?.focus();
		}
	} );
}

const { state } = store( PFX, {
	state: {
		get navMenuSubOpen() {
			const ctx = getContext();
			return ctx.open.includes( ctx.sub );
		},
		get navMenuDialogRole() {
			const ctx = getContext();
			return ctx.drawer && ctx.modal !== false ? 'dialog' : null;
		},
		get navMenuDialogModal() {
			const ctx = getContext();
			return ctx.drawer && ctx.modal !== false ? 'true' : null;
		},
		get navMenuDialogLabel() {
			const ctx = getContext();
			return ctx.drawer && ctx.modal !== false ? ctx.dialogLabel : null;
		},
	},
	actions: {
		navMenuSub() {
			const ctx = getContext();
			const { ref } = getElement();
			if ( state.navMenuSubOpen ) {
				// Close it and anything open inside it (or inside its phone list).
				const subs = controlled( ref.getAttribute( 'aria-controls' ) );
				ctx.open = ctx.open.filter( ( id ) => {
					const el = document.getElementById( id );
					return (
						id !== ctx.sub &&
						! subs.some( ( s ) => s.contains( el ) )
					);
				} );
			} else {
				ctx.open = [ ...ancestors( ref ), ctx.sub ];
			}
		},
		navMenuDrawer() {
			const ctx = getContext();
			const { ref } = getElement();
			setDrawer( ctx, ref.closest( 'nav' ), ! ctx.drawer );
		},
	},
	callbacks: {
		navMenuInit() {
			const ctx = getContext();
			const { ref } = getElement();
			ctx.ready = true;
			// Widening past the breakpoint with the drawer open would leave the page locked.
			window.addEventListener(
				'resize',
				withScope( () => {
					if ( ctx.drawer && ! offCanvas( ref ) ) {
						ctx.drawer = false;
						if ( ctx.modal ) {
							document.documentElement.style.overflow = '';
						}
					}
				} ),
				{ passive: true }
			);
		},
		// Synchronous: it cancels Escape's and Tab's default actions.
		navMenuKey: withSyncEvent( ( event ) => {
			const ctx = getContext();
			const { ref } = getElement();
			if ( event.key === 'Escape' ) {
				if ( ctx.open.length ) {
					const last = ctx.open[ ctx.open.length - 1 ];
					ctx.open = ctx.open.slice( 0, -1 );
					ref.querySelector(
						`[aria-controls~="${ last }"]`
					)?.focus();
					event.preventDefault();
				} else if ( ctx.drawer ) {
					setDrawer( ctx, ref, false );
					event.preventDefault();
				}
				return;
			}
			if ( event.key !== 'Tab' || ! ctx.drawer || ctx.modal === false ) {
				return;
			}
			// Keep Tab inside the open dialog.
			const drawer = ref.querySelector( '.' + PFX + '-nav-menu__drawer' );
			const items = [ ...drawer.querySelectorAll( FOCUSABLE ) ].filter(
				( el ) => el.offsetParent !== null
			);
			if ( ! items.length ) {
				return;
			}
			const first = items[ 0 ];
			const last = items[ items.length - 1 ];
			const active = drawer.ownerDocument.activeElement;
			if (
				event.shiftKey &&
				( active === first || ! drawer.contains( active ) )
			) {
				last.focus();
				event.preventDefault();
			} else if (
				! event.shiftKey &&
				( active === last || ! drawer.contains( active ) )
			) {
				first.focus();
				event.preventDefault();
			}
		} ),
		navMenuOutside( event ) {
			const ctx = getContext();
			const { ref } = getElement();
			const drawer = ref.querySelector( '.' + PFX + '-nav-menu__drawer' );
			if ( ref.contains( event.target ) ) {
				// A click on the dim area around an open drawer closes it.
				if ( ctx.drawer && event.target === drawer ) {
					setDrawer( ctx, ref, false );
				} else if (
					ctx.drawer &&
					ctx.closeOnLink &&
					drawer?.contains( event.target ) &&
					event.target.closest?.( 'a[href]' )
				) {
					// The link's own navigation (or scroll) takes focus where it goes.
					setDrawer( ctx, ref, false, false );
				}
				return;
			}
			ctx.open = [];
			if ( ctx.drawer && ctx.modal === false ) {
				setDrawer( ctx, ref, false, false );
			}
		},
		navMenuFocus( event ) {
			const ctx = getContext();
			const { ref } = getElement();
			if ( ref.contains( event.target ) ) {
				return;
			}
			if ( ctx.open.length ) {
				ctx.open = [];
			}
			// A dropdown is not a dialog: Tab past its end closes it.
			if ( ctx.drawer && ctx.modal === false ) {
				setDrawer( ctx, ref, false, false );
			}
		},
	},
} );
