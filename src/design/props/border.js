/**
 * Design prop `border` (pbs-p4). Twin: includes/design/props/class-border.php.
 */
import { __ } from '@wordpress/i18n';

import * as V from '../values';
import { SIDES } from '../kinds';

export const STYLES = [
	'none',
	'solid',
	'dashed',
	'dotted',
	'double',
	'groove',
	'ridge',
	'inset',
	'outset',
];

export default {
	key: 'border',
	group: 'border',
	target: '',
	blocks: [],
	label: __( 'Border', 'page-builder-sandwich' ),
	control: 'border',
	sanitize( value ) {
		if ( ! value || typeof value !== 'object' || Array.isArray( value ) ) {
			return null;
		}
		const out = {};
		for ( const side of Object.keys( SIDES ) ) {
			const input = value[ side ];
			if (
				! input ||
				typeof input !== 'object' ||
				Array.isArray( input )
			) {
				continue;
			}
			const one = {};
			const width = V.length( input.width ?? null, {
				keywords: [ 'thin', 'medium', 'thick' ],
				tokens: [ 'var' ],
			} );
			if ( width !== null ) {
				one.width = width;
			}
			const style = V.choice( input.style, STYLES );
			if ( style !== null ) {
				one.style = style;
			}
			const c = V.color( input.color ?? null );
			if ( c !== null ) {
				one.color = c;
			}
			if ( Object.keys( one ).length ) {
				out[ side ] = one;
			}
		}
		return Object.keys( out ).length ? out : null;
	},
	toCss( value, prefix ) {
		const out = {};
		for ( const [ side, css ] of Object.entries( SIDES ) ) {
			for ( const k of [ 'width', 'style', 'color' ] ) {
				if ( value[ side ]?.[ k ] !== undefined ) {
					out[ `border-${ css }-${ k }` ] = V.css(
						value[ side ][ k ],
						prefix
					);
				}
			}
		}
		return out;
	},
};
