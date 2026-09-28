/**
 * Design prop `zIndex` (pbs-p4). Twin: includes/design/props/class-z-index.php.
 */
import { __ } from '@wordpress/i18n';

import { numberProp } from '../kinds';

export default numberProp( {
	key: 'zIndex',
	group: 'position',
	property: 'z-index',
	min: -9999,
	max: 9999,
	integer: true,
	label: __( 'Stack order (z-index)', 'page-builder-sandwich' ),
	control: 'number',
} );
