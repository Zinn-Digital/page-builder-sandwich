/**
 * Design prop `contentWidth` (pbs-p4). Twin: includes/design/props/class-content-width.php.
 */
import { __ } from '@wordpress/i18n';

import * as V from '../values';
import { lengthProp } from '../kinds';

export default lengthProp( {
	key: 'contentWidth',
	group: 'layout',
	blocks: [ 'pbs/section', 'pbs/container' ],
	property: '',
	opts: { tokens: [ 'var' ] },
	label: __( 'Content width', 'page-builder-sandwich' ),
	control: 'length',
	toCss: ( value, prefix ) => ( {
		[ `--${ prefix }-cw` ]: V.css( value, prefix ),
	} ),
} );
