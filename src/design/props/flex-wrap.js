/**
 * Design prop `flexWrap` (pbs-p4). Twin: includes/design/props/class-flex-wrap.php.
 */
import { __ } from '@wordpress/i18n';

import { choiceProp } from '../kinds';

export default choiceProp( {
	key: 'flexWrap',
	group: 'layout',
	target: 'layout',
	property: 'flex-wrap',
	choices: [ 'nowrap', 'wrap', 'wrap-reverse' ],
	label: __( 'Wrap', 'page-builder-sandwich' ),
	control: 'buttons',
	options: () => ( {
		nowrap: __( 'No wrap', 'page-builder-sandwich' ),
		wrap: __( 'Wrap', 'page-builder-sandwich' ),
		'wrap-reverse': __( 'Wrap reversed', 'page-builder-sandwich' ),
	} ),
} );
