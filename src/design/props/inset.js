/**
 * Design prop `inset` (pbs-p4). Twin: includes/design/props/class-inset.php.
 */
import { __ } from '@wordpress/i18n';

import { sidesProp } from '../kinds';

export default sidesProp( {
	key: 'inset',
	group: 'position',
	property: 'inset-%s',
	opts: { neg: true, keywords: [ 'auto' ], tokens: [ 'space', 'var' ] },
	label: __( 'Offsets', 'page-builder-sandwich' ),
	control: 'sides',
} );
