/**
 * Design prop `aspectRatio` (pbs-p4). Twin: includes/design/props/class-aspect-ratio.php.
 */
import { __ } from '@wordpress/i18n';

import * as V from '../values';

export default {
	key: 'aspectRatio',
	group: 'size',
	target: '',
	blocks: [],
	label: __( 'Aspect ratio', 'page-builder-sandwich' ),
	control: 'aspect-ratio',
	sanitize( value ) {
		if ( typeof value === 'number' ) {
			const n = V.number( value, 0.01, 100 );
			return n === null ? null : V.num( n );
		}
		if ( typeof value !== 'string' ) {
			return null;
		}
		value = value.trim().toLowerCase();
		if ( value === 'auto' ) {
			return 'auto';
		}
		const m = /^(\d+(?:\.\d+)?)(?:\s*\/\s*(\d+(?:\.\d+)?))?$/.exec( value );
		if ( ! m ) {
			return null;
		}
		const w = parseFloat( m[ 1 ] );
		const h = m[ 2 ] !== undefined ? parseFloat( m[ 2 ] ) : null;
		if (
			w <= 0 ||
			w > 10000 ||
			( h !== null && ( h <= 0 || h > 10000 ) )
		) {
			return null;
		}
		return V.num( w ) + ( h === null ? '' : '/' + V.num( h ) );
	},
	toCss: ( value ) => ( { 'aspect-ratio': String( value ) } ),
};
