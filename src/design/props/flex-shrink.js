/**
 * Design prop `flexShrink` (pbs-p4). Twin: includes/design/props/class-flex-shrink.php.
 */
import { __ } from '@wordpress/i18n';

import { numberProp } from '../kinds';

export default numberProp( {
	key: 'flexShrink',
	group: 'layout',
	property: 'flex-shrink',
	min: 0,
	max: 100,
	label: __( 'Shrink', 'page-builder-sandwich' ),
	control: 'number',
} );
