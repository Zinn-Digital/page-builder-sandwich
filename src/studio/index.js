/**
 * Sandwich Studio entry (webpack entry `studio`, handle pbsw-studio). PHP prints the boot data as
 * `window.pbswStudio` (includes/core/class-studio.php).
 */
import { createRoot } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import domReady from '@wordpress/dom-ready';
import { getBlockType } from '@wordpress/blocks';
import { addFilter } from '@wordpress/hooks';
import { MediaUpload } from '@wordpress/media-utils';
import App from './app';
import { clearSnapshot, defaultStorage, snapshotKey } from './lib/snapshot';
import './style.scss';

/*
 * Every REST call Studio makes says so. Safe mode's must-use file narrows the plugin list only
 * for a request carrying BOTH this header and the user's signed cookie, so the normal editor in
 * another tab (same cookie, no header) keeps every plugin.
 */
apiFetch.use( ( options, next ) =>
	next( {
		...options,
		headers: { ...( options.headers || {} ), 'X-PBSW-Studio': '1' },
	} )
);

// The block editor screen registers these in edit-post; Studio is its own screen.
// wp-block-library is a declared dependency of this script (class-studio.php); it is read from
// the global rather than imported so the package is not installed into the plugin folder (its
// PHP sources would ship inside node_modules scans).
if ( ! getBlockType( 'core/paragraph' ) ) {
	window.wp.blockLibrary.registerCoreBlocks();
}
addFilter(
	'editor.MediaUpload',
	'pbsw/studio/media-upload',
	() => MediaUpload
);

domReady( () => {
	const root = document.getElementById( 'pbsw-studio-root' );
	const data = window.pbswStudio;
	if ( ! root || ! data ) {
		return;
	}
	const storage = defaultStorage();
	const key = snapshotKey( data.site, data.postId );
	const boot = {
		...data,
		storage,
		snapshotKey: key,
		clearSnapshot: () => clearSnapshot( storage, key ),
	};
	createRoot( root ).render( <App boot={ boot } /> );
} );
