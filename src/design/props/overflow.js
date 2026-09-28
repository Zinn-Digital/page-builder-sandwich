/**
 * Design prop `overflow` (pbs-p4). Twin: includes/design/props/class-overflow.php.
 */
import { __ } from '@wordpress/i18n';

import { choiceProp } from '../kinds';

export default choiceProp( {
	key: 'overflow',
	group: 'advanced',
	property: 'overflow',
	choices: [ 'visible', 'hidden', 'clip', 'auto', 'scroll' ],
	label: __( 'Overflow', 'page-builder-sandwich' ),
	control: 'select',
	options: () => ( {
		visible: __( 'Visible', 'page-builder-sandwich' ),
		hidden: __( 'Hidden', 'page-builder-sandwich' ),
		clip: __( 'Clip', 'page-builder-sandwich' ),
		auto: __( 'Scroll when needed', 'page-builder-sandwich' ),
		scroll: __( 'Always scroll', 'page-builder-sandwich' ),
	} ),
} );
