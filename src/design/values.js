/**
 * Value validators shared by every design prop (pbs-p4). Twin of
 * includes/design/class-values.php, line for line; wp/tests/fixtures/pbs-design/props.json holds
 * both to the same output.
 *
 * Every function returns a value that is safe inside a CSS declaration, or null.
 */

export const TOKEN_TYPES = [
	'color',
	'gradient',
	'size',
	'space',
	'font',
	'shadow',
	'radius',
	'var',
];

const UNITS =
	'px|em|rem|%|vw|vh|vmin|vmax|svh|lvh|dvh|svw|lvw|dvw|vi|vb|ch|ex|lh|fr|cqi|cqb';
const UNIT_LIST = UNITS.split( '|' );
const MAX_FUNCTION = 120;

const isObject = ( v ) =>
	v !== null && typeof v === 'object' && ! Array.isArray( v );
const isNumber = ( v ) => typeof v === 'number';

/**
 * A token reference whose type is one of `types`.
 *
 * @param {unknown}  value Candidate.
 * @param {string[]} types Allowed types.
 * @return {{t:string,v:string}|null} Token.
 */
export function token( value, types ) {
	if (
		! isObject( value ) ||
		typeof value.t !== 'string' ||
		typeof value.v !== 'string'
	) {
		return null;
	}
	if ( ! types.includes( value.t ) || ! TOKEN_TYPES.includes( value.t ) ) {
		return null;
	}
	if ( ! /^[a-z0-9][a-z0-9-]{0,63}$/.test( value.v ) ) {
		return null;
	}
	return { t: value.t, v: value.v };
}

/**
 * The CSS for a token.
 *
 * @param {{t:string,v:string}} tok    Token.
 * @param {string}              prefix Class prefix.
 * @return {string} CSS.
 */
export function tokenCss( tok, prefix ) {
	const v = tok.v;
	switch ( tok.t ) {
		case 'color':
			return `var(--wp--preset--color--${ v })`;
		case 'gradient':
			return `var(--wp--preset--gradient--${ v })`;
		case 'size':
			return `var(--wp--preset--font-size--${ v })`;
		case 'space':
			return `var(--${ prefix }-space-${ v },var(--wp--preset--spacing--${ v }))`;
		case 'font':
			return `var(--${ prefix }-font-${ v },var(--wp--preset--font-family--${ v }))`;
		case 'shadow':
			return `var(--${ prefix }-shadow-${ v },var(--wp--preset--shadow--${ v }))`;
		default:
			return `var(--${ prefix }-${ tok.t }-${ v })`;
	}
}

/**
 * A sanitised value as CSS.
 *
 * @param {string|Object} value  Value.
 * @param {string}        prefix Prefix.
 * @return {string} CSS.
 */
export const css = ( value, prefix ) =>
	isObject( value ) ? tokenCss( value, prefix ) : String( value );

/**
 * A number as CSS writes it: at most four decimals, no trailing zeros, never -0.
 *
 * @param {number} n Number.
 * @return {string} Text.
 */
export function num( n ) {
	n = Number( n );
	if ( Math.abs( n ) < 0.00005 ) {
		return '0';
	}
	let s = n.toFixed( 4 );
	s = s.replace( /0+$/, '' ).replace( /\.$/, '' );
	return s === '-0' ? '0' : s;
}

/**
 * A finite number within a range; numeric strings accepted.
 *
 * @param {unknown} value Candidate.
 * @param {number}  min   Min.
 * @param {number}  max   Max.
 * @return {number|null} Number.
 */
export function number( value, min, max ) {
	if (
		typeof value === 'string' &&
		/^-?(?:\d+(?:\.\d+)?|\.\d+)$/.test( value.trim() )
	) {
		value = parseFloat( value.trim() );
	}
	if ( ! isNumber( value ) || ! Number.isFinite( value ) ) {
		return null;
	}
	return value < min || value > max ? null : value;
}

/**
 * calc(), clamp(), min() or max() over numbers, units and operators only.
 *
 * @param {string} value Lower-cased, trimmed candidate.
 * @return {string|null} Value.
 */
export function func( value ) {
	if (
		value.length > MAX_FUNCTION ||
		! /^(calc|clamp|min|max)\([0-9a-z.%+\-*/ ,()]*\)$/.test( value )
	) {
		return null;
	}
	const allowed = [ ...UNIT_LIST, 'calc', 'clamp', 'min', 'max' ];
	for ( const word of value.match( /[a-z]+/g ) || [] ) {
		if ( ! allowed.includes( word ) ) {
			return null;
		}
	}
	let depth = 0;
	for ( const ch of value ) {
		if ( ch === '(' ) {
			depth++;
		} else if ( ch === ')' && --depth < 0 ) {
			return null;
		}
	}
	return depth === 0 ? value : null;
}

/**
 * A CSS length.
 *
 * @param {unknown} value Candidate.
 * @param {Object}  opts  neg, unitless, keywords, tokens.
 * @return {string|Object|null} Value.
 */
export function length( value, opts = {} ) {
	const neg = !! opts.neg;
	const unitless = !! opts.unitless;
	const keywords = opts.keywords || [];
	const tokens = opts.tokens || [];

	if ( isObject( value ) ) {
		return token( value, tokens );
	}
	if ( isNumber( value ) ) {
		if (
			! Number.isFinite( value ) ||
			( ! neg && value < 0 ) ||
			Math.abs( value ) > 100000
		) {
			return null;
		}
		return num( value ) + ( unitless || value === 0 ? '' : 'px' );
	}
	if ( typeof value !== 'string' ) {
		return null;
	}
	value = value.trim().toLowerCase();
	if ( value === '' ) {
		return null;
	}
	if ( keywords.includes( value ) ) {
		return value;
	}
	const m = new RegExp(
		`^(-?)((?:\\d+(?:\\.\\d+)?|\\.\\d+))(${ UNITS })?$`
	).exec( value );
	if ( m ) {
		if ( m[ 1 ] === '-' && ! neg ) {
			return null;
		}
		const n = parseFloat( m[ 1 ] + m[ 2 ] );
		const unit = m[ 3 ] || '';
		if ( Math.abs( n ) > 100000 ) {
			return null;
		}
		if ( unit === '' && ! unitless && n !== 0 ) {
			return null;
		}
		return (
			num( n ) +
			( n === 0 && ! unitless && unit !== '%' && unit !== 'fr'
				? ''
				: unit )
		);
	}
	return func( value );
}

/**
 * A CSS colour.
 *
 * @param {unknown} value Candidate.
 * @return {string|Object|null} Value.
 */
export function color( value ) {
	if ( isObject( value ) ) {
		return token( value, [ 'color', 'var' ] );
	}
	if ( typeof value !== 'string' ) {
		return null;
	}
	value = value.trim().toLowerCase();
	if ( /^#(?:[0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/.test( value ) ) {
		return value;
	}
	if (
		value.length <= 80 &&
		/^(?:rgba?|hsla?|hwb|lab|lch|oklab|oklch)\([0-9a-z.%, /+-]+\)$/.test(
			value
		)
	) {
		return value;
	}
	if ( /^[a-z]{3,20}$/.test( value ) ) {
		return value;
	}
	return null;
}

/**
 * One of a fixed set of strings.
 *
 * @param {unknown}  value   Candidate.
 * @param {string[]} allowed Allowed.
 * @return {string|null} Value.
 */
export const choice = ( value, allowed ) =>
	typeof value === 'string' && allowed.includes( value ) ? value : null;
