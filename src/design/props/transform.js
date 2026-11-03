/**
 * Design prop `transform` (pbs-p4b): rotate, scale, skew and offset.
 * Twin: includes/design/props/class-transform.php.
 */
import { __ } from '@wordpress/i18n';

import * as V from '../values';

/** Number parts → [min, max]. */
export const RANGES = {
	rotate: [ -360, 360 ],
	scale: [ 0, 5 ],
	skewX: [ -80, 80 ],
	skewY: [ -80, 80 ],
};

/** Length options for the offsets. */
export const OFFSET = { neg: true };

export default {
	key: 'transform',
	group: 'transform',
	target: '',
	blocks: [],
	label: __( 'Transform', 'page-builder-sandwich' ),
	control: 'transform',
	sanitize( value ) {
		if ( ! value || typeof value !== 'object' || Array.isArray( value ) ) {
			return null;
		}
		const out = {};
		for ( const [ part, [ min, max ] ] of Object.entries( RANGES ) ) {
			const n = V.number( value[ part ] ?? null, min, max );
			if ( n !== null ) {
				out[ part ] = n;
			}
		}
		for ( const part of [ 'x', 'y' ] ) {
			const len = V.length( value[ part ] ?? null, OFFSET );
			if ( typeof len === 'string' ) {
				out[ part ] = len;
			}
		}
		return Object.keys( out ).length ? out : null;
	},
	toCss( value, prefix ) {
		const out = {};
		const x = value.x ?? '0';
		const y = value.y ?? '0';
		if ( x !== '0' || y !== '0' ) {
			out.translate = `${
				x === '0' ? '0' : `calc(${ x }*var(--${ prefix }-tdir,1))`
			} ${ y }`;
		}
		if ( value.rotate !== undefined ) {
			out.rotate = `${ V.num( value.rotate ) }deg`;
		}
		if ( value.scale !== undefined ) {
			out.scale = V.num( value.scale );
		}
		if ( value.skewX !== undefined || value.skewY !== undefined ) {
			out.transform = `skew(${ V.num( value.skewX ?? 0 ) }deg,${ V.num(
				value.skewY ?? 0
			) }deg)`;
		}
		return out;
	},
};
