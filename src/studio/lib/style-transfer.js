/**
 * Copy and paste styles and sections between blocks, pages and SITES (feature pbs-d7).
 *
 * The clipboard carries plain JSON with a versioned marker, so a copy made in Studio on one
 * WordPress can be pasted into Studio on another: nothing in it refers to this site (no post or
 * attachment IDs are treated as style, and a section travels as serialized block markup, the
 * same portable format WordPress itself copies blocks as).
 *
 * ⭐ WHAT COUNTS AS "STYLE" IS DERIVED, NOT LISTED. A block's style is every attribute its own
 * block type declares that is not content: not `role: "content"`, not sourced from the markup
 * (`source`), and not an identity or link (ids, URLs, anchors, lock, metadata). A hand-kept list of
 * style keys would be correct for today's blocks and silently wrong for the next one.
 */

/** Marker key and the format version this build writes and reads. */
export const MARKER = 'pbswStudioClipboard';
export const VERSION = 1;

/**
 * Attributes shared by most blocks that express appearance. Used when pasting onto a block of a
 * DIFFERENT type: only these travel, and only where the target block declares them with the
 * same type.
 */
export const SHARED_STYLE_KEYS = [
	'style',
	'legacyClass',
	'className',
	'backgroundColor',
	'textColor',
	'gradient',
	'fontSize',
	'fontFamily',
	'borderColor',
	'align',
	'textAlign',
];

/**
 * Names that identify, link or hold content — never style, whatever their schema says. `legacy`
 * is provenance (the block came from 5.x content) and `legacyData`/`wrapData` are 5.x behaviour
 * settings: pasting style onto a new block must not turn it into a legacy one.
 */
const NOT_STYLE =
	/^(id|ids|ref|url|href|src|srcset|link|linkTo|linkTarget|linkDestination|linkClass|rel|target|anchor|alt|caption|content|text|title|label|value|values|tag|tagName|attrs|instance|widget|sidebar|svg|iconRef|metadata|lock|templateLock|allowedBlocks|placeholder|name|slug|level|ordered|start|reversed|citation|mediaId|mediaUrl|mediaType|mediaLink|sizeSlug|dimRatio|focalPoint|legacyData|legacy|wrapData)$|(Id|Ids|Url|URL|Href)$/;

/**
 * Whether one attribute definition of a block type is a style attribute.
 *
 * @param {string} key Attribute name.
 * @param {Object} def Attribute definition from block.json.
 * @return {boolean} True for style.
 */
export function isStyleAttribute( key, def ) {
	if ( ! def || typeof def !== 'object' ) {
		return false;
	}
	if ( def.role === 'content' || def.__experimentalRole === 'content' ) {
		return false;
	}
	if ( def.source ) {
		return false;
	}
	return ! NOT_STYLE.test( key );
}

/**
 * The style attribute names a block type declares.
 *
 * @param {Object} blockType A registered block type.
 * @return {string[]} Names.
 */
export function styleKeysOf( blockType ) {
	const attrs = blockType?.attributes || {};
	return Object.keys( attrs ).filter( ( key ) =>
		isStyleAttribute( key, attrs[ key ] )
	);
}

/**
 * The JSON type of a value, in block.json's vocabulary.
 *
 * @param {*} value Value.
 * @return {string} Type.
 */
function jsonType( value ) {
	if ( Array.isArray( value ) ) {
		return 'array';
	}
	if ( value === null ) {
		return 'null';
	}
	if ( typeof value === 'number' ) {
		return Number.isInteger( value ) ? 'integer' : 'number';
	}
	return typeof value;
}

/**
 * Whether a value may be stored in an attribute of this definition.
 *
 * @param {*}      value Value (undefined = "unset", always allowed).
 * @param {Object} def   Attribute definition.
 * @return {boolean} Allowed.
 */
export function fitsAttribute( value, def ) {
	if ( value === undefined ) {
		return true;
	}
	if ( def?.enum && ! def.enum.includes( value ) ) {
		return false;
	}
	const types = [].concat( def?.type || [] );
	if ( ! types.length ) {
		return true;
	}
	const got = jsonType( value );
	return types.some(
		( t ) => t === got || ( t === 'number' && got === 'integer' )
	);
}

/**
 * Build the clipboard text for a block's style.
 *
 * @param {Object} block     The block (name, attributes).
 * @param {Object} blockType Its registered type.
 * @return {string} JSON text.
 */
export function copyStyle( block, blockType ) {
	const keys = styleKeysOf( blockType );
	const attributes = {};
	for ( const key of keys ) {
		if ( block.attributes?.[ key ] !== undefined ) {
			attributes[ key ] = block.attributes[ key ];
		}
	}
	return JSON.stringify( {
		[ MARKER ]: VERSION,
		kind: 'style',
		blockName: block.name,
		keys,
		attributes,
	} );
}

/**
 * Build the clipboard text for a section (one or more blocks).
 *
 * @param {string} markup Serialized block markup.
 * @param {number} count  Number of top-level blocks.
 * @return {string} JSON text.
 */
export function copySection( markup, count ) {
	return JSON.stringify( {
		[ MARKER ]: VERSION,
		kind: 'section',
		count,
		content: markup,
	} );
}

/**
 * Read clipboard text written by copyStyle()/copySection() (by this build or another site's).
 *
 * @param {string} text Clipboard text.
 * @return {Object|null} The payload, or null when it is not ours or from a newer format.
 */
export function readClipboard( text ) {
	if ( typeof text !== 'string' || ! text.trim().startsWith( '{' ) ) {
		return null;
	}
	let data;
	try {
		data = JSON.parse( text );
	} catch {
		return null;
	}
	if ( ! data || data[ MARKER ] !== VERSION ) {
		return null;
	}
	if ( data.kind === 'style' ) {
		if (
			typeof data.blockName !== 'string' ||
			! Array.isArray( data.keys ) ||
			! data.attributes ||
			typeof data.attributes !== 'object' ||
			Array.isArray( data.attributes )
		) {
			return null;
		}
		return data;
	}
	if ( data.kind === 'section' && typeof data.content === 'string' ) {
		return data;
	}
	return null;
}

/**
 * The attribute update that pastes a copied style onto a target block.
 *
 * Same block type: every style key of the source is set (a key the source had unset is cleared
 * on the target too — pasting a style replaces the style). Different type: only the shared style
 * keys, and only where the target declares the key as style with a compatible type.
 *
 * @param {Object} payload    A readClipboard() style payload.
 * @param {string} targetName Target block name.
 * @param {Object} targetType Target block type.
 * @return {Object|null} Attributes to set, or null when nothing applies.
 */
export function styleUpdateFor( payload, targetName, targetType ) {
	if ( ! payload || payload.kind !== 'style' ) {
		return null;
	}
	const defs = targetType?.attributes || {};
	const targetKeys = new Set( styleKeysOf( targetType ) );
	const same = payload.blockName === targetName;
	const keys = same
		? payload.keys.filter( ( k ) => targetKeys.has( k ) )
		: SHARED_STYLE_KEYS.filter(
				( k ) =>
					targetKeys.has( k ) && payload.attributes[ k ] !== undefined
			);
	const update = {};
	for ( const key of keys ) {
		const value = payload.attributes[ key ];
		if ( fitsAttribute( value, defs[ key ] ) ) {
			update[ key ] = value;
		}
	}
	return Object.keys( update ).length ? update : null;
}
