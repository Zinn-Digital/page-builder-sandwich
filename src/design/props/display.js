/**
 * Design prop `display` (pbs-p4). Twin: includes/design/props/class-display.php.
 */
import { __ } from '@wordpress/i18n';

import { choiceProp } from '../kinds';

export default choiceProp( {
	key: 'display',
	group: 'layout',
	target: 'layout',
	property: 'display',
	choices: [
		'block',
		'flex',
		'grid',
		'inline',
		'inline-block',
		'inline-flex',
		'inline-grid',
		'contents',
		'none',
	],
	label: __( 'Display', 'page-builder-sandwich' ),
	control: 'select',
	options: () => ( {
		block: __( 'Block', 'page-builder-sandwich' ),
		flex: __( 'Flexbox', 'page-builder-sandwich' ),
		grid: __( 'Grid', 'page-builder-sandwich' ),
		inline: __( 'Inline', 'page-builder-sandwich' ),
		'inline-block': __( 'Inline block', 'page-builder-sandwich' ),
		'inline-flex': __( 'Inline flexbox', 'page-builder-sandwich' ),
		'inline-grid': __( 'Inline grid', 'page-builder-sandwich' ),
		contents: __( 'Contents only', 'page-builder-sandwich' ),
		none: __( 'Hidden', 'page-builder-sandwich' ),
	} ),
} );
