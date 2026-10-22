/**
 * Design prop `navDrawerBg` (NAV1, pbs/nav-menu). Twin: includes/design/props/class-nav-drawer-bg.php.
 */
import { __ } from '@wordpress/i18n';

import * as V from '../values';
import { colorProp } from '../kinds';

export default colorProp( {
	key: 'navDrawerBg',
	group: 'menu',
	blocks: [ 'pbs/nav-menu' ],
	property: '',
	label: __( 'Mobile menu background', 'page-builder-sandwich' ),
	control: 'color',
	toCss: ( value, prefix ) => ( {
		[ `--${ prefix }-nav-drawer-bg` ]: V.css( value, prefix ),
	} ),
} );
