/**
 * The JavaScript twin of \ZinnDigital\PBS\Core\Svg::sanitize (includes/core/class-svg.php).
 *
 * Same allow-list, same rules, same canonical output: the input is PARSED as XML and a NEW
 * document is written from the parts that pass. The two are held together by one fixture,
 * wp/tests/fixtures/pbs-icons/svg-cases.json — PHPUnit asserts the PHP output, vitest asserts
 * this output, against the same expected bytes.
 *
 * ⭐ Used in the editor so the icon an author picks or uploads is stored already clean; the
 * server sanitises again on every render and on upload, so this is never the only guard.
 */

const NS = 'http://www.w3.org/2000/svg';
const XLINK = 'http://www.w3.org/1999/xlink';

/** Allowed elements: lower-case local name => canonical spelling. */
export const ELEMENTS = {
	svg: 'svg',
	g: 'g',
	path: 'path',
	circle: 'circle',
	ellipse: 'ellipse',
	line: 'line',
	polyline: 'polyline',
	polygon: 'polygon',
	rect: 'rect',
	title: 'title',
	desc: 'desc',
	defs: 'defs',
	symbol: 'symbol',
	use: 'use',
	lineargradient: 'linearGradient',
	radialgradient: 'radialGradient',
	stop: 'stop',
	clippath: 'clipPath',
	mask: 'mask',
	text: 'text',
	tspan: 'tspan',
};

/** Allowed attributes: lower-case name => canonical spelling. */
export const ATTRIBUTES = {
	viewbox: 'viewBox',
	preserveaspectratio: 'preserveAspectRatio',
	width: 'width',
	height: 'height',
	x: 'x',
	y: 'y',
	x1: 'x1',
	y1: 'y1',
	x2: 'x2',
	y2: 'y2',
	cx: 'cx',
	cy: 'cy',
	r: 'r',
	rx: 'rx',
	ry: 'ry',
	fx: 'fx',
	fy: 'fy',
	d: 'd',
	points: 'points',
	transform: 'transform',
	fill: 'fill',
	'fill-opacity': 'fill-opacity',
	'fill-rule': 'fill-rule',
	'clip-rule': 'clip-rule',
	'clip-path': 'clip-path',
	mask: 'mask',
	stroke: 'stroke',
	'stroke-width': 'stroke-width',
	'stroke-linecap': 'stroke-linecap',
	'stroke-linejoin': 'stroke-linejoin',
	'stroke-miterlimit': 'stroke-miterlimit',
	'stroke-dasharray': 'stroke-dasharray',
	'stroke-dashoffset': 'stroke-dashoffset',
	'stroke-opacity': 'stroke-opacity',
	opacity: 'opacity',
	offset: 'offset',
	'stop-color': 'stop-color',
	'stop-opacity': 'stop-opacity',
	gradientunits: 'gradientUnits',
	gradienttransform: 'gradientTransform',
	spreadmethod: 'spreadMethod',
	clippathunits: 'clipPathUnits',
	maskunits: 'maskUnits',
	'font-size': 'font-size',
	'font-family': 'font-family',
	'font-weight': 'font-weight',
	'text-anchor': 'text-anchor',
	'dominant-baseline': 'dominant-baseline',
	id: 'id',
	role: 'role',
	'aria-hidden': 'aria-hidden',
	'aria-label': 'aria-label',
	focusable: 'focusable',
	style: 'style',
	href: 'href',
	'xlink:href': 'xlink:href',
};

const PAINT = [ 'fill', 'stroke', 'clip-path', 'mask' ];
const TEXT_PARENTS = [ 'title', 'desc', 'text', 'tspan' ];

/**
 * PHP's htmlspecialchars( ENT_QUOTES | ENT_XML1 ).
 *
 * @param {string} value Raw text.
 * @return {string} Escaped text.
 */
