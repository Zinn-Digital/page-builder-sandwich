/**
 * Custom HTML attributes on a block (`pbs.attrs`). Twin of includes/design/class-html-attributes.php:
 * only `data-*` and `aria-*` names, at most MAX per block, values plain text up to MAX_VALUE
 * characters. The server applies them on render; the editor only stores what passes here.
 */

export const NAME_PATTERN =
	/^(?:data-[a-z0-9][a-z0-9_.:-]{0,62}|aria-[a-z]{2,40})$/;
export const MAX = 20;
export const MAX_VALUE = 500;

/**
 * Is a name allowed?
 *
 * @param {unknown} name Name.
 * @return {boolean} Allowed.
 */
export const allowed = ( name ) =>
	typeof name === 'string' && NAME_PATTERN.test( name );

/**
 * Normalise a typed name: trimmed, lower-case.
 *
 * @param {string} name Name.
 * @return {string} Name.
 */
export const normalName = ( name ) =>
	String( name || '' )
		.trim()
		.toLowerCase();

/**
 * Editor rows ([{ name, value }]) → the stored object, dropping refused names and empty rows.
 *
 * @param {Array<{name: string, value: string}>} rows Rows.
 * @return {Object<string, string>} Attributes.
 */
export function toStored( rows ) {
	const out = {};
	for ( const row of rows || [] ) {
		if ( Object.keys( out ).length >= MAX ) {
			break;
		}
		const name = normalName( row?.name );
		if ( ! allowed( name ) ) {
			continue;
		}
		out[ name ] = Array.from( String( row?.value ?? '' ) )
			.filter(
				( c ) => c.charCodeAt( 0 ) > 31 && c.charCodeAt( 0 ) !== 127
			)
			.join( '' )
			.slice( 0, MAX_VALUE );
	}
	return out;
}

/**
 * The stored object → editor rows.
 *
 * @param {Object<string, string>|undefined} attrs Stored.
 * @return {Array<{name: string, value: string}>} Rows.
 */
export function toRows( attrs ) {
	return Object.entries( attrs && typeof attrs === 'object' ? attrs : {} )
		.filter( ( [ name ] ) => allowed( name ) )
		.map( ( [ name, value ] ) => ( { name, value: String( value ) } ) );
}
