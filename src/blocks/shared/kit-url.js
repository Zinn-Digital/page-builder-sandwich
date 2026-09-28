/**
 * A URL safe to save (the twin of Core\Render::safe_url): http(s), mailto, tel, relative or an
 * anchor. Any other scheme (javascript:, data:, vbscript: …) gives ''. Numeric and named
 * character references are decoded first, so `java&#115;cript:` cannot hide a scheme.
 * Kept apart from kit.js so a block's save() pulls in nothing else.
 */

const NAMED = { colon: ':', tab: '\t', newline: '\n', sol: '/', amp: '&' };

/**
 * Decode the character references a URL could use to hide its scheme.
 *
 * @param {string} value Raw value.
 * @return {string} Decoded.
 */
function decode( value ) {
	return value
		.replace( /&#x([0-9a-f]+);?/gi, ( m, hex ) =>
			String.fromCodePoint( parseInt( hex, 16 ) )
		)
		.replace( /&#([0-9]+);?/g, ( m, dec ) =>
			String.fromCodePoint( parseInt( dec, 10 ) )
		)
		.replace(
			/&([a-z]+);/gi,
			( m, name ) => NAMED[ name.toLowerCase() ] ?? m
		);
}

/**
 * @param {string} url URL.
 * @return {string} URL or ''.
 */
export function safeUrl( url ) {
	const value = String( url || '' ).trim();
	if ( ! value ) {
		return '';
	}
	// Remove whitespace and control characters, as Core\Render::safe_url() does.
	// eslint-disable-next-line no-control-regex
	const spaces = /[\x00-\x20]+/g;
	const squashed = decode( value ).replace( spaces, '' ).toLowerCase();
	if (
		/^[a-z][a-z0-9+.-]*:/.test( squashed ) &&
		! /^(https?|mailto|tel):/.test( squashed )
	) {
		return '';
	}
	return value;
}
