/**
 * Design prop `padding` (pbs-p4). Twin: includes/design/props/class-padding.php.
 */
import { __ } from '@wordpress/i18n';

import { sidesProp } from '../kinds';

export default sidesProp( {
	key: 'padding',
	group: 'spacing',
	property: 'padding-%s',
	opts: { tokens: [ 'space', 'var' ] },
	label: __( 'Padding', 'page-builder-sandwich' ),
	control: 'sides',
} );
