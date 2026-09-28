/**
 * Breakpoints in the editor (pbs-d2, pbs-d3). Twin of includes/design/class-breakpoints.php.
 * The server prints them in output order as `window.pbswDesign.breakpoints`
 * (Breakpoints::media_list()).
 */

/**
 * The media list for an option value, in output order: `min` entries ascending, then `max`
 * entries descending.
 *
 * @param {Object} config Option value {tablet, mobile, custom}.
 * @return {Array<Object>} Breakpoints.
 */
export function mediaList( config ) {
	const mins = [];
	const maxes = [
		{ id: 'tablet', label: 'Tablet', max: Number( config.tablet ) },
		{ id: 'mobile', label: 'Mobile', max: Number( config.mobile ) },
	];
	for ( const c of config.custom || [] ) {
		( c.min !== undefined ? mins : maxes ).push( c );
	}
	mins.sort( ( a, b ) => a.min - b.min );
	maxes.sort( ( a, b ) => b.max - a.max );
	return [ ...mins, ...maxes ];
}

/**
 * The media query that applies a breakpoint's styles.
 *
 * @param {Object} bp Breakpoint.
 * @return {string} Query.
 */
export const media = ( bp ) =>
	bp.min !== undefined
		? `(min-width:${ bp.min }px)`
		: `(max-width:${ bp.max }px)`;

/**
 * The media query covering only one breakpoint's own range, '' for every width, null if unknown.
 *
 * @param {string}        id   Breakpoint id (base included).
 * @param {Array<Object>} list mediaList() output.
 * @return {string|null} Query.
 */
export function range( id, list ) {
	const maxes = list
		.filter( ( b ) => b.max !== undefined )
		.map( ( b ) => b.max )
		.sort( ( a, b ) => a - b );
	const mins = list
		.filter( ( b ) => b.min !== undefined )
		.map( ( b ) => b.min )
		.sort( ( a, b ) => a - b );
	let lo = null;
	let hi = null;
	if ( id === 'base' ) {
		lo = maxes.length ? maxes[ maxes.length - 1 ] + 1 : null;
		hi = mins.length ? mins[ 0 ] - 1 : null;
	} else {
		const bp = list.find( ( b ) => b.id === id );
		if ( ! bp ) {
			return null;
		}
		if ( bp.max !== undefined ) {
			hi = bp.max;
			for ( const m of maxes ) {
				if ( m < hi ) {
					lo = m + 1;
				}
			}
		} else {
			lo = bp.min;
			for ( const m of [ ...mins ].reverse() ) {
				if ( m > lo ) {
					hi = m - 1;
				}
			}
		}
	}
	const parts = [];
	if ( lo !== null ) {
		parts.push( `(min-width:${ lo }px)` );
	}
	if ( hi !== null ) {
		parts.push( `(max-width:${ hi }px)` );
	}
	return parts.join( ' and ' );
}

/**
 * The breakpoints whose rules apply when previewing `id` (base first, then each wider one down to
 * `id`), in cascade order — what a browser would apply at that width, without media queries.
 *
 * @param {string}        id   Breakpoint being previewed.
 * @param {Array<Object>} list mediaList() output.
 * @return {string[]} Ids in cascade order.
 */
export function cascade( id, list ) {
	const bp = list.find( ( b ) => b.id === id );
	if ( ! bp ) {
		return [ 'base' ];
	}
	if ( bp.min !== undefined ) {
		return [
			'base',
			...list
				.filter( ( b ) => b.min !== undefined && b.min <= bp.min )
				.map( ( b ) => b.id ),
		];
	}
	return [
		'base',
		...list
			.filter( ( b ) => b.max !== undefined && b.max >= bp.max )
			.map( ( b ) => b.id ),
	];
}

/**
 * The breakpoint a value is inherited from when previewing `id`: the nearest wider breakpoint that
 * sets it, or null.
 *
 * @param {Object}        s     `pbs.s`.
 * @param {string}        key   Prop key.
 * @param {string}        id    Breakpoint being edited.
 * @param {Array<Object>} list  mediaList() output.
 * @param {string}        state '' or ':hover'.
 * @return {{from:string,value:unknown}|null} Inherited value.
 */
export function inherited( s, key, id, list, state = '' ) {
	const chain = cascade( id, list ).filter( ( b ) => b !== id );
	for ( let i = chain.length - 1; i >= 0; i-- ) {
		const values = s?.[ chain[ i ] + state ];
		if ( values && values[ key ] !== undefined ) {
			return { from: chain[ i ], value: values[ key ] };
		}
	}
	if ( state ) {
		// A hover value falls back to the normal value of the same breakpoint, then wider ones.
		const own = s?.[ id ]?.[ key ];
		if ( own !== undefined ) {
			return { from: id, value: own };
		}
		return inherited( s, key, id, list, '' );
	}
	return null;
}
