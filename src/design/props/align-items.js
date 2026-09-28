/**
 * Design prop `alignItems` (pbs-p4). Twin: includes/design/props/class-align-items.php.
 */
import { __ } from '@wordpress/i18n';

import { choiceProp } from '../kinds';

export default choiceProp( {
	key: 'alignItems',
	group: 'layout',
	target: 'layout',
	property: 'align-items',
	choices: [ 'start', 'center', 'end', 'stretch', 'baseline' ],
	label: __( 'Align items', 'page-builder-sandwich' ),
	control: 'buttons',
	options: () => ( {
		start: __( 'Start', 'page-builder-sandwich' ),
		center: __( 'Centre', 'page-builder-sandwich' ),
		end: __( 'End', 'page-builder-sandwich' ),
		stretch: __( 'Stretch', 'page-builder-sandwich' ),
		baseline: __( 'Baseline', 'page-builder-sandwich' ),
	} ),
} );
