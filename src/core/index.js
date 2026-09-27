/**
 * Registers the P1 core blocks in the block editor (script handle pbsw-core-editor).
 */
import { registerBlockType } from '@wordpress/blocks';

import * as edits from './edit';
import { BLOCKS } from './blocks';
// pbs-p4: the layout-shift (CLS) warning for image, video, HTML and embed blocks.
import './perf';

for ( const { metadata, save, edit } of BLOCKS ) {
	registerBlockType( metadata, { edit: edits[ edit ], save } );
}
