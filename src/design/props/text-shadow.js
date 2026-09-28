/**
 * Design prop `textShadow` (pbs-p4). Twin: includes/design/props/class-text-shadow.php.
 */
import { __ } from '@wordpress/i18n';

import { shadowProp } from '../kinds';

export default shadowProp( {
	key: 'textShadow',
	property: 'text-shadow',
	box: false,
	label: __( 'Text shadow', 'page-builder-sandwich' ),
	control: 'shadow',
} );
