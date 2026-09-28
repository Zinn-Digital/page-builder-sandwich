/**
 * The editor's twin of Blocks\Site\Toc::headings()/slug(): the headings of the post being edited,
 * their anchors computed the same way, nested into the same list. Pure, so it is unit-tested
 * against the PHP (the list the editor previews is the list the page gets).
 */
/**
 * Plain text of a heading's content (a string or a RichTextData).
 *
 * @param {string|Object} content Heading content attribute.
 * @return {string} Text.
 */
export function textOf( content ) {
	const html = String( content ?? '' );
	// DOMParser builds an inert document (no script runs, nothing loads) just to read the text.
	const text =
		typeof window !== 'undefined' && window.DOMParser
			? new window.DOMParser().parseFromString( html, 'text/html' ).body
					.textContent || ''
			: html.replace( /<[^>]*>/g, '' );
	return text.replace( /\s+/g, ' ' ).trim();
}

/**
 * WordPress's sanitize_title() for the cases that matter here: lower case, accents off, anything
 * but a–z, 0–9 and dashes collapsed to a dash. Returns '' for text with no Latin letters or digits
 * (the PHP turns those into a percent-encoded slug, which Toc::slug() replaces with section-<n>).
 *
 * @param {string} text Text.
 * @return {string} Slug.
 */
export function slugOf( text ) {
	const plain = text.normalize( 'NFD' ).replace( /[̀-ͯ]/g, '' );
	if ( /[^ -~]/.test( plain ) ) {
		return '';
	}
	return plain
		.toLowerCase()
		.replace( /&.+?;/g, '' )
		.replace( /[^a-z0-9 _-]/g, '' )
		.replace( /[\s_]+/g, '-' )
		.replace( /-+/g, '-' )
		.replace( /^-|-$/g, '' );
}

/**
 * Headings of a block tree: [ { level, text, id } ], in order, ids unique.
 *
 * @param {Object[]} blocks Editor blocks.
 * @return {Object[]} Headings.
 */
export function headingsOf( blocks ) {
	const flat = [];
	const walk = ( list ) =>
		list.forEach( ( b ) => {
			if ( b.name === 'core/heading' ) {
				flat.push( b );
			}
			walk( b.innerBlocks || [] );
		} );
	walk( blocks );

	const used = new Set();
	const out = [];
	let n = 0;
	for ( const b of flat ) {
		const text = textOf( b.attributes.content );
		if ( ! text ) {
			continue;
		}
		n++;
		const own = b.attributes.anchor || '';
		let id = own || slugOf( text ) || `section-${ n }`;
		const base = id;
		for ( let i = 2; used.has( id ); i++ ) {
			id = `${ base }-${ i }`;
		}
		used.add( id );
		out.push( {
			level: Math.max( 1, Math.min( 6, b.attributes.level || 2 ) ),
			text,
			id,
		} );
	}
	return out;
}

/**
 * Nest a flat heading list the way Toc::nested() does.
 *
 * @param {Object[]} items Headings (already filtered to the level range).
 * @param {number}   level The top level.
 * @return {Object[]} [ { text, id, children } ].
 */
export function nest( items, level ) {
	let i = 0;
	const build = ( lvl ) => {
		const list = [];
		while ( i < items.length && items[ i ].level >= lvl ) {
			const item = { ...items[ i ], children: [] };
			i++;
			if ( i < items.length && items[ i ].level > item.level ) {
				item.children = build( items[ i ].level );
			}
			list.push( item );
		}
		return list;
	};
	return build( level );
}
