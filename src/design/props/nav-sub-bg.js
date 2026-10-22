/**
 * Design prop `navSubBg` (NAV1, pbs/nav-menu). Twin: includes/design/props/class-nav-sub-bg.php.
 */
import { __ } from '@wordpress/i18n';

import * as V from '../values';
import { colorProp } from '../kinds';

export default colorProp( {
	key: 'navSubBg',
	group: 'menu',
	blocks: [ 'pbs/nav-menu' ],
	property: '',
	label: __( 'Submenu background', 'page-builder-sandwich' ),
	control: 'color',
	toCss: ( value, prefix ) => ( {
		[ `--${ prefix }-nav-sub-bg` ]: V.css( value, prefix ),
	} ),
} );
