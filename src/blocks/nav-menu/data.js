/**
 * The chosen menu's items (and every menu, for the picker), from core's REST API. Shared by
 * pbs/nav-menu's editor and the Pro mega panel and item options.
 */
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';

import { fromRecords, menuTree } from './tree';

/**
 * @param {number} menuId Menu.
 * @return {Object} { menus, items, loading, allowed }
 */
export function useMenu( menuId ) {
	return useSelect(
		( select ) => {
			const core = select( coreStore );
			const allowed = core.canUser( 'read', {
				kind: 'root',
				name: 'menu',
			} );
			const menus = core.getEntityRecords( 'root', 'menu', {
				per_page: -1,
			} );
			const query = { menus: menuId, per_page: -1 };
			const records = menuId
				? core.getEntityRecords( 'root', 'menuItem', query )
				: [];
			return {
				allowed: allowed !== false,
				menus: menus || [],
				items: menuTree( fromRecords( records ) ),
				loading:
					menus === null ||
					( !! menuId &&
						! core.hasFinishedResolution( 'getEntityRecords', [
							'root',
							'menuItem',
							query,
						] ) ),
			};
		},
		[ menuId ]
	);
}

/**
 * Every item of a tree, depth first, with its depth: for pickers that list the whole menu.
 *
 * @param {Array<Object>} items Tree.
 * @param {number}        depth Depth of these items.
 * @return {Array<Object>} Items with `depth`.
 */
export function flatItems( items, depth = 0 ) {
	return ( items || [] ).flatMap( ( i ) => [
		{ ...i, depth },
		...flatItems( i.children, depth + 1 ),
	] );
}
