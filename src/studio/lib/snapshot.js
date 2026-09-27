/**
 * Crash recovery (feature pbs-r16): a local copy of unsaved work, written every few seconds while
 * there are unsaved changes and cleared on a successful save.
 *
 * ⭐ WHY LOCAL AND SERVER BOTH. Server autosaves (core's /autosaves endpoint, every ~20 s) survive
 * a lost laptop; a local snapshot survives the tab being killed between two autosaves, a network
 * outage and a login that expired — the cases where the server copy is up to 20 s old or was never
 * written. On open, the NEWEST of the two that differs from the saved post is offered.
 *
 * Storage is `localStorage`, keyed by site AND post, so two sites on one browser (or two posts)
 * never offer each other's work.
 */

/** Key prefix. */
export const PREFIX = 'pbsw-studio-snapshot:';

/**
 * Storage key for a post on a site.
 *
 * @param {string}        site   Home URL.
 * @param {number|string} postId Post ID.
 * @return {string} Key.
 */
export function snapshotKey( site, postId ) {
	return `${ PREFIX }${ String( site ).replace( /\/+$/, '' ) }#${ postId }`;
}

/**
 * The storage to use, or null when the browser refuses it (private mode, blocked site data).
 *
 * @return {Storage|null} Storage.
 */
export function defaultStorage() {
	try {
		const s = window.localStorage;
		const probe = PREFIX + 'probe';
		s.setItem( probe, '1' );
		s.removeItem( probe );
		return s;
	} catch {
		return null;
	}
}

/**
 * Write a snapshot.
 *
 * @param {Storage|null} storage Storage.
 * @param {string}       key     Key.
 * @param {Object}       data    { title, content, time }.
 * @return {boolean} Written.
 */
export function writeSnapshot( storage, key, data ) {
	if ( ! storage ) {
		return false;
	}
	try {
		storage.setItem(
			key,
			JSON.stringify( {
				v: 1,
				title: String( data.title ?? '' ),
				content: String( data.content ?? '' ),
				time: Number( data.time ?? Date.now() ),
			} )
		);
		return true;
	} catch {
		return false;
	}
}

/**
 * Read a snapshot.
 *
 * @param {Storage|null} storage Storage.
 * @param {string}       key     Key.
 * @return {{title:string,content:string,time:number}|null} Snapshot.
 */
export function readSnapshot( storage, key ) {
	if ( ! storage ) {
		return null;
	}
	try {
		const data = JSON.parse( storage.getItem( key ) || 'null' );
		if (
			! data ||
			data.v !== 1 ||
			typeof data.content !== 'string' ||
			typeof data.time !== 'number'
		) {
			return null;
		}
		return {
			title: String( data.title ?? '' ),
			content: data.content,
			time: data.time,
		};
	} catch {
		return null;
	}
}

/**
 * Remove a snapshot.
 *
 * @param {Storage|null} storage Storage.
 * @param {string}       key     Key.
 * @return {void}
 */
export function clearSnapshot( storage, key ) {
	try {
		storage?.removeItem( key );
	} catch {
		// Nothing to clear when storage is unavailable.
	}
}

/**
 * A REST date (`modified_gmt`, no zone) as epoch milliseconds.
 *
 * @param {string|undefined} gmt Date.
 * @return {number} Milliseconds, 0 when absent.
 */
export function gmtToMs( gmt ) {
	if ( ! gmt ) {
		return 0;
	}
	const ms = Date.parse( /Z|[+-]\d\d:?\d\d$/.test( gmt ) ? gmt : gmt + 'Z' );
	return Number.isNaN( ms ) ? 0 : ms;
}

/**
 * Decide what to offer on open.
 *
 * @param {Object}      args
 * @param {Object|null} args.snapshot The local snapshot.
 * @param {Object}      args.saved    { title, content, modifiedGmt } of the saved post.
 * @param {Object|null} args.autosave { title, content, modifiedGmt } of the server autosave.
 * @return {{source:'local'|'autosave',title:string,content:string,time:number}|null} Offer.
 */
export function recoveryOffer( { snapshot, saved, autosave } ) {
	const savedTime = gmtToMs( saved?.modifiedGmt );
	const differs = ( c ) =>
		c.content !== ( saved?.content ?? '' ) ||
		c.title !== ( saved?.title ?? '' );
	const candidates = [];
	if ( snapshot && snapshot.time > savedTime && differs( snapshot ) ) {
		candidates.push( { source: 'local', ...snapshot } );
	}
	if ( autosave ) {
		const a = {
			title: String( autosave.title ?? '' ),
			content: String( autosave.content ?? '' ),
			time: gmtToMs( autosave.modifiedGmt ),
		};
		if ( a.time > savedTime && differs( a ) ) {
			candidates.push( { source: 'autosave', ...a } );
		}
	}
	candidates.sort( ( x, y ) => y.time - x.time );
	return candidates[ 0 ] || null;
}
