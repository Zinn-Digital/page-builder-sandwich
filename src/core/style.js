/**
 * Turn a declaration string (`min-height: 380px; color: #333`) into a React style object, for
 * the EDITOR preview only. The saved HTML keeps the string as written (save.js), and the server
 * sanitises it again on output (includes/core/class-css.php).
 *
 * @param {string} css Declarations.
 * @return {Object} React style object.
 */
export function styleObject( css ) {
	const out = {};
	for ( const decl of ( css || '' ).split( ';' ) ) {
		const at = decl.indexOf( ':' );
		if ( at < 1 ) {
			continue;
		}
		const prop = decl.slice( 0, at ).trim().toLowerCase();
		const value = decl.slice( at + 1 ).trim();
		if ( ! /^-?[a-z][a-z0-9-]*$/.test( prop ) || ! value ) {
			continue;
		}
		if ( /url\s*\(|expression|javascript:/i.test( value ) ) {
			continue;
		}
		const key = prop.startsWith( '--' )
			? prop
			: prop
					.replace( /^-/, '' )
					.replace( /-([a-z])/g, ( m, c ) => c.toUpperCase() );
		out[ key ] = value;
	}
	return out;
}
