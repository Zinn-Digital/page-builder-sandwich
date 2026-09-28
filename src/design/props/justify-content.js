/**
 * Design prop `justifyContent` (pbs-p4). Twin: includes/design/props/class-justify-content.php.
 */
import { __ } from '@wordpress/i18n';

import { choiceProp } from '../kinds';

export default choiceProp( {
	key: 'justifyContent',
	group: 'layout',
	target: 'layout',
	property: 'justify-content',
	choices: [
		'start',
		'center',
		'end',
		'space-between',
		'space-around',
		'space-evenly',
		'stretch',
	],
	label: __( 'Justify content', 'page-builder-sandwich' ),
	control: 'buttons',
	options: () => ( {
		start: __( 'Start', 'page-builder-sandwich' ),
		center: __( 'Centre', 'page-builder-sandwich' ),
		end: __( 'End', 'page-builder-sandwich' ),
		'space-between': __( 'Space between', 'page-builder-sandwich' ),
		'space-around': __( 'Space around', 'page-builder-sandwich' ),
		'space-evenly': __( 'Space evenly', 'page-builder-sandwich' ),
		stretch: __( 'Stretch', 'page-builder-sandwich' ),
	} ),
} );
