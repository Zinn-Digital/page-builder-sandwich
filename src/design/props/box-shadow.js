/**
 * Design prop `boxShadow` (pbs-p4). Twin: includes/design/props/class-box-shadow.php.
 */
import { __ } from '@wordpress/i18n';

import { shadowProp } from '../kinds';

export default shadowProp( {
	key: 'boxShadow',
	property: 'box-shadow',
	label: __( 'Box shadow', 'page-builder-sandwich' ),
	control: 'shadow',
} );
