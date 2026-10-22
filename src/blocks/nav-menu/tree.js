/**
 * A menu title as plain text: entities decoded (the REST API sends `&#8217;` and friends), any
 * markup dropped. An inert document is built for it, so nothing runs or loads.
 *
 * @param {string} value Title.
 * @return {string} Text.
 */
function decodeEntities( value ) {
	const text = String( value ?? '' );
	if (
		! /[&<]/.test( text ) ||
		typeof window === 'undefined' ||
		! window.DOMParser
	) {
		return text;
	}
	return (
		new window.DOMParser().parseFromString(
			`<!doctype html><body>${ text }`,
			'text/html'
		).body.textContent || ''
	);
}

/**
 * Menu items → a tree: the editor's twin of Site\Menu_Data::tree() (a vitest leg holds the two
 * together). Items whose parent is missing become top level, so a half-deleted menu still shows
 * every link; three levels at most, deeper items following their ancestor at the third.
 *
 * @param {Array<{id:number,parent:number,title:string}>} flat Items in menu order.
 * @return {Array<Object>} Tree: each item with `children`.
 */
export function menuTree( flat ) {
	const ids = new Set( flat.map( ( i ) => i.id ) );
	const children = new Map();
	for ( const item of flat ) {
		const parent = ids.has( item.parent ) ? item.parent : 0;
		if ( ! children.has( parent ) ) {
			children.set( parent, [] );
		}
		children.get( parent ).push( item );
	}
	const strip = ( item ) =>
		Object.fromEntries(
			Object.entries( item ).filter( ( [ key ] ) => key !== 'parent' )
		);
	const below = ( parent ) =>
		( children.get( parent ) || [] ).flatMap( ( item ) => [
			item,
			...below( item.id ),
		] );
	const build = ( parent, depth ) =>
		( children.get( parent ) || [] ).flatMap( ( item ) =>
			depth < 2
				? [
						{
							...strip( item ),
							children: build( item.id, depth + 1 ),
						},
					]
				: // Three levels at most: deeper items follow their ancestor, so no link is lost.
					[ item, ...below( item.id ) ].map( ( i ) => ( {
						...strip( i ),
						children: [],
					} ) )
		);
	return build( 0, 0 );
}

/**
 * core-data menu item records → flat items.
 *
 * @param {Array<Object>} records `root`/`menuItem` records.
 * @return {Array<Object>} Flat items, in menu order.
 */
export function fromRecords( records ) {
	return [ ...( records || [] ) ]
		.sort( ( a, b ) => a.menu_order - b.menu_order )
		.map( ( r ) => ( {
			id: r.id,
			parent: r.parent || 0,
			title: decodeEntities(
				( r.title?.rendered || r.title?.raw || '' ).replace(
					/<[^>]*>/g,
					''
				)
			),
			url: r.url || '',
			desc: decodeEntities( r.description || '' ),
		} ) );
}
