/**
 * Design prop `margin` (pbs-p4). Twin: includes/design/props/class-margin.php.
 */
import { __ } from '@wordpress/i18n';

import { sidesProp } from '../kinds';

export default sidesProp( {
	key: 'margin',
	group: 'spacing',
	property: 'margin-%s',
	opts: { neg: true, keywords: [ 'auto' ], tokens: [ 'space', 'var' ] },
	label: __( 'Margin', 'page-builder-sandwich' ),
	control: 'sides',
} );
