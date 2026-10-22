/**
 * Design prop `navHover` (NAV1, pbs/nav-menu). Twin: includes/design/props/class-nav-hover.php.
 */
import { __ } from '@wordpress/i18n';

import * as V from '../values';
import { colorProp } from '../kinds';

export default colorProp( {
	key: 'navHover',
	group: 'menu',
	blocks: [ 'pbs/nav-menu' ],
	property: '',
	label: __( 'Link colour on hover', 'page-builder-sandwich' ),
	control: 'color',
	toCss: ( value, prefix ) => ( {
		[ `--${ prefix }-nav-hover` ]: V.css( value, prefix ),
	} ),
} );
