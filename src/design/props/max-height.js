/**
 * Design prop `maxHeight` (pbs-p4). Twin: includes/design/props/class-max-height.php.
 */
import { __ } from '@wordpress/i18n';

import { lengthProp } from '../kinds';

export default lengthProp( {
	key: 'maxHeight',
	group: 'size',
	property: 'max-block-size',
	opts: {
		keywords: [ 'none', 'fit-content', 'min-content', 'max-content' ],
		tokens: [ 'var' ],
	},
	label: __( 'Max height', 'page-builder-sandwich' ),
	control: 'length',
} );
