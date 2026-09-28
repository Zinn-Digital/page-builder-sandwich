/**
 * Design prop `gridArea` (pbs-p4). Twin: includes/design/props/class-grid-area.php.
 */
import { __ } from '@wordpress/i18n';

export default {
	key: 'gridArea',
	group: 'layout',
	target: '',
	blocks: [],
	label: __( 'Area name', 'page-builder-sandwich' ),
	control: 'text',
	sanitize( value ) {
		if ( typeof value !== 'string' ) {
			return null;
		}
		value = value.trim().toLowerCase();
		return /^[a-z_][a-z0-9_-]{0,31}$/.test( value ) ? value : null;
	},
	toCss: ( value ) => ( { 'grid-area': String( value ) } ),
};
