/**
 * Design prop `backgroundColor` (pbs-p4). Twin: includes/design/props/class-background-color.php.
 */
import { __ } from '@wordpress/i18n';

import { colorProp } from '../kinds';

export default colorProp( {
	key: 'backgroundColor',
	group: 'colour',
	property: 'background-color',
	label: __( 'Background colour', 'page-builder-sandwich' ),
	control: 'color',
} );
