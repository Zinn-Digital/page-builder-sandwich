/**
 * Design prop `navBackdrop` (NAV1, pbs/nav-menu). Twin: includes/design/props/class-nav-backdrop.php.
 */
import { __ } from '@wordpress/i18n';

import * as V from '../values';
import { colorProp } from '../kinds';

export default colorProp( {
	key: 'navBackdrop',
	group: 'menu',
	blocks: [ 'pbs/nav-menu' ],
	property: '',
	label: __( 'Page dimming behind the mobile menu', 'page-builder-sandwich' ),
	control: 'color',
	toCss: ( value, prefix ) => ( {
		[ `--${ prefix }-nav-backdrop` ]: V.css( value, prefix ),
	} ),
} );
