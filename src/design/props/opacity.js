/**
 * Design prop `opacity` (pbs-p4). Twin: includes/design/props/class-opacity.php.
 */
import { __ } from '@wordpress/i18n';

import { numberProp } from '../kinds';

export default numberProp( {
	key: 'opacity',
	group: 'advanced',
	property: 'opacity',
	min: 0,
	max: 1,
	label: __( 'Opacity', 'page-builder-sandwich' ),
	control: 'range',
} );
