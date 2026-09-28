/**
 * Design prop `fontSize` (pbs-p4). Twin: includes/design/props/class-font-size.php.
 */
import { __ } from '@wordpress/i18n';

import { lengthProp } from '../kinds';

export default lengthProp( {
	key: 'fontSize',
	group: 'typography',
	property: 'font-size',
	opts: { tokens: [ 'size', 'var' ] },
	label: __( 'Size', 'page-builder-sandwich' ),
	control: 'length',
} );
