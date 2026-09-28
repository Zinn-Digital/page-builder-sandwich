/**
 * Registers the free design-system blocks in the block editor (webpack entry `blocks`, script
 * handle pbsw-blocks-editor — wp-admin only), and the extensions of core blocks (P5 group G-B).
 */
import { registerBlockType } from '@wordpress/blocks';

import { BLOCKS } from './list';
// The inline "Tooltip" text format (any paragraph, heading or list).
import './tooltip/format';
import { registerExtensions } from './extensions';

for ( const { metadata, settings } of BLOCKS ) {
	registerBlockType( metadata, settings );
}

registerExtensions();
