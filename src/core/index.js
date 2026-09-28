/**
 * Registers the P1 core blocks in the block editor (script handle pbsw-core-editor).
 */
import { __ } from '@wordpress/i18n';
import { registerBlockType, registerBlockVariation } from '@wordpress/blocks';

import * as edits from './edit';
import { BLOCKS } from './blocks';
// pbs-p4: the layout-shift (CLS) warning for image, video, HTML and embed blocks.
import './perf';

for ( const { metadata, save, edit } of BLOCKS ) {
	registerBlockType( metadata, { edit: edits[ edit ], save } );
}

// 6.2: an icon inserted from the inserter sits on its own line (a block the theme's layout
// sizes and aligns). 6.1 icons keep no `display` and are placed automatically by the server
// (Core\Blocks::icon_display()); the Placement setting changes either.
registerBlockVariation( 'pbs/icon', {
	name: 'icon-block',
	title: __( 'Icon', 'page-builder-sandwich' ),
	isDefault: true,
	scope: [ 'inserter' ],
	attributes: { display: 'block' },
	isActive: [ 'display' ],
} );
