/**
 * Which props the Style tab shows for a block (pure, tested): flex controls only when the block
 * lays out as flexbox, grid controls only for a grid, child controls only when the PARENT is a
 * flex or grid container, offsets only for a positioned block.
 */
import { appliesTo } from '../kinds';
import { cascade } from '../breakpoints';

const FLEX = [ 'flex', 'inline-flex' ];
const GRID = [ 'grid', 'inline-grid' ];

/** Blocks that lay out their children with flexbox unless told otherwise. */
export const FLEX_BY_DEFAULT = [ 'pbs/section', 'pbs/container' ];

/**
 * The value that applies at a breakpoint: its own, else the nearest wider one's.
 *
 * @param {Object}        s    `pbs.s`.
 * @param {string}        key  Prop key.
 * @param {string}        bp   Breakpoint.
 * @param {Array<Object>} list Media list.
 * @return {unknown} Value or undefined.
 */
export function effective( s, key, bp, list ) {
	const chain = cascade( bp, list );
	for ( let i = chain.length - 1; i >= 0; i-- ) {
		const v = s?.[ chain[ i ] ]?.[ key ];
		if ( v !== undefined ) {
			return v;
		}
	}
	return undefined;
}

/**
 * How a block lays out its children at a breakpoint: 'flex', 'grid' or 'block'.
 *
 * @param {string}        name       Block name.
 * @param {Object}        attributes Block attributes.
 * @param {string}        bp         Breakpoint.
 * @param {Array<Object>} list       Media list.
 * @return {string} Mode.
 */
export function layoutMode( name, attributes, bp, list ) {
	const display = effective( attributes?.pbs?.s, 'display', bp, list );
	if ( FLEX.includes( display ) ) {
		return 'flex';
	}
	if ( GRID.includes( display ) ) {
		return 'grid';
	}
	if ( display !== undefined ) {
		return 'block';
	}
	if ( FLEX_BY_DEFAULT.includes( name ) ) {
		return 'flex';
	}
	// Core's own layout support (group, row, stack, grid variations).
	const type = attributes?.layout?.type;
	if ( type === 'flex' ) {
		return 'flex';
	}
	if ( type === 'grid' ) {
		return 'grid';
	}
	return 'block';
}

/** Props shown only for a flex container, a grid container, or either. */
const ONLY = {
	flexDirection: [ 'flex' ],
	flexWrap: [ 'flex' ],
	justifyContent: [ 'flex', 'grid' ],
	alignItems: [ 'flex', 'grid' ],
	gap: [ 'flex', 'grid' ],
	gridColumns: [ 'grid' ],
	gridRows: [ 'grid' ],
	gridAreas: [ 'grid' ],
	placeItems: [ 'grid' ],
};

/** Props shown only when the PARENT is a flex or grid container. */
const CHILD = {
	alignSelf: [ 'flex', 'grid' ],
	order: [ 'flex', 'grid' ],
	flexGrow: [ 'flex' ],
	flexShrink: [ 'flex' ],
	flexBasis: [ 'flex' ],
	colSpan: [ 'grid' ],
	rowSpan: [ 'grid' ],
	gridArea: [ 'grid' ],
};

/**
 * The props to show.
 *
 * @param {Array<Object>} props Registry.
 * @param {Object}        ctx   { name, attributes, parentMode, mode, position }.
 * @return {Array<Object>} Visible props, registry order.
 */
export function visibleProps( props, ctx ) {
	return props.filter( ( p ) => {
		if ( ! appliesTo( p, ctx.name ) ) {
			return false;
		}
		if ( ONLY[ p.key ] && ! ONLY[ p.key ].includes( ctx.mode ) ) {
			return false;
		}
		if ( CHILD[ p.key ] && ! CHILD[ p.key ].includes( ctx.parentMode ) ) {
			return false;
		}
		if (
			p.key === 'contentWidth' &&
			( ctx.attributes?.width ??
				( ctx.name === 'pbs/section' ? 'boxed' : 'full' ) ) !== 'boxed'
		) {
			return false;
		}
		if (
			p.key === 'inset' &&
			( ! ctx.position || ctx.position === 'static' )
		) {
			return false;
		}
		return true;
	} );
}
