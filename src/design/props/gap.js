/**
 * Design prop `gap` (pbs-p4). Twin: includes/design/props/class-gap.php.
 */
import { __ } from '@wordpress/i18n';

import * as V from '../values';

const OPTS = { keywords: [ 'normal' ], tokens: [ 'space', 'var' ] };

export default {
	key: 'gap',
	group: 'layout',
	target: 'layout',
	blocks: [],
	label: __( 'Gap', 'page-builder-sandwich' ),
	control: 'gap',
	sanitize( value ) {
		if ( value && typeof value === 'object' && ! ( 't' in value ) ) {
			const out = {};
			for ( const k of [ 'row', 'column' ] ) {
				const v = k in value ? V.length( value[ k ], OPTS ) : null;
				if ( v !== null ) {
					out[ k ] = v;
				}
			}
			return Object.keys( out ).length ? out : null;
		}
		const v = V.length( value, OPTS );
		return v === null ? null : { row: v, column: v };
	},
	toCss( value, prefix ) {
		const out = {};
		if ( value.row !== undefined ) {
			out[ 'row-gap' ] = V.css( value.row, prefix );
		}
		if ( value.column !== undefined ) {
			out[ 'column-gap' ] = V.css( value.column, prefix );
		}
		return out;
	},
};
