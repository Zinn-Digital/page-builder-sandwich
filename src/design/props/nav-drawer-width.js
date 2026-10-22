/**
 * Design prop `navDrawerWidth` (NAV1, pbs/nav-menu). Twin: includes/design/props/class-nav-drawer-width.php.
 */
import { __ } from '@wordpress/i18n';

import * as V from '../values';
import { lengthProp } from '../kinds';

export default lengthProp( {
	key: 'navDrawerWidth',
	group: 'menu',
	blocks: [ 'pbs/nav-menu' ],
	property: '',
	opts: { tokens: [ 'var' ] },
	label: __( 'Mobile menu width', 'page-builder-sandwich' ),
	control: 'length',
	toCss: ( value, prefix ) => ( {
		[ `--${ prefix }-nav-drawer-width` ]: V.css( value, prefix ),
	} ),
} );
