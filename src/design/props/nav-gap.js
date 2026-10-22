/**
 * Design prop `navGap` (NAV1, pbs/nav-menu). Twin: includes/design/props/class-nav-gap.php.
 */
import { __ } from '@wordpress/i18n';

import * as V from '../values';
import { lengthProp } from '../kinds';

export default lengthProp( {
	key: 'navGap',
	group: 'menu',
	blocks: [ 'pbs/nav-menu' ],
	property: '',
	opts: { tokens: [ 'space', 'var' ] },
	label: __( 'Space between items', 'page-builder-sandwich' ),
	control: 'length',
	toCss: ( value, prefix ) => ( {
		[ `--${ prefix }-nav-gap` ]: V.css( value, prefix ),
	} ),
} );
