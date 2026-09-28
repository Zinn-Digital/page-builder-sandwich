/**
 * pbs/tabs view module (Interactivity API): the WAI-ARIA Tabs pattern, automatic activation.
 * The server rendered the initial state (ids, aria-selected, tabindex, hidden panels); this only
 * moves the selection. Arrow keys follow the reading direction (mirrored in right-to-left),
 * Up/Down on vertical tabs, Home/End jump to the ends. `__PREFIX__` is the store namespace.
 */
import { store, getContext, getElement } from '@wordpress/interactivity';

store( '__PREFIX__', {
	state: {
		get tabsSelected() {
			const ctx = getContext();
			return ctx.tabsSel === ctx.tabsI;
		},
		get tabsTabindex() {
			const ctx = getContext();
			return ctx.tabsSel === ctx.tabsI ? 0 : -1;
		},
	},
	actions: {
		tabsSelect() {
			const ctx = getContext();
			ctx.tabsSel = ctx.tabsI;
		},
		tabsKey( event ) {
			const ctx = getContext();
			const { ref } = getElement();
			const list = ref.closest( '[role="tablist"]' );
			const vertical =
				list?.getAttribute( 'aria-orientation' ) === 'vertical';
			const rtl = getComputedStyle( ref ).direction === 'rtl';
			const last = ctx.tabsCount - 1;
			let next = null;
			switch ( event.key ) {
				case 'ArrowRight':
					next = vertical ? null : ctx.tabsI + ( rtl ? -1 : 1 );
					break;
				case 'ArrowLeft':
					next = vertical ? null : ctx.tabsI + ( rtl ? 1 : -1 );
					break;
				case 'ArrowDown':
					next = vertical ? ctx.tabsI + 1 : null;
					break;
				case 'ArrowUp':
					next = vertical ? ctx.tabsI - 1 : null;
					break;
				case 'Home':
					next = 0;
					break;
				case 'End':
					next = last;
					break;
			}
			if ( null === next ) {
				return;
			}
			event.preventDefault();
			if ( next < 0 ) {
				next = last;
			} else if ( next > last ) {
				next = 0;
			}
			ctx.tabsSel = next;
			list?.querySelectorAll( '[role="tab"]' )[ next ]?.focus();
		},
	},
} );
