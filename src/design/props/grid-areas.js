/**
 * Design prop `gridAreas` (pbs-p4). Twin: includes/design/props/class-grid-areas.php.
 */
import { __ } from '@wordpress/i18n';

export default {
	key: 'gridAreas',
	group: 'layout',
	target: 'layout',
	blocks: [],
	label: __( 'Named areas', 'page-builder-sandwich' ),
	control: 'grid-areas',
	sanitize( value ) {
		if ( typeof value !== 'string' || value.length > 400 ) {
			return null;
		}
		value = value.trim().toLowerCase();
		if ( ! /^(?:"[a-z0-9._ -]+"\s*)+$/.test( value ) ) {
			return null;
		}
		const rows = [];
		for ( const m of value.matchAll( /"([^"]+)"/g ) ) {
			const row = m[ 1 ].replace( /\s+/g, ' ' ).trim();
			if ( row === '' ) {
				return null;
			}
			rows.push( `"${ row }"` );
		}
		return rows.join( ' ' );
	},
	toCss: ( value ) => ( { 'grid-template-areas': String( value ) } ),
};
