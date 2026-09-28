/**
 * Design prop `cursor` (pbs-p4). Twin: includes/design/props/class-cursor.php.
 */
import { __ } from '@wordpress/i18n';

import { choiceProp } from '../kinds';

export default choiceProp( {
	key: 'cursor',
	group: 'advanced',
	property: 'cursor',
	choices: [
		'auto',
		'default',
		'pointer',
		'text',
		'move',
		'grab',
		'not-allowed',
		'help',
		'crosshair',
		'zoom-in',
	],
	label: __( 'Cursor', 'page-builder-sandwich' ),
	control: 'select',
	options: () => ( {
		auto: __( 'Automatic', 'page-builder-sandwich' ),
		default: __( 'Arrow', 'page-builder-sandwich' ),
		pointer: __( 'Pointer', 'page-builder-sandwich' ),
		text: __( 'Text', 'page-builder-sandwich' ),
		move: __( 'Move', 'page-builder-sandwich' ),
		grab: __( 'Grab', 'page-builder-sandwich' ),
		'not-allowed': __( 'Not allowed', 'page-builder-sandwich' ),
		help: __( 'Help', 'page-builder-sandwich' ),
		crosshair: __( 'Crosshair', 'page-builder-sandwich' ),
		'zoom-in': __( 'Zoom in', 'page-builder-sandwich' ),
	} ),
} );
