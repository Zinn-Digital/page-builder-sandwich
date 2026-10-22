/**
 * Design prop `navDrawerColor` (NAV1, pbs/nav-menu). Twin: includes/design/props/class-nav-drawer-color.php.
 */
import { __ } from '@wordpress/i18n';

import * as V from '../values';
import { colorProp } from '../kinds';

export default colorProp( {
	key: 'navDrawerColor',
	group: 'menu',
	blocks: [ 'pbs/nav-menu' ],
	property: '',
	label: __( 'Mobile menu text', 'page-builder-sandwich' ),
	control: 'color',
	toCss: ( value, prefix ) => ( {
		[ `--${ prefix }-nav-drawer-color` ]: V.css( value, prefix ),
	} ),
} );
