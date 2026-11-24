/**
 * Design prop `navPad` (NAV1, pbsw/nav-menu). Twin: includes/design/props/class-nav-pad.php.
 */
import { __ } from '@wordpress/i18n';

import * as V from '../values';
import { lengthProp } from '../kinds';

export default lengthProp( {
	key: 'navPad',
	group: 'menu',
	blocks: [ 'pbsw/nav-menu' ],
	property: '',
	opts: { tokens: [ 'space', 'var' ] },
	label: __( 'Space above and below each link', 'page-builder-sandwich' ),
	control: 'length',
	toCss: ( value, prefix ) => ( {
		[ `--${ prefix }-nav-pad` ]: V.css( value, prefix ),
	} ),
} );
