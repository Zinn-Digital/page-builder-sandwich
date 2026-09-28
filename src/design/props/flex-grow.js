/**
 * Design prop `flexGrow` (pbs-p4). Twin: includes/design/props/class-flex-grow.php.
 */
import { __ } from '@wordpress/i18n';

import { numberProp } from '../kinds';

export default numberProp( {
	key: 'flexGrow',
	group: 'layout',
	property: 'flex-grow',
	min: 0,
	max: 100,
	label: __( 'Grow', 'page-builder-sandwich' ),
	control: 'number',
} );
