/**
 * Editor state of the design panel (pbs-p4): which breakpoint and state (normal / hover) is being
 * edited and previewed. One store, read by the Style tab, the canvas preview and Sandwich Studio's
 * device switcher, so all three always agree.
 */
import { createReduxStore, register } from '@wordpress/data';

export const STORE = 'pbsw/design';

/** Server data printed by Design\Editor::data(). */
export const config = () =>
	window.pbswDesign || {
		prefix: 'zd',
		breakpoints: [
			{ id: 'tablet', label: 'Tablet', max: 1024 },
			{ id: 'mobile', label: 'Mobile', max: 767 },
		],
		tokens: {},
		customAllowed: false,
		elementCss: false,
	};

const DEFAULT = { breakpoint: 'base', hover: false };

export const store = createReduxStore( STORE, {
	reducer( state = DEFAULT, action ) {
		switch ( action.type ) {
			case 'SET_BREAKPOINT':
				return state.breakpoint === action.breakpoint
					? state
					: { ...state, breakpoint: action.breakpoint };
			case 'SET_HOVER':
				return state.hover === action.hover
					? state
					: { ...state, hover: action.hover };
		}
		return state;
	},
	actions: {
		setBreakpoint: ( breakpoint ) => ( {
			type: 'SET_BREAKPOINT',
			breakpoint,
		} ),
		setHover: ( hover ) => ( { type: 'SET_HOVER', hover: !! hover } ),
	},
	selectors: {
		getBreakpoint: ( state ) => state.breakpoint,
		isHover: ( state ) => state.hover,
	},
} );

// The design bundle and the blocks bundle both include this module; the first to run registers
// the store, and everything addresses it by name, so the two copies share one state.
if ( ! window.pbswDesignStore ) {
	window.pbswDesignStore = true;
	register( store );
}
