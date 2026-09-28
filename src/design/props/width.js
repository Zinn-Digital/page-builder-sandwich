/**
 * Design prop `width` (pbs-p4). Twin: includes/design/props/class-width.php.
 */
import { __ } from '@wordpress/i18n';

import { lengthProp } from '../kinds';

export default lengthProp( {
	key: 'width',
	group: 'size',
	property: 'inline-size',
	opts: {
		keywords: [ 'auto', 'fit-content', 'min-content', 'max-content' ],
		tokens: [ 'var' ],
	},
	label: __( 'Width', 'page-builder-sandwich' ),
	control: 'length',
} );
