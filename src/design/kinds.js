/**
 * Prop kinds (pbs-p4). Twins of includes/design/kinds/*.php. A prop is a plain object:
 *
 *   { key, group, target, blocks, label, control, options,
 *     sanitize( value ) → safe value | null,
 *     toCss( safeValue, prefix ) → { property: value } }
 *
 * The panel reads `group`, `label`, `control` and `options`; the compiler reads the rest.
 */
import * as V from './values';

const base = ( def ) => ( {
	group: 'advanced',
	target: '',
	blocks: [],
	...def,
} );

/**
 * Does a prop apply to a block?
 *
 * @param {Object} prop      Prop.
 * @param {string} blockName Block name.
 * @return {boolean} Applies.
 */
export const appliesTo = ( prop, blockName ) =>
	! prop.blocks.length || prop.blocks.includes( blockName );

export const choiceProp = ( def ) =>
	base( {
		sanitize: ( v ) => V.choice( v, def.choices ),
		toCss: ( v ) => ( { [ def.property ]: String( v ) } ),
		...def,
	} );

export const lengthProp = ( def ) =>
	base( {
		sanitize: ( v ) => V.length( v, def.opts || {} ),
		toCss: ( v, prefix ) => ( { [ def.property ]: V.css( v, prefix ) } ),
		...def,
	} );

export const colorProp = ( def ) =>
	base( {
		group: 'colour',
		sanitize: ( v ) => V.color( v ),
		toCss: ( v, prefix ) => ( { [ def.property ]: V.css( v, prefix ) } ),
		...def,
	} );

export const SIDES = {
	blockStart: 'block-start',
	inlineEnd: 'inline-end',
	blockEnd: 'block-end',
	inlineStart: 'inline-start',
};

export const sidesProp = ( def ) => {
	const sides = def.sides || SIDES;
	return base( {
		sanitize: ( v ) => {
			if ( ! v || typeof v !== 'object' || Array.isArray( v ) ) {
				return null;
			}
			const out = {};
			for ( const side of Object.keys( sides ) ) {
				if ( side in v ) {
					const clean = V.length( v[ side ], def.opts || {} );
					if ( clean !== null ) {
						out[ side ] = clean;
					}
				}
			}
			return Object.keys( out ).length ? out : null;
		},
		toCss: ( v, prefix ) => {
			const out = {};
			for ( const [ side, css ] of Object.entries( sides ) ) {
				if ( v[ side ] !== undefined ) {
					out[ def.property.replace( '%s', css ) ] = V.css(
						v[ side ],
						prefix
					);
				}
			}
			return out;
		},
		...def,
	} );
};

export const templateProp = ( def ) =>
	base( {
		group: 'layout',
		target: 'layout',
		sanitize: ( v ) => {
			if (
				( typeof v === 'number' && Number.isInteger( v ) ) ||
				( typeof v === 'string' && /^\d{1,2}$/.test( v.trim() ) )
			) {
				const count = parseInt( v, 10 );
				return count >= 1 && count <= 24
					? `repeat(${ count },${ def.track })`
					: null;
			}
			if ( typeof v !== 'string' ) {
				return null;
			}
			v = v.trim().replace( /\s+/g, ' ' ).toLowerCase();
			if (
				v === '' ||
				v.length > 300 ||
				! /^[0-9a-z.%()\-, [\]]+$/.test( v )
			) {
				return null;
			}
			let depth = 0;
			for ( const ch of v ) {
				if ( ch === '(' || ch === '[' ) {
					depth++;
				} else if ( ( ch === ')' || ch === ']' ) && --depth < 0 ) {
					return null;
				}
			}
			return depth === 0 ? v : null;
		},
		toCss: ( v ) => ( { [ def.property ]: String( v ) } ),
		...def,
	} );

export const numberProp = ( def ) =>
	base( {
		sanitize: ( v ) => {
			const n = V.number( v, def.min, def.max );
			if ( n === null || ( def.integer && ! Number.isInteger( n ) ) ) {
				return null;
			}
			return n;
		},
		toCss: ( v ) => ( { [ def.property ]: V.num( v ) } ),
		...def,
	} );

export const spanProp = ( def ) =>
	base( {
		group: 'layout',
		sanitize: ( v ) => {
			if ( v === 'full' ) {
				return 'full';
			}
			if ( typeof v === 'string' && /^\d{1,2}$/.test( v ) ) {
				v = parseInt( v, 10 );
			}
			if ( ! Number.isInteger( v ) || v < 1 || v > 24 ) {
				return null;
			}
			return v;
		},
		toCss: ( v ) => ( {
			[ def.property ]: v === 'full' ? '1/-1' : `span ${ v }`,
		} ),
		...def,
	} );

export const shadowProp = ( def ) => {
	const box = def.box !== false;
	return base( {
		group: 'shadow',
		sanitize: ( value ) => {
			if ( value === 'none' ) {
				return 'none';
			}
			if ( ! value || typeof value !== 'object' ) {
				return null;
			}
			if ( ! Array.isArray( value ) ) {
				return 't' in value
					? V.token( value, [ 'shadow', 'var' ] )
					: null;
			}
			const layers = [];
			for ( const layer of value.slice( 0, 4 ) ) {
				if ( ! layer || typeof layer !== 'object' ) {
					continue;
				}
				const x = V.length( layer.x ?? 0, { neg: true } );
				const y = V.length( layer.y ?? 0, { neg: true } );
				const blur = V.length( layer.blur ?? 0 );
				const c = V.color( layer.color ?? null );
				if (
					typeof x !== 'string' ||
					typeof y !== 'string' ||
					typeof blur !== 'string' ||
					c === null
				) {
					continue;
				}
				const one = { x, y, blur, color: c };
				if ( box ) {
					const spread = V.length( layer.spread ?? 0, { neg: true } );
					if ( typeof spread !== 'string' ) {
						continue;
					}
					one.spread = spread;
					if ( layer.inset === true ) {
						one.inset = true;
					}
				}
				layers.push( one );
			}
			return layers.length ? layers : null;
		},
		toCss: ( value, prefix ) => {
			if ( typeof value === 'string' ) {
				return { [ def.property ]: value };
			}
			if ( ! Array.isArray( value ) ) {
				return { [ def.property ]: V.tokenCss( value, prefix ) };
			}
			return {
				[ def.property ]: value
					.map(
						( l ) =>
							( l.inset ? 'inset ' : '' ) +
							`${ l.x } ${ l.y } ${ l.blur }` +
							( box ? ` ${ l.spread }` : '' ) +
							` ${ V.css( l.color, prefix ) }`
					)
					.join( ',' ),
			};
		},
		...def,
	} );
};
