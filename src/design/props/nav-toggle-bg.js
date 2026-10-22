/**
 * Design prop `navToggleBg` (NAV1, pbs/nav-menu). Twin: includes/design/props/class-nav-toggle-bg.php.
 */
import { __ } from '@wordpress/i18n';

import * as V from '../values';
import { colorProp } from '../kinds';

export default colorProp( {
	key: 'navToggleBg',
	group: 'menu',
	blocks: [ 'pbs/nav-menu' ],
	property: '',
	label: __( 'Menu button background', 'page-builder-sandwich' ),
	control: 'color',
	toCss: ( value, prefix ) => ( {
		[ `--${ prefix }-nav-toggle-bg` ]: V.css( value, prefix ),
	} ),
} );
