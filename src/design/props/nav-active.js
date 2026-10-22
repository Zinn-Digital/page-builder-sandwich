/**
 * Design prop `navActive` (NAV1, pbs/nav-menu). Twin: includes/design/props/class-nav-active.php.
 */
import { __ } from '@wordpress/i18n';

import * as V from '../values';
import { colorProp } from '../kinds';

export default colorProp( {
	key: 'navActive',
	group: 'menu',
	blocks: [ 'pbs/nav-menu' ],
	property: '',
	label: __( 'Current page link colour', 'page-builder-sandwich' ),
	control: 'color',
	toCss: ( value, prefix ) => ( {
		[ `--${ prefix }-nav-active` ]: V.css( value, prefix ),
	} ),
} );
