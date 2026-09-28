/**
 * Design prop `transition` (pbs-p4). Twin: includes/design/props/class-transition.php.
 */
import { __ } from '@wordpress/i18n';

import * as V from '../values';

export const PROPERTIES = {
	all: 'all',
	colors: 'color,background-color,border-color',
	opacity: 'opacity',
	transform: 'transform',
	shadow: 'box-shadow',
};
export const EASINGS = [
	'ease',
	'linear',
	'ease-in',
	'ease-out',
	'ease-in-out',
];

export default {
	key: 'transition',
	group: 'advanced',
	target: '',
	blocks: [],
	label: __( 'Transition', 'page-builder-sandwich' ),
	control: 'transition',
	sanitize( value ) {
		if ( ! value || typeof value !== 'object' || Array.isArray( value ) ) {
			return null;
		}
		const duration = V.number( value.duration ?? null, 0, 10000 );
		if ( duration === null ) {
			return null;
		}
		const out = {
			property:
				typeof value.property === 'string' &&
				Object.hasOwn( PROPERTIES, value.property )
					? value.property
					: 'all',
			duration: Math.round( duration ),
			easing: V.choice( value.easing, EASINGS ) ?? 'ease',
		};
		const delay = V.number( value.delay ?? null, 0, 10000 );
		if ( delay !== null && delay > 0 ) {
			out.delay = Math.round( delay );
		}
		return out;
	},
	toCss( value ) {
		const out = {
			'transition-property': PROPERTIES[ value.property ],
			'transition-duration': `${ value.duration }ms`,
			'transition-timing-function': value.easing,
		};
		if ( value.delay !== undefined ) {
			out[ 'transition-delay' ] = `${ value.delay }ms`;
		}
		return out;
	},
};
