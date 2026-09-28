/**
 * Design prop `minWidth` (pbs-p4). Twin: includes/design/props/class-min-width.php.
 */
import { __ } from '@wordpress/i18n';

import { lengthProp } from '../kinds';

export default lengthProp( {
	key: 'minWidth',
	group: 'size',
	property: 'min-inline-size',
	opts: {
		keywords: [ 'auto', 'fit-content', 'min-content', 'max-content' ],
		tokens: [ 'var' ],
	},
	label: __( 'Min width', 'page-builder-sandwich' ),
	control: 'length',
} );
