/**
 * Design prop `color` (pbs-p4). Twin: includes/design/props/class-color.php.
 */
import { __ } from '@wordpress/i18n';

import { colorProp } from '../kinds';

export default colorProp( {
	key: 'color',
	group: 'colour',
	property: 'color',
	label: __( 'Text colour', 'page-builder-sandwich' ),
	control: 'color',
} );
