/**
 * The arithmetic of the visual grid editor (pbs-d1), kept pure so it is tested without a DOM.
 */
import { num } from '../../design/values';

/**
 * Track sizes in px from a computed `grid-template-columns`/`-rows` value ("200px 300.5px").
 *
 * @param {string} computed Computed value.
 * @return {number[]} Sizes.
 */
export function parseTracks( computed ) {
	return String( computed || '' )
		.split( /\s+/ )
		.map( ( t ) => parseFloat( t ) )
		.filter( ( n ) => Number.isFinite( n ) );
}

/**
 * Where each track starts, from the content-box start, with a gap between tracks.
 *
 * @param {number[]} sizes Track sizes.
 * @param {number}   gap   Gap.
 * @return {number[]} Start offsets.
 */
export function offsets( sizes, gap ) {
	const out = [];
	let at = 0;
	for ( const s of sizes ) {
		out.push( at );
		at += s + gap;
	}
	return out;
}

/**
 * Drag the boundary after track `i` by `delta` px: track i grows, track i+1 shrinks, neither below
 * `min`. The result is written as fr units that sum to the track count, so the grid keeps its
 * proportions at every width.
 *
 * @param {number[]} sizes Track sizes (px).
 * @param {number}   i     Boundary index (between i and i+1).
 * @param {number}   delta Movement along the inline axis, logical (+ = towards the end).
 * @param {number}   min   Smallest track.
 * @return {string} A track list such as "1.2fr 0.8fr 1fr".
 */
export function resizeColumns( sizes, i, delta, min = 24 ) {
	const next = [ ...sizes ];
	const pair = next[ i ] + next[ i + 1 ];
	next[ i ] = Math.min( Math.max( next[ i ] + delta, min ), pair - min );
	next[ i + 1 ] = pair - next[ i ];
	return toFr( next );
}

/**
 * Sizes as fr units summing to the number of tracks.
 *
 * @param {number[]} sizes Sizes.
 * @return {string} Track list.
 */
export function toFr( sizes ) {
	const total = sizes.reduce( ( a, b ) => a + b, 0 ) || 1;
	return sizes
		.map(
			( s ) =>
				`${ num( Math.round( ( s / total ) * sizes.length * 100 ) / 100 ) }fr`
		)
		.join( ' ' );
}

/**
 * Drag the end of row `i` by `delta` px: that row's minimum height changes; every row keeps
 * `auto` as its maximum so content never overflows.
 *
 * @param {number[]} sizes Row sizes (px).
 * @param {number}   i     Row index.
 * @param {number}   delta Movement along the block axis.
 * @param {number}   min   Smallest row.
 * @return {string} A track list such as "minmax(120px,auto) minmax(80px,auto)".
 */
export function resizeRows( sizes, i, delta, min = 24 ) {
	return sizes
		.map(
			( s, k ) =>
				`minmax(${ Math.round(
					k === i ? Math.max( s + delta, min ) : s
				) }px,auto)`
		)
		.join( ' ' );
}

/**
 * The track under a logical coordinate.
 *
 * @param {number[]} starts Track start offsets.
 * @param {number[]} sizes  Track sizes.
 * @param {number}   at     Coordinate.
 * @return {number} Track index (clamped).
 */
export function trackAt( starts, sizes, at ) {
	for ( let i = starts.length - 1; i >= 0; i-- ) {
		if ( at >= starts[ i ] ) {
			return i;
		}
	}
	return 0;
}

/**
 * The span a child gets when its corner is dragged to `at`.
 *
 * @param {number[]} starts Track starts.
 * @param {number[]} sizes  Track sizes.
 * @param {number}   first  The child's first track.
 * @param {number}   at     Pointer coordinate (logical).
 * @return {number} Span, at least 1.
 */
export function spanTo( starts, sizes, first, at ) {
	return Math.max( 1, trackAt( starts, sizes, at ) - first + 1 );
}

/**
 * The value for one more or one fewer column.
 *
 * @param {unknown}  current Stored gridColumns value.
 * @param {number[]} sizes   Current track sizes.
 * @param {number}   step    +1 or -1.
 * @return {number|string|undefined} New value (undefined = back to one column).
 */
export function stepColumns( current, sizes, step ) {
	const n = sizes.length || 1;
	const counted =
		current === undefined ||
		typeof current === 'number' ||
		/^repeat\(\d{1,2},minmax\(0,1fr\)\)$/.test( String( current ) ) ||
		/^\d{1,2}$/.test( String( current ) );
	if ( counted ) {
		const next = Math.min( Math.max( n + step, 1 ), 24 );
		return next;
	}
	if ( step > 0 ) {
		return `${ toFr( sizes ) } 1fr`;
	}
	return sizes.length > 1 ? toFr( sizes.slice( 0, -1 ) ) : undefined;
}
