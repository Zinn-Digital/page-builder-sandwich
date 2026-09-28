/**
 * Design prop `placeItems` (pbs-p4). Twin: includes/design/props/class-place-items.php.
 */
import { __ } from '@wordpress/i18n';

const WORD = '(?:start|center|end|stretch|baseline|normal)';
const RE = new RegExp( `^${ WORD }(?: ${ WORD })?$` );

export default {
	key: 'placeItems',
	group: 'layout',
	target: 'layout',
	blocks: [],
	label: __( 'Place items', 'page-builder-sandwich' ),
	control: 'select',
	options: () => ( {
		start: __( 'Start', 'page-builder-sandwich' ),
		center: __( 'Centre', 'page-builder-sandwich' ),
		end: __( 'End', 'page-builder-sandwich' ),
		stretch: __( 'Stretch', 'page-builder-sandwich' ),
	} ),
	sanitize( value ) {
		if ( typeof value !== 'string' ) {
			return null;
		}
		value = value.trim().replace( /\s+/g, ' ' ).toLowerCase();
		return RE.test( value ) ? value : null;
	},
	toCss: ( value ) => ( { 'place-items': String( value ) } ),
};
