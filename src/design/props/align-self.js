/**
 * Design prop `alignSelf` (pbs-p4). Twin: includes/design/props/class-align-self.php.
 */
import { __ } from '@wordpress/i18n';

import { choiceProp } from '../kinds';

export default choiceProp( {
	key: 'alignSelf',
	group: 'layout',
	property: 'align-self',
	choices: [ 'auto', 'start', 'center', 'end', 'stretch', 'baseline' ],
	label: __( 'Align self', 'page-builder-sandwich' ),
	control: 'buttons',
	options: () => ( {
		auto: __( 'Auto', 'page-builder-sandwich' ),
		start: __( 'Start', 'page-builder-sandwich' ),
		center: __( 'Centre', 'page-builder-sandwich' ),
		end: __( 'End', 'page-builder-sandwich' ),
		stretch: __( 'Stretch', 'page-builder-sandwich' ),
		baseline: __( 'Baseline', 'page-builder-sandwich' ),
	} ),
} );
