/**
 * Element ids (pbs-p4): 8 characters [a-z0-9], unique within a post. A block gets one the first
 * time a design value is written to it (and a section/container when inserted); a duplicated or
 * pasted copy gets a new one, so editing the copy never restyles the original.
 */
const ALPHABET = 'abcdefghijklmnopqrstuvwxyz0123456789';

/**
 * A new id not in `taken`.
 *
 * @param {Set<string>}  taken  Ids in use.
 * @param {() => number} random Random source (tests).
 * @return {string} Id.
 */
export function newId( taken = new Set(), random = Math.random ) {
	for (;;) {
		let id = '';
		for ( let i = 0; i < 8; i++ ) {
			id += ALPHABET[ Math.floor( random() * ALPHABET.length ) ];
		}
		if ( ! taken.has( id ) ) {
			return id;
		}
	}
}

/**
 * Walk editor blocks depth-first, in document order.
 *
 * @param {Array<Object>}           blocks Blocks {clientId, attributes, innerBlocks}.
 * @param {(block: Object) => void} fn     Visitor.
 */
export function walk( blocks, fn ) {
	for ( const block of blocks || [] ) {
		fn( block );
		walk( block.innerBlocks, fn );
	}
}

/**
 * Which blocks must get a new id: every block after the first carrying an id already seen. The
 * block that OWNED an id before this change keeps it (a paste above the original must re-id the
 * paste, not the original), which is what `owners` records between calls.
 *
 * @param {Array<Object>}       blocks Block tree.
 * @param {Map<string, string>} owners id → clientId that owns it (updated in place).
 * @param {() => number}        random Random source (tests).
 * @return {Array<{clientId:string,id:string}>} Re-assignments.
 */
export function dedupe( blocks, owners, random = Math.random ) {
	const byId = new Map();
	const taken = new Set();
	const missing = [];
	walk( blocks, ( b ) => {
		const pbs = b.attributes?.pbs;
		const id = pbs?.id;
		// Styled but no id yet: a block inserted from a variation or a pattern that carries preset
		// design values (the compiler ignores a block without an id).
		if ( ! id && pbs && ( pbs.s || pbs.hide || pbs.cls || pbs.css ) ) {
			missing.push( b.clientId );
		}
		if ( typeof id === 'string' && id ) {
			taken.add( id );
			if ( ! byId.has( id ) ) {
				byId.set( id, [] );
			}
			byId.get( id ).push( b.clientId );
		}
	} );
	const out = [];
	for ( const [ id, clients ] of byId ) {
		const owner = clients.includes( owners.get( id ) )
			? owners.get( id )
			: clients[ 0 ];
		owners.set( id, owner );
		for ( const clientId of clients ) {
			if ( clientId !== owner ) {
				const fresh = newId( taken, random );
				taken.add( fresh );
				owners.set( fresh, clientId );
				out.push( { clientId, id: fresh } );
			}
		}
	}
	for ( const clientId of missing ) {
		const fresh = newId( taken, random );
		taken.add( fresh );
		owners.set( fresh, clientId );
		out.push( { clientId, id: fresh } );
	}
	for ( const id of [ ...owners.keys() ] ) {
		if ( ! byId.has( id ) && ! out.some( ( r ) => r.id === id ) ) {
			owners.delete( id );
		}
	}
	return out;
}
