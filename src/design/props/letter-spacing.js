/**
 * Design prop `letterSpacing` (pbs-p4). Twin: includes/design/props/class-letter-spacing.php.
 */
import { __ } from '@wordpress/i18n';

import { lengthProp } from '../kinds';

export default lengthProp( {
	key: 'letterSpacing',
	group: 'typography',
	property: 'letter-spacing',
	opts: { neg: true, keywords: [ 'normal' ], tokens: [ 'var' ] },
	label: __( 'Letter spacing', 'page-builder-sandwich' ),
	control: 'length',
} );
