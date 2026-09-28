/**
 * Design prop `minHeight` (pbs-p4). Twin: includes/design/props/class-min-height.php.
 */
import { __ } from '@wordpress/i18n';

import { lengthProp } from '../kinds';

export default lengthProp( {
	key: 'minHeight',
	group: 'size',
	property: 'min-block-size',
	opts: {
		keywords: [ 'auto', 'fit-content', 'min-content', 'max-content' ],
		tokens: [ 'var' ],
	},
	label: __( 'Min height', 'page-builder-sandwich' ),
	control: 'length',
} );
