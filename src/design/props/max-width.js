/**
 * Design prop `maxWidth` (pbs-p4). Twin: includes/design/props/class-max-width.php.
 */
import { __ } from '@wordpress/i18n';

import { lengthProp } from '../kinds';

export default lengthProp( {
	key: 'maxWidth',
	group: 'size',
	property: 'max-inline-size',
	opts: {
		keywords: [ 'none', 'fit-content', 'min-content', 'max-content' ],
		tokens: [ 'var' ],
	},
	label: __( 'Max width', 'page-builder-sandwich' ),
	control: 'length',
} );
