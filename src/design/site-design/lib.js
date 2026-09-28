/**
 * Pure helpers for the Site design screen (unit-tested in __tests__/lib.test.js). They mirror
 * the server's validation so the screen can say what is wrong before a request is sent; the
 * server stays the authority and re-checks everything.
 */

/** A theme.json preset slug. */
export const SLUG_RE = /^[a-z0-9][a-z0-9-]{0,47}$/;

/**
 * Is this a colour the server accepts (Theme_Globals::is_color)?
 *
 * @param {string} value Colour.
 * @return {boolean} Valid.
 */
export function isColor( value ) {
	const color = String( value || '' )
		.trim()
		.toLowerCase();
	if ( /^#(?:[0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/.test( color ) ) {
		return true;
	}
	if ( color === 'transparent' || color === 'currentcolor' ) {
		return true;
	}
	return /^(?:rgba?|hsla?|hwb|lab|lch|oklab|oklch)\([0-9.%\s,/+a-z-]{1,80}\)$/.test(
		color
	);
}

/**
 * Is this a font stack the server accepts?
 *
 * @param {string} stack Stack.
 * @return {boolean} Valid.
 */
export function isFontStack( stack ) {
	const s = String( stack || '' );
	return s.length > 0 && s.length <= 300 && /^[\p{L}\p{N} ,"'._-]+$/u.test( s );
}

/**
 * A slug from a display name, unique in `taken`.
 *
 * @param {string}   name  Name.
 * @param {string[]} taken Slugs in use.
 * @return {string} Slug.
 */
export function slugify( name, taken = [] ) {
	let base = String( name || '' )
		.normalize( 'NFKD' )
		.replace( /[̀-ͯ]/g, '' )
		.toLowerCase()
		.replace( /[^a-z0-9]+/g, '-' )
		.replace( /^-+|-+$/g, '' )
		.slice( 0, 40 )
		.replace( /-+$/g, '' );
	if ( ! SLUG_RE.test( base ) ) {
		base = 'item';
	}
	let slug = base;
	for ( let n = 2; taken.includes( slug ); n++ ) {
		slug = `${ base }-${ n }`;
	}
	return slug;
}

/**
 * Every font the site has, theme first, for a picker.
 *
 * @param {{theme: Array, custom: Array}} fonts Fonts by origin.
 * @return {Array<{label: string, value: string}>} Options.
 */
export function fontOptions( fonts ) {
	const seen = new Set();
	const out = [];
	for ( const font of [ ...( fonts?.theme || [] ), ...( fonts?.custom || [] ) ] ) {
		if ( ! seen.has( font.slug ) ) {
			seen.add( font.slug );
			out.push( { label: font.name || font.slug, value: font.slug } );
		}
	}
	return out;
}

/**
 * Every colour the site has (default, theme, custom), de-duplicated by slug with the later
 * origin winning — the same precedence WordPress applies.
 *
 * @param {{default?: Array, theme?: Array, custom?: Array}} colors Colours by origin.
 * @return {Array<{slug: string, name: string, color: string}>} Colours.
 */
export function allColors( colors ) {
	const map = new Map();
	for ( const origin of [ 'default', 'theme', 'custom' ] ) {
		for ( const c of colors?.[ origin ] || [] ) {
			map.set( c.slug, c );
		}
	}
	return [ ...map.values() ];
}

/**
 * The first problem in a colour list, or null.
 *
 * @param {Array<{slug: string, color: string}>} list Colours.
 * @return {string|null} The slug of the first invalid entry, or '#dup' for a duplicate slug.
 */
export function firstInvalidColor( list ) {
	const seen = new Set();
	for ( const c of list ) {
		if ( ! SLUG_RE.test( c.slug ) || seen.has( c.slug ) ) {
			return '#dup';
		}
		seen.add( c.slug );
		if ( ! isColor( c.color ) ) {
			return c.slug;
		}
	}
	return null;
}
