/**
 * Design prop `order` (pbs-p4). Twin: includes/design/props/class-order.php.
 */
import { __ } from '@wordpress/i18n';

import { numberProp } from '../kinds';

export default numberProp( {
	key: 'order',
	group: 'layout',
	property: 'order',
	min: -99,
	max: 99,
	integer: true,
	label: __( 'Order', 'page-builder-sandwich' ),
	control: 'number',
} );
