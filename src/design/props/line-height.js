/**
 * Design prop `lineHeight` (pbs-p4). Twin: includes/design/props/class-line-height.php.
 */
import { __ } from '@wordpress/i18n';

import { lengthProp } from '../kinds';

export default lengthProp( {
	key: 'lineHeight',
	group: 'typography',
	property: 'line-height',
	opts: { unitless: true, keywords: [ 'normal' ], tokens: [ 'var' ] },
	label: __( 'Line height', 'page-builder-sandwich' ),
	control: 'length',
} );
