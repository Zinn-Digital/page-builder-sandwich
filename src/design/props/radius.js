/**
 * Design prop `radius` (pbs-p4). Twin: includes/design/props/class-radius.php.
 */
import { __ } from '@wordpress/i18n';

import { sidesProp } from '../kinds';

export default sidesProp( {
	key: 'radius',
	group: 'border',
	property: 'border-%s-radius',
	opts: { tokens: [ 'radius', 'var' ] },
	sides: {
		startStart: 'start-start',
		startEnd: 'start-end',
		endStart: 'end-start',
		endEnd: 'end-end',
	},
	label: __( 'Corner radius', 'page-builder-sandwich' ),
	control: 'corners',
} );
