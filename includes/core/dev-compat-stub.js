/*
 * The legacy developer API, available before any add-on script runs.
 *
 * Printed inline at the very top of the block editor's <head> by class-dev-compat.php, ahead of
 * every other script, so an add-on that calls window.pbsAddInspector() the moment its file
 * loads finds the function. Calls are queued; the dev-compat bundle replays them, in order,
 * once the block API is ready, and from then on handles calls directly (registry.js install()).
 */
( function ( w ) {
	'use strict';
	w.pbswDevCompatQueue = w.pbswDevCompatQueue || [];
	const queue = function ( op ) {
		return function () {
			w.pbswDevCompatQueue.push(
				[ op ].concat( Array.prototype.slice.call( arguments ) )
			);
		};
	};
	if ( typeof w.pbsAddInspector !== 'function' ) {
		w.pbsAddInspector = queue( 'add' );
	}
	if ( typeof w.pbsRemoveInspector !== 'function' ) {
		w.pbsRemoveInspector = queue( 'remove' );
	}
} )( window );
