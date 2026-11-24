/*
 * The legacy developer API, available before any add-on script runs.
 *
 * Loaded (blocking) at the very top of the block editor's <head> by class-dev-compat.php, ahead of
 * every other script, so an add-on that calls window.pbswAddInspector() the moment its file
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
	if ( typeof w.pbswAddInspector !== 'function' ) {
		w.pbswAddInspector = queue( 'add' );
	}
	if ( typeof w.pbswRemoveInspector !== 'function' ) {
		w.pbswRemoveInspector = queue( 'remove' );
	}
} )( window );
