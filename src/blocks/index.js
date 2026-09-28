/**
 * Registers the free design-system blocks in the block editor (webpack entry `blocks`, script
 * handle pbsw-blocks-editor — wp-admin only).
 */
import { registerBlockType } from '@wordpress/blocks';

import { BLOCKS } from './list';

for ( const { metadata, settings } of BLOCKS ) {
	registerBlockType( metadata, settings );
}
