/**
 * Design prop `navSubColor` (NAV1, pbs/nav-menu). Twin: includes/design/props/class-nav-sub-color.php.
 */
import { __ } from '@wordpress/i18n';

import * as V from '../values';
import { colorProp } from '../kinds';

export default colorProp( {
	key: 'navSubColor',
	group: 'menu',
	blocks: [ 'pbs/nav-menu' ],
	property: '',
	label: __( 'Submenu text', 'page-builder-sandwich' ),
	control: 'color',
	toCss: ( value, prefix ) => ( {
		[ `--${ prefix }-nav-sub-color` ]: V.css( value, prefix ),
	} ),
} );
