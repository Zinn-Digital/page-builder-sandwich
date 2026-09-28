/**
 * Design prop `textAlign` (pbs-p4). Twin: includes/design/props/class-text-align.php.
 */
import { __ } from '@wordpress/i18n';

import { choiceProp } from '../kinds';

export default choiceProp( {
	key: 'textAlign',
	group: 'typography',
	property: 'text-align',
	choices: [ 'start', 'center', 'end', 'justify' ],
	label: __( 'Alignment', 'page-builder-sandwich' ),
	control: 'buttons',
	options: () => ( {
		start: __( 'Start', 'page-builder-sandwich' ),
		center: __( 'Centre', 'page-builder-sandwich' ),
		end: __( 'End', 'page-builder-sandwich' ),
		justify: __( 'Justify', 'page-builder-sandwich' ),
	} ),
} );
