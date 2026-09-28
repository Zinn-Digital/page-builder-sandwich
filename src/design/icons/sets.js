/**
 * Icon set loading and search for the IconPicker.
 *
 * ⭐ Lazy, in two steps. A set's INDEX (assets/icons/<set>.json: names, search words, which styles
 * each icon has) is fetched the first time its tab opens, or when the author turns on "Search
 * all sets". Its DRAWINGS (assets/icons/<set>/<style>-<n>.json) are fetched only for the style on
 * screen. Both are cached for the page. The front end never loads any of it: a picked icon is
 * stored in the block as inline SVG. Files are built by wp/bin/pbs-icons-build.php.
 */

/** Sets the picker offers, in tab order. Labels are proper names (not translated). */
export const SETS = [
	{ id: 'fa', label: 'Font Awesome' },
	{ id: 'lucide', label: 'Lucide' },
	{ id: 'material', label: 'Material Symbols' },
	{ id: 'phosphor', label: 'Phosphor' },
];

const cache = new Map();
const styleCache = new Map();

/**
 * Where the set files live. The server prints window.pbswIcons.base in the editor only.
 *
 * @return {string} Base URL ending in '/'.
 */
export function baseUrl() {
	const data = ( typeof window !== 'undefined' && window.pbswIcons ) || {};
	return data.base || '';
}

const defaultFetch = ( ...args ) => window.fetch( ...args );

function getJson( url, fetcher ) {
	return fetcher( url, { credentials: 'same-origin' } ).then( ( res ) => {
		if ( ! res.ok ) {
			throw new Error( `HTTP ${ res.status }` );
		}
		return res.json();
	} );
}

/**
 * Load a set's index once; later calls return the same promise.
 *
 * @param {string}   id      Set id.
 * @param {Function} fetcher fetch() implementation (tests inject one).
 * @return {Promise<Object>} The indexed set.
 */
export function loadSet( id, fetcher = defaultFetch ) {
	if ( ! SETS.some( ( s ) => s.id === id ) ) {
		return Promise.reject( new Error( `unknown icon set ${ id }` ) );
	}
	if ( ! cache.has( id ) ) {
		const promise = getJson( `${ baseUrl() }${ id }.json`, fetcher )
			.then( indexSet )
			.catch( ( err ) => {
				cache.delete( id );
				throw err;
			} );
		cache.set( id, promise );
	}
	return cache.get( id );
}

/**
 * Load the drawings of one style of a loaded set, once. Resolves when every entry that has the
 * style carries its body.
 *
 * @param {Object}   set     Indexed set (from loadSet).
 * @param {string}   style   Style id.
 * @param {Function} fetcher fetch() implementation.
 * @return {Promise<Object>} The same set.
 */
export function loadStyle( set, style, fetcher = defaultFetch ) {
	if ( ! set.styles.includes( style ) ) {
		return Promise.reject( new Error( `unknown style ${ style }` ) );
	}
	const key = `${ set.id }/${ style }`;
	if ( ! styleCache.has( key ) ) {
		const count = ( set.chunks && set.chunks[ style ] ) || 0;
		const files = [];
		for ( let n = 0; n < count; n++ ) {
			files.push(
				getJson(
					`${ baseUrl() }${ set.id }/${ style }-${ n }.json`,
					fetcher
				)
			);
		}
		const promise = Promise.all( files )
			.then( ( parts ) => {
				const byName = set.byName;
				for ( const part of parts ) {
					for ( const [ name, body ] of Object.entries( part ) ) {
						if ( byName[ name ] ) {
							byName[ name ].bodies[ style ] = body;
						}
					}
				}
				return set;
			} )
			.catch( ( err ) => {
				styleCache.delete( key );
				throw err;
			} );
		styleCache.set( key, promise );
	}
	return styleCache.get( key );
}

/** Forget every loaded set (tests). */
export function resetSets() {
	cache.clear();
	styleCache.clear();
}

/**
 * The index file as picker entries, each with a lower-case search haystack.
 *
 * @param {Object} set Parsed index file.
 * @return {Object} Set with `entries` and `byName`.
 */
export function indexSet( set ) {
	const byName = {};
	const entries = ( set.icons || [] ).map( ( [ name, words, has ] ) => {
		const entry = {
			set: set.id,
			name,
			words: words || '',
			styles: ( has || [] ).map( ( i ) => set.styles[ i ] ),
			bodies: {},
			hay: `${ name } ${ name.replace( /[-_]/g, ' ' ) } ${ words || '' }`,
		};
		byName[ name ] = entry;
		return entry;
	} );
	return { ...set, entries, byName };
}

/**
 * The styles a set must have loaded to show each icon in its own first style (used when
 * searching every set, where no single style applies).
 *
 * @param {Object} set Indexed set.
 * @return {string[]} Style ids.
 */
export function firstStyles( set ) {
	return [
		...new Set(
			set.entries.map( ( e ) => e.styles[ 0 ] ).filter( Boolean )
		),
	];
}

/**
 * Split a query into lower-case terms.
 *
 * @param {string} query Query.
 * @return {string[]} Terms.
 */
export function terms( query ) {
	return String( query || '' )
		.toLowerCase()
		.split( /[\s,]+/ )
		.filter( Boolean );
}

/**
 * Score one entry against the terms; 0 = no match. Name hits outrank keyword hits.
 *
 * @param {Object}   entry Entry.
 * @param {string[]} words Query terms.
 * @return {number} Score.
 */
export function score( entry, words ) {
	if ( ! words.length ) {
		return 1;
	}
	let total = 0;
	for ( const w of words ) {
		if ( ! entry.hay.includes( w ) ) {
			return 0;
		}
	}
	const name = entry.name.toLowerCase();
	for ( const w of words ) {
		total += name.includes( w ) ? 3 : 1;
	}
	if ( name === words.join( '-' ) || name === words.join( '_' ) ) {
		total += 100;
	} else if ( name.startsWith( words[ 0 ] ) ) {
		total += 10;
	}
	return total;
}

/**
 * Search entries (from one or more sets). Stable: equal scores keep the given order.
 *
 * @param {Object[]} entries Entries.
 * @param {string}   query   Query.
 * @param {string}   style   Only entries that have this style ('' = any).
 * @return {Object[]} Matching entries, best first.
 */
export function search( entries, query, style = '' ) {
	const words = terms( query );
	const hits = [];
	entries.forEach( ( entry, i ) => {
		if ( style && ! entry.styles.includes( style ) ) {
			return;
		}
		const s = score( entry, words );
		if ( s > 0 ) {
			hits.push( { entry, s, i } );
		}
	} );
	hits.sort( ( a, b ) => b.s - a.s || a.i - b.i );
	return hits.map( ( h ) => h.entry );
}
