/**
 * Design prop `position` (pbs-p4). Twin: includes/design/props/class-position.php.
 */
import { __ } from '@wordpress/i18n';

import { choiceProp } from '../kinds';

export default choiceProp( {
	key: 'position',
	group: 'position',
	property: 'position',
	choices: [ 'static', 'relative', 'absolute', 'fixed', 'sticky' ],
	label: __( 'Position', 'page-builder-sandwich' ),
	control: 'select',
	options: () => ( {
		static: __( 'Default', 'page-builder-sandwich' ),
		relative: __( 'Relative', 'page-builder-sandwich' ),
		absolute: __( 'Absolute', 'page-builder-sandwich' ),
		fixed: __( 'Fixed', 'page-builder-sandwich' ),
		sticky: __( 'Sticky', 'page-builder-sandwich' ),
	} ),
} );
