/**
 * Design prop `navSubRadius` (NAV1, pbs/nav-menu). Twin: includes/design/props/class-nav-sub-radius.php.
 */
import { __ } from '@wordpress/i18n';

import * as V from '../values';
import { lengthProp } from '../kinds';

export default lengthProp( {
	key: 'navSubRadius',
	group: 'menu',
	blocks: [ 'pbs/nav-menu' ],
	property: '',
	opts: { tokens: [ 'radius', 'var' ] },
	label: __( 'Submenu corners', 'page-builder-sandwich' ),
	control: 'length',
	toCss: ( value, prefix ) => ( {
		[ `--${ prefix }-nav-sub-radius` ]: V.css( value, prefix ),
	} ),
} );
