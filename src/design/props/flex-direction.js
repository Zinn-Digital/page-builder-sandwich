/**
 * Design prop `flexDirection` (pbs-p4). Twin: includes/design/props/class-flex-direction.php.
 */
import { __ } from '@wordpress/i18n';

import { choiceProp } from '../kinds';

export default choiceProp( {
	key: 'flexDirection',
	group: 'layout',
	target: 'layout',
	property: 'flex-direction',
	choices: [ 'row', 'row-reverse', 'column', 'column-reverse' ],
	label: __( 'Direction', 'page-builder-sandwich' ),
	control: 'buttons',
	options: () => ( {
		row: __( 'Row', 'page-builder-sandwich' ),
		'row-reverse': __( 'Row reversed', 'page-builder-sandwich' ),
		column: __( 'Column', 'page-builder-sandwich' ),
		'column-reverse': __( 'Column reversed', 'page-builder-sandwich' ),
	} ),
} );
