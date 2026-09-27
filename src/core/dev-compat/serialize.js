/**
 * Shortcode text for a pbs/shortcode block: `[tag a="b" /]` or `[tag a="b"]content[/tag]`.
 *
 * This string is what the block SAVES, so it is what stays in post_content if the plugin is ever
 * deactivated — WordPress then runs it as an ordinary shortcode. It must therefore be text that
 * WordPress's own parser (get_shortcode_regex + shortcode_parse_atts) reads back to the values
 * the editor set.
 *
 * ⭐ Escaping follows WordPress's own advice for shortcode attributes: a value may not carry a
 * double quote, square brackets or angle brackets, so those five characters are written as HTML
 * entities. The handler therefore receives them entity-encoded (`&quot;`, `&#91;` …), exactly as
 * it would from a hand-typed shortcode that followed the same advice.
 *
 * ⭐ An empty body is written SELF-CLOSING (`[tag /]`). A bare `[tag]` followed later on the page
 * by `[tag]…[/tag]` is read by WordPress as one enclosing shortcode spanning both blocks — which
 * is what the raw text would do once the plugin is gone.
 */

/** Shortcode tags this layer accepts (a strict subset of what add_shortcode() allows). */
export const TAG_RE = /^[A-Za-z0-9_-]+$/;

/** Attribute names WordPress's shortcode_parse_atts() can read back (`[\w-]+`). */
export const ATTR_NAME_RE = /^[A-Za-z0-9_-]+$/;

const ATTR_ESCAPES = {
	'"': '&quot;',
	'[': '&#91;',
	']': '&#93;',
	'<': '&lt;',
	'>': '&gt;',
};

/**
 * Escape one attribute value.
 *
 * @param {string} value Raw value.
 * @return {string} Value safe inside `name="…"`.
 */
export function escapeAttr( value ) {
	return String( value ).replace( /["[\]<>]/g, ( ch ) => ATTR_ESCAPES[ ch ] );
}

/**
 * Reverse of escapeAttr (for the editor reading a value back, and for tests).
 *
 * @param {string} value Escaped value.
 * @return {string} Raw value.
 */
export function unescapeAttr( value ) {
	return String( value )
		.replace( /&quot;/g, '"' )
		.replace( /&#91;/g, '[' )
		.replace( /&#93;/g, ']' )
		.replace( /&lt;/g, '<' )
		.replace( /&gt;/g, '>' );
}

/**
 * Normalise one attribute value to the string that is written, or null to omit it.
 *
 * @param {*} value Any value from the block's `attrs` object.
 * @return {string|null} String value, or null when the attribute is left out.
 */
export function attrValueToString( value ) {
	if ( value === null || value === undefined || value === false ) {
		return null;
	}
	if ( value === true ) {
		return 'true';
	}
	if ( typeof value === 'number' ) {
		return Number.isFinite( value ) ? String( value ) : null;
	}
	if ( Array.isArray( value ) ) {
		return value
			.filter( ( v ) => typeof v === 'string' || typeof v === 'number' )
			.join( ',' );
	}
	if ( typeof value === 'string' ) {
		return value;
	}
	return null;
}

/**
 * Build the shortcode text.
 *
 * @param {string} tag     Shortcode tag.
 * @param {Object} attrs   Attribute name => value.
 * @param {string} content Enclosed content ('' for none).
 * @return {string} Shortcode text, or '' when the tag is not a valid shortcode tag.
 */
export function buildShortcode( tag, attrs, content ) {
	if ( typeof tag !== 'string' || ! TAG_RE.test( tag ) ) {
		return '';
	}
	let out = '[' + tag;
	if ( attrs && typeof attrs === 'object' && ! Array.isArray( attrs ) ) {
		for ( const [ name, raw ] of Object.entries( attrs ) ) {
			if ( ! ATTR_NAME_RE.test( name ) ) {
				continue;
			}
			const value = attrValueToString( raw );
			if ( value === null ) {
				continue;
			}
			out += ' ' + name + '="' + escapeAttr( value ) + '"';
		}
	}
	const body = typeof content === 'string' ? content : '';
	if ( body === '' ) {
		return out + ' /]';
	}

	// The body may hold nested shortcodes and HTML; only two sequences are neutralised: our own
	// closing tag (it would end the shortcode early) and an HTML comment opener (a stray
	// `<!-- /wp:…` would end the BLOCK early when the post is parsed).
	const closing = '[/' + tag + ']';
	const safeBody = body
		.split( closing )
		.join( '&#91;/' + tag + '&#93;' )
		.replace( /<!--/g, '&lt;!--' );

	return out + ']' + safeBody + closing;
}

/**
 * Strip tags from a legacy description. Legacy printed `desc` as raw HTML (`{{{ data.desc }}}`);
 * here it is shown as text, so an add-on can never inject markup into the editor through it.
 *
 * @param {*} value Description.
 * @return {string} Plain text.
 */
export function plainText( value ) {
	if ( typeof value !== 'string' && typeof value !== 'number' ) {
		return '';
	}
	// Strip tags until nothing changes, then drop any stray angle bracket, so a nested or broken
	// tag (`<<b>b>`) cannot survive as markup (CodeQL js/incomplete-multi-character-sanitization).
	let text = String( value );
	let previous;
	do {
		previous = text;
		text = text.replace( /<[^>]*>/g, '' );
	} while ( text !== previous );
	return text.replace( /[<>]/g, '' ).replace( /\s+/g, ' ' ).trim();
}
