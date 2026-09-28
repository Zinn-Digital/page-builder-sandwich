/**
 * Design prop `fontStyle` (pbs-p4). Twin: includes/design/props/class-font-style.php.
 */
import { __ } from '@wordpress/i18n';

import { choiceProp } from '../kinds';

export default choiceProp( {
	key: 'fontStyle',
	group: 'typography',
	property: 'font-style',
	choices: [ 'normal', 'italic' ],
	label: __( 'Style', 'page-builder-sandwich' ),
	control: 'buttons',
	options: () => ( {
		normal: __( 'Normal', 'page-builder-sandwich' ),
		italic: __( 'Italic', 'page-builder-sandwich' ),
	} ),
} );
