/**
 * Design prop `flexBasis` (pbs-p4). Twin: includes/design/props/class-flex-basis.php.
 */
import { __ } from '@wordpress/i18n';

import { lengthProp } from '../kinds';

export default lengthProp( {
	key: 'flexBasis',
	group: 'layout',
	property: 'flex-basis',
	opts: { keywords: [ 'auto', 'content' ], tokens: [ 'var' ] },
	label: __( 'Basis', 'page-builder-sandwich' ),
	control: 'length',
} );
