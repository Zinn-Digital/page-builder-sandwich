/**
 * Design prop `navSubShadow` (NAV1, pbs/nav-menu). Twin: includes/design/props/class-nav-sub-shadow.php.
 */
import { __ } from '@wordpress/i18n';

import { shadowProp } from '../kinds';

const base = shadowProp( { key: 'navSubShadow', property: 'box-shadow' } );

export default shadowProp( {
	key: 'navSubShadow',
	group: 'menu',
	blocks: [ 'pbs/nav-menu' ],
	property: 'box-shadow',
	label: __( 'Submenu shadow', 'page-builder-sandwich' ),
	control: 'shadow',
	toCss: ( value, prefix ) => ( {
		[ `--${ prefix }-nav-sub-shadow` ]: base.toCss( value, prefix )[
			'box-shadow'
		],
	} ),
} );
