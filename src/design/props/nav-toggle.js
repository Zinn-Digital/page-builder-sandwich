/**
 * Design prop `navToggle` (NAV1, pbs/nav-menu). Twin: includes/design/props/class-nav-toggle.php.
 */
import { __ } from '@wordpress/i18n';

import * as V from '../values';
import { colorProp } from '../kinds';

export default colorProp( {
	key: 'navToggle',
	group: 'menu',
	blocks: [ 'pbs/nav-menu' ],
	property: '',
	label: __( 'Menu button colour', 'page-builder-sandwich' ),
	control: 'color',
	toCss: ( value, prefix ) => ( {
		[ `--${ prefix }-nav-toggle` ]: V.css( value, prefix ),
	} ),
} );
