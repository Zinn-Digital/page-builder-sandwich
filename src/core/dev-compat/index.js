/**
 * Developer compatibility layer (feature pbs-dev5): the pbs/shortcode block plus the legacy
 * `window.pbsAddInspector()` / `pbs_shortcodes` API on top of it.
 *
 * Built as its own bundle (`build/dev-compat.js`, handle `pbsw-dev-compat`), loaded only in the
 * block editor. `setupDevCompat()` is exported so the core editor bundle can import it
 * instead; it runs once per window whichever bundle calls it first.
 */

import {
	registerBlockType,
	registerBlockVariation,
	unregisterBlockVariation,
	getBlockVariations,
} from '@wordpress/blocks';

import metadata from '../../../blocks/shortcode/block.json';
import { createRegistry, install } from './registry';
import { makeEdit } from './edit';
import save from './save';

export { buildShortcode } from './serialize';
export { createRegistry, install } from './registry';
export { describeOption } from './controls';

/**
 * Register the block and take over the legacy globals.
 *
 * @param {Window} win The window.
 * @return {Object} The registry.
 */
export function setupDevCompat( win = window ) {
	if ( win.pbswDevCompatRegistry ) {
		return win.pbswDevCompatRegistry;
	}
	const registry = createRegistry( {
		register: ( variation ) =>
			registerBlockVariation( metadata.name, variation ),
		unregister: ( name ) => {
			if (
				( getBlockVariations( metadata.name ) || [] ).some(
					( v ) => v.name === name
				)
			) {
				unregisterBlockVariation( metadata.name, name );
			}
		},
	} );
	registerBlockType( metadata, { edit: makeEdit( registry ), save } );
	win.pbswDevCompatRegistry = registry;
	install( win, registry );
	return registry;
}

setupDevCompat();