function esc( value ) {
	return value
		.replace( /&/g, '&amp;' )
		.replace( /</g, '&lt;' )
		.replace( />/g, '&gt;' )
		.replace( /"/g, '&quot;' )
		.replace( /'/g, '&apos;' );
}

/**
 * PHP's trim(): the characters " \t\n\r\0\x0B".
 *
 * @param {string} value Value.
 * @return {string} Trimmed value.
 */
function phpTrim( value ) {
	// PHP trim()'s own character list, control characters included on purpose.
	// eslint-disable-next-line no-control-regex
	return value.replace( /^[ \t\n\r\0\x0B]+|[ \t\n\r\0\x0B]+$/g, '' );
}

function attributeName( attr ) {
	const local = String( attr.localName ).toLowerCase();
	const ns = attr.namespaceURI;
	if ( ns === XLINK ) {
		return local === 'href' ? 'xlink:href' : null;
	}
	if ( ns ) {
		return null;
	}
	if ( local === 'xlink:href' ) {
		return null;
	}
	return Object.prototype.hasOwnProperty.call( ATTRIBUTES, local )
		? local
		: null;
}

function attributeValue( key, raw ) {
	const value = phpTrim( raw );

	// Browsers ignore C0 controls and spaces inside a scheme, so they are squashed before the check.
	// eslint-disable-next-line no-control-regex
	const squashed = value.replace( /[\x00-\x20]+/g, '' ).toLowerCase();
	if (
		squashed.includes( 'javascript:' ) ||
		squashed.includes( 'data:' ) ||
		squashed.includes( 'vbscript:' )
	) {
		return null;
	}
	if ( key === 'href' || key === 'xlink:href' ) {
		return /^#[A-Za-z_][\w.:-]*$/.test( value ) ? value : null;
	}
	if ( key === 'style' ) {
		return /url\s*\(|expression|@import|behavior|-moz-binding|[\\<>{}]/i.test(
			value
		)
			? null
			: value;
	}
	if ( squashed.includes( 'url(' ) ) {
		if (
			PAINT.includes( key ) &&
			/^url\(\s*['"]?#[A-Za-z_][\w.:-]*['"]?\s*\)(\s+[#\w(),.%\s-]*)?$/.test(
				value
			)
		) {
			return value;
		}
		return null;
	}
	return value;
}

function element( el, root ) {
	const name = String( el.localName ).toLowerCase();
	const ns = el.namespaceURI;
	if (
		! Object.prototype.hasOwnProperty.call( ELEMENTS, name ) ||
		( ns && ns !== NS )
	) {
		return '';
	}
	const tag = ELEMENTS[ name ];
	const attrs = new Map();
	if ( root ) {
		attrs.set( 'xmlns', NS );
	}
	for ( const attr of Array.from( el.attributes || [] ) ) {
		const key = attributeName( attr );
		if ( key === null ) {
			continue;
		}
		const value = attributeValue( key, String( attr.value ) );
		if ( value !== null ) {
			attrs.set( ATTRIBUTES[ key ], value );
		}
	}
	if ( attrs.has( 'xlink:href' ) ) {
		attrs.set( 'xmlns:xlink', XLINK );
	}
	let children = '';
	for ( const child of Array.from( el.childNodes ) ) {
		if ( child.nodeType === 1 ) {
			children += element( child, false );
		} else if ( child.nodeType === 3 && TEXT_PARENTS.includes( name ) ) {
			children += esc( child.data );
		}
		// Comments, processing instructions and CDATA (nodeType 4) are never copied.
	}
	let out = '<' + tag;
	for ( const [ key, value ] of attrs ) {
		out += ` ${ key }="${ esc( value ) }"`;
	}
	return children === '' ? out + '/>' : `${ out }>${ children }</${ tag }>`;
}

/**
 * Sanitise an SVG document. Returns '' when the input is not a well-formed SVG.
 *
 * @param {string} input Untrusted SVG markup.
 * @return {string} Clean, canonical SVG, or ''.
 */
export function sanitizeSvg( input ) {
	let svg = phpTrim( String( input ?? '' ) );
	if ( svg === '' || new TextEncoder().encode( svg ).length > 512000 ) {
		return '';
	}
	if ( /<!DOCTYPE/i.test( svg ) || /<!ENTITY/i.test( svg ) ) {
		return '';
	}
	svg = svg.replace( /^<\?xml[^>]*\?>\s*/i, '' );
	let doc;
	try {
		doc = new window.DOMParser().parseFromString( svg, 'application/xml' );
	} catch {
		return '';
	}
	const rootEl = doc && doc.documentElement;
	if (
		! rootEl ||
		doc.getElementsByTagName( 'parsererror' ).length > 0 ||
		String( rootEl.localName ).toLowerCase() !== 'svg'
	) {
		return '';
	}
	return element( rootEl, true );
}

/**
 * Sanitise an uploaded SVG and size it 1em (like the set icons), keeping its drawing: a
 * width/height pair with no viewBox becomes the viewBox, so the icon scales instead of cropping.
 *
 * @param {string} input SVG markup.
 * @return {string} Sanitised, 1em-sized SVG, or ''.
 */
export function sizedSvg( input ) {
	const clean = sanitizeSvg( input );
	const end = clean.indexOf( '>' );
	if ( ! clean || end < 0 ) {
		return '';
	}
	let head = clean.slice( 0, end );
	const tail = clean.slice( end );
	const selfClosing = head.endsWith( '/' );
	if ( selfClosing ) {
		head = head.slice( 0, -1 );
	}
	// A plain search, not a RegExp built from the name (semgrep detect-non-literal-regexp).
	const read = ( name ) => {
		const at = head.indexOf( ` ${ name }="` );
		if ( at < 0 ) {
			return '';
		}
		const from = at + name.length + 3;
		const to = head.indexOf( '"', from );
		return to < 0 ? '' : head.slice( from, to );
	};
	if ( ! read( 'viewBox' ) ) {
		const w = parseFloat( read( 'width' ) );
		const h = parseFloat( read( 'height' ) );
		if ( w > 0 && h > 0 ) {
			head += ` viewBox="0 0 ${ w } ${ h }"`;
		}
	}
	head = head.replace( / (width|height)="[^"]*"/g, '' );
	head += ' width="1em" height="1em"';
	return sanitizeSvg( head + ( selfClosing ? '/' : '' ) + tail );
}

/**
 * Build a full SVG from a set's root attributes and one icon body, sized 1em and coloured with
 * currentColor, then sanitise it.
 *
 * @param {Object} root Set root attributes (viewBox, fill, stroke…).
 * @param {string} body Inner markup, optionally prefixed "<viewBox>|".
 * @return {string} Sanitised SVG.
 */
export function iconSvg( root, body ) {
	let inner = body;
	let viewBox = root.viewBox;
	const bar = body.indexOf( '|' );
	if ( bar > 0 && body[ 0 ] !== '<' ) {
		viewBox = body.slice( 0, bar );
		inner = body.slice( bar + 1 );
	}
	const attrs = { ...root, viewBox, width: '1em', height: '1em' };
	const order = Object.keys( attrs ).sort();
	const head = order
		.map( ( key ) => `${ key }="${ esc( String( attrs[ key ] ) ) }"` )
		.join( ' ' );
	return sanitizeSvg( `<svg xmlns="${ NS }" ${ head }>${ inner }</svg>` );
}
