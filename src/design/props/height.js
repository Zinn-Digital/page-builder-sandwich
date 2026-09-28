/**
 * Design prop `height` (pbs-p4). Twin: includes/design/props/class-height.php.
 */
import { __ } from '@wordpress/i18n';

import { lengthProp } from '../kinds';

export default lengthProp( {
	key: 'height',
	group: 'size',
	property: 'block-size',
	opts: {
		keywords: [ 'auto', 'fit-content', 'min-content', 'max-content' ],
		tokens: [ 'var' ],
	},
	label: __( 'Height', 'page-builder-sandwich' ),
	control: 'length',
} );
