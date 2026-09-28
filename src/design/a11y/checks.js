/**
 * The accessibility checker's rules (pbs-a1). Pure: every rule reads either the block tree or a
 * DOM the caller hands in (the editor canvas), and returns findings. Unit-tested in
 * __tests__/checks.test.js against jsdom and against hand-made block trees.
 *
 * A finding: { id, rule, severity ('error'|'warning'), clientId, message, fix? }
 * `fix` describes an action the sidebar can apply: { type, ... } (see fixes.js).
 */
import { __, sprintf } from '@wordpress/i18n';

// ── Colour ──────────────────────────────────────────────────────────────────────────────────

/**
 * Parse a computed colour (`rgb(…)` / `rgba(…)`, the forms getComputedStyle returns, both the
 * comma and the space syntax) or a hex colour.
 *
 * @param {string} value Colour.
 * @return {{r:number,g:number,b:number,a:number}|null} Channels 0-255, alpha 0-1.
 */
export function parseColor( value ) {
	const v = String( value || '' )
		.trim()
		.toLowerCase();
	let m = v.match( /^#([0-9a-f]{3,8})$/ );
	if ( m ) {
		let hex = m[ 1 ];
		if ( hex.length === 3 || hex.length === 4 ) {
			hex = hex
				.split( '' )
				.map( ( c ) => c + c )
				.join( '' );
		}
		if ( hex.length !== 6 && hex.length !== 8 ) {
			return null;
		}
		const n = ( i ) => parseInt( hex.slice( i, i + 2 ), 16 );
		return {
			r: n( 0 ),
			g: n( 2 ),
			b: n( 4 ),
			a: hex.length === 8 ? n( 6 ) / 255 : 1,
		};
	}
	m = v.match(
		/^rgba?\(\s*([\d.]+)[\s,]+([\d.]+)[\s,]+([\d.]+)(?:\s*[,/]\s*([\d.]+%?))?\s*\)$/
	);
	if ( m ) {
		let a = 1;
		if ( m[ 4 ] !== undefined ) {
			a = m[ 4 ].endsWith( '%' )
				? parseFloat( m[ 4 ] ) / 100
				: parseFloat( m[ 4 ] );
		}
		return { r: +m[ 1 ], g: +m[ 2 ], b: +m[ 3 ], a };
	}
	if ( v === 'transparent' ) {
		return { r: 0, g: 0, b: 0, a: 0 };
	}
	return null;
}

/**
 * Put `top` (possibly translucent) over an opaque `bottom`.
 *
 * @param {Object} top    Colour.
 * @param {Object} bottom Opaque colour.
 * @return {Object} Opaque colour.
 */
export function composite( top, bottom ) {
	const a = top.a;
	return {
		r: top.r * a + bottom.r * ( 1 - a ),
		g: top.g * a + bottom.g * ( 1 - a ),
		b: top.b * a + bottom.b * ( 1 - a ),
		a: 1,
	};
}

/**
 * WCAG 2.x relative luminance.
 *
 * @param {Object} c Colour.
 * @return {number} Luminance.
 */
export function luminance( c ) {
	const ch = ( v ) => {
		const s = v / 255;
		return s <= 0.03928
			? s / 12.92
			: Math.pow( ( s + 0.055 ) / 1.055, 2.4 );
	};
	return 0.2126 * ch( c.r ) + 0.7152 * ch( c.g ) + 0.0722 * ch( c.b );
}

/**
 * WCAG contrast ratio of two opaque colours.
 *
 * @param {Object} a Colour.
 * @param {Object} b Colour.
 * @return {number} 1-21.
 */
export function contrastRatio( a, b ) {
	const la = luminance( a );
	const lb = luminance( b );
	return ( Math.max( la, lb ) + 0.05 ) / ( Math.min( la, lb ) + 0.05 );
}

/**
 * The minimum ratio WCAG AA asks of this text: 3 for large text (24px, or 18.66px bold), 4.5
 * otherwise.
 *
 * @param {number} sizePx Font size in px.
 * @param {number} weight Font weight.
 * @return {number} Required ratio.
 */
export function requiredRatio( sizePx, weight ) {
	const large = sizePx >= 24 || ( sizePx >= 18.66 && weight >= 700 );
	return large ? 3 : 4.5;
}

/**
 * The colour actually behind an element: walk up compositing translucent backgrounds until an
 * opaque one (the canvas is white when nothing is). Returns null where a background image or
 * gradient makes the answer unknowable from CSS — the rule then stays silent rather than guess.
 *
 * @param {Element}  el       Element.
 * @param {Function} getStyle `( el ) => CSSStyleDeclaration`.
 * @return {Object|null} Opaque colour, or null when unknowable.
 */
export function effectiveBackground( el, getStyle ) {
	const layers = [];
	for (
		let node = el;
		node && node.nodeType === 1;
		node = node.parentElement
	) {
		const style = getStyle( node );
		if ( style.backgroundImage && style.backgroundImage !== 'none' ) {
			return null;
		}
		const c = parseColor( style.backgroundColor );
		if ( c && c.a > 0 ) {
			layers.push( c );
			if ( c.a >= 1 ) {
				break;
			}
		}
	}
	let out = { r: 255, g: 255, b: 255, a: 1 };
	for ( let i = layers.length - 1; i >= 0; i-- ) {
		out = layers[ i ].a >= 1 ? layers[ i ] : composite( layers[ i ], out );
	}
	return out;
}

// ── DOM helpers ─────────────────────────────────────────────────────────────────────────────

/**
 * The editor block that holds an element (`data-block` = clientId in the block editor canvas).
 *
 * @param {Element} el Element.
 * @return {string|null} clientId.
 */
export function blockOf( el ) {
	const host = el && el.closest ? el.closest( '[data-block]' ) : null;
	if ( ! host ) {
		// Inside a block's own preview frame (the Custom HTML block, embeds): the frame's block.
		const frame = el?.ownerDocument?.defaultView?.frameElement;
		return frame ? blockOf( frame ) : null;
	}
	return host.getAttribute( 'data-block' );
}

/**
 * A frame's document when it is same-origin, else null.
 *
 * @param {HTMLIFrameElement} frame Frame.
 * @return {Document|null} Document.
 */
function frameDocument( frame ) {
	try {
		return frame.contentDocument;
	} catch {
		return null; // Cross-origin: an embed from another site cannot be read, and is not ours.
	}
}

/**
 * Elements matching a selector inside the blocks of a canvas, including inside a block's own
 * same-origin preview frame (the Custom HTML block draws its preview in one).
 *
 * @param {Element} root     Canvas root.
 * @param {string}  selector Selector (may be a comma list).
 * @return {Element[]} Elements.
 */
export function inBlocks( root, selector ) {
	const scoped = selector
		.split( ',' )
		.map( ( s ) => `[data-block] ${ s.trim() }` )
		.join( ',' );
	const out = [ ...root.querySelectorAll( scoped ) ];
	for ( const frame of root.querySelectorAll( '[data-block] iframe' ) ) {
		const doc = frameDocument( frame );
		if ( doc?.body ) {
			out.push( ...doc.body.querySelectorAll( selector ) );
		}
	}
	return out;
}

/**
 * The element's own text (direct text nodes only, trimmed).
 *
 * @param {Element} el Element.
 * @return {string} Text.
 */
function ownText( el ) {
	let text = '';
	for ( const node of el.childNodes ) {
		if ( node.nodeType === 3 ) {
			text += node.textContent;
		}
	}
	return text.trim();
}

/**
 * An element's accessible name, as far as a static reading can tell (aria-labelledby,
 * aria-label, alt of contained images, text, title).
 *
 * @param {Element} el Element.
 * @return {string} Name.
 */
export function accessibleName( el ) {
	const doc = el.ownerDocument;
	const by = el.getAttribute( 'aria-labelledby' );
	if ( by ) {
		const text = by
			.split( /\s+/ )
			.map( ( id ) => doc.getElementById( id )?.textContent || '' )
			.join( ' ' )
			.trim();
		if ( text ) {
			return text;
		}
	}
	const label = ( el.getAttribute( 'aria-label' ) || '' ).trim();
	if ( label ) {
		return label;
	}
	let text = '';
	const walk = ( node ) => {
		for ( const child of node.childNodes ) {
			if ( child.nodeType === 3 ) {
				text += child.textContent;
			} else if ( child.nodeType === 1 ) {
				if ( child.getAttribute( 'aria-hidden' ) === 'true' ) {
					continue;
				}
				if ( child.tagName === 'IMG' ) {
					text += ' ' + ( child.getAttribute( 'alt' ) || '' );
				} else if (
					child.tagName === 'svg' ||
					child.tagName === 'SVG'
				) {
					text +=
						' ' +
						( child.getAttribute( 'aria-label' ) ||
							child.querySelector( 'title' )?.textContent ||
							'' );
				} else {
					walk( child );
				}
			}
		}
	};
	walk( el );
	text = text.replace( /\s+/g, ' ' ).trim();
	return text || ( el.getAttribute( 'title' ) || '' ).trim();
}

let seq = 0;
const finding = ( rule, severity, clientId, message, fix ) => ( {
	id: `${ rule }-${ ++seq }`,
	rule,
	severity,
	clientId,
	message,
	fix,
} );

// ── Rules over the DOM ──────────────────────────────────────────────────────────────────────

/**
 * Text contrast (WCAG 1.4.3): every element with text of its own, against the background it
 * actually sits on. One finding per block (the worst element), so a long paragraph does not
 * produce twenty.
 *
 * @param {Element}  root     The canvas root (only [data-block] descendants are checked).
 * @param {Function} getStyle `( el ) => CSSStyleDeclaration`.
 * @return {Array} Findings.
 */
export function checkContrast( root, getStyle ) {
	const worst = new Map();
	for ( const el of root.querySelectorAll(
		'[data-block], [data-block] *'
	) ) {
		if ( ! ownText( el ) ) {
			continue;
		}
		const style = getStyle( el );
		if ( style.visibility === 'hidden' || style.display === 'none' ) {
			continue;
		}
		const fg = parseColor( style.color );
		const bg = effectiveBackground( el, getStyle );
		if ( ! fg || ! bg ) {
			continue;
		}
		const ratio = contrastRatio( fg.a < 1 ? composite( fg, bg ) : fg, bg );
		const need = requiredRatio(
			parseFloat( style.fontSize ) || 16,
			parseInt( style.fontWeight, 10 ) || 400
		);
		const clientId = blockOf( el );
		if ( ratio < need && clientId ) {
			const prev = worst.get( clientId );
			if ( ! prev || ratio < prev.ratio ) {
				worst.set( clientId, { ratio, need, bg } );
			}
		}
	}
	return [ ...worst.entries() ].map( ( [ clientId, { ratio, need, bg } ] ) =>
		finding(
			'contrast',
			'error',
			clientId,
			sprintf(
				/* translators: 1: measured contrast ratio, 2: required ratio. */
				__(
					'Text contrast is %1$s:1; it needs at least %2$s:1 to be readable.',
					'page-builder-sandwich'
				),
				ratio.toFixed( 2 ),
				need
			),
			{ type: 'contrast', background: bg, need }
		)
	);
}

/** Link texts that say nothing about where the link goes, as a translatable list. */
export function vagueLinkTexts() {
	return __(
		'click here,here,read more,more,link,this link,learn more,go',
		'page-builder-sandwich'
	)
		.split( ',' )
		.map( ( s ) => s.trim().toLowerCase() )
		.filter( Boolean );
}

/**
 * Link text (WCAG 2.4.4): a link must have a name, and the name must say something.
 *
 * @param {Element} root Canvas root.
 * @return {Array} Findings.
 */
export function checkLinks( root ) {
	const vague = vagueLinkTexts();
	const out = [];
	for ( const a of inBlocks( root, 'a[href]' ) ) {
		const clientId = blockOf( a );
		if ( ! clientId ) {
			continue;
		}
		const name = accessibleName( a )
			.toLowerCase()
			.replace( /[.…!?:]+$/, '' );
		if ( ! name ) {
			out.push(
				finding(
					'link-empty',
					'error',
					clientId,
					__(
						'A link has no text, so a screen reader announces only “link”. Add text or a label.',
						'page-builder-sandwich'
					),
					{ type: 'select' }
				)
			);
		} else if ( vague.includes( name ) ) {
			out.push(
				finding(
					'link-vague',
					'warning',
					clientId,
					sprintf(
						/* translators: %s: the link text. */
						__(
							'The link text “%s” does not say where it goes. Describe the destination instead.',
							'page-builder-sandwich'
						),
						name
					),
					{ type: 'select' }
				)
			);
		}
	}
	return out;
}

/**
 * Keyboard access (WCAG 2.1.1, 2.4.3): no positive tabindex (it breaks the reading order), no
 * mouse-only controls (a click handler or role=button on something that cannot take focus),
 * and every iframe has a title.
 *
 * @param {Element} root Canvas root.
 * @return {Array} Findings.
 */
export function checkKeyboard( root ) {
	const out = [];
	const seen = new Set();
	const add = ( el, rule, message ) => {
		const clientId = blockOf( el );
		if ( clientId && ! seen.has( rule + clientId ) ) {
			seen.add( rule + clientId );
			out.push(
				finding( rule, 'error', clientId, message, { type: 'select' } )
			);
		}
	};
	for ( const el of inBlocks( root, '[tabindex]' ) ) {
		if ( parseInt( el.getAttribute( 'tabindex' ), 10 ) > 0 ) {
			add(
				el,
				'tabindex',
				__(
					'An element has a positive tabindex, which changes the keyboard order away from the reading order. Use 0 or remove it.',
					'page-builder-sandwich'
				)
			);
		}
	}
	const focusable = 'a[href],button,input,select,textarea,summary,[tabindex]';
	for ( const el of inBlocks(
		root,
		'[onclick], [role="button"], [role="link"]'
	) ) {
		if ( ! el.matches( focusable ) ) {
			add(
				el,
				'mouse-only',
				__(
					'Something clickable cannot be reached with the keyboard. Use a real button or link.',
					'page-builder-sandwich'
				)
			);
		}
	}
	for ( const el of root.querySelectorAll( '[data-block] iframe' ) ) {
		if (
			! (
				el.getAttribute( 'title' ) ||
				el.getAttribute( 'aria-label' ) ||
				''
			).trim()
		) {
			add(
				el,
				'iframe-title',
				__(
					'An embedded frame has no title, so a screen reader cannot say what it is.',
					'page-builder-sandwich'
				)
			);
		}
	}
	return out;
}

/**
 * Form labels (WCAG 1.3.1, 4.1.2): every field a visitor fills in has a label.
 *
 * @param {Element} root Canvas root.
 * @return {Array} Findings.
 */
export function checkFormLabels( root ) {
	const out = [];
	const seen = new Set();
	for ( const el of inBlocks( root, 'input, select, textarea' ) ) {
		const type = ( el.getAttribute( 'type' ) || '' ).toLowerCase();
		if (
			[ 'hidden', 'submit', 'button', 'reset', 'image' ].includes( type )
		) {
			continue;
		}
		if (
			el.closest( '[contenteditable="true"]' ) ||
			el.closest(
				'.block-editor-block-list__block-edit-toolbar, .components-popover'
			)
		) {
			continue;
		}
		const id = el.getAttribute( 'id' );
		const doc = el.ownerDocument;
		const labelled =
			( el.getAttribute( 'aria-label' ) || '' ).trim() ||
			el.getAttribute( 'aria-labelledby' ) ||
			( el.getAttribute( 'title' ) || '' ).trim() ||
			el.closest( 'label' ) ||
			( id &&
				doc.querySelector(
					`label[for="${ window.CSS.escape( id ) }"]`
				) );
		const clientId = blockOf( el );
		if ( ! labelled && clientId && ! seen.has( clientId ) ) {
			seen.add( clientId );
			out.push(
				finding(
					'form-label',
					'error',
					clientId,
					__(
						'A form field has no label, so its purpose is not announced.',
						'page-builder-sandwich'
					),
					{ type: 'select' }
				)
			);
		}
	}
	return out;
}

// ── Rules over the block tree ───────────────────────────────────────────────────────────────

/**
 * Flatten the block tree in document order.
 *
 * @param {Array} blocks Blocks (`{ clientId, name, attributes, innerBlocks }`).
 * @return {Array} Blocks.
 */
export function flatten( blocks ) {
	const out = [];
	const walk = ( list ) => {
		for ( const b of list || [] ) {
			out.push( b );
			walk( b.innerBlocks );
		}
	};
	walk( blocks );
	return out;
}

/**
 * Heading order (WCAG 1.3.1, 2.4.6): no level skipped on the way down, no empty headings, and
 * no second H1 (the page title is the H1).
 *
 * @param {Array} blocks Block tree.
 * @return {Array} Findings.
 */
export function checkHeadings( blocks ) {
	const out = [];
	let prev = 1; // The post title.
	for ( const b of flatten( blocks ) ) {
		if ( b.name !== 'core/heading' ) {
			continue;
		}
		const level = b.attributes?.level || 2;
		const text = String(
			b.attributes?.content?.text ?? b.attributes?.content ?? ''
		)
			.replace( /<[^>]*>/g, '' )
			.trim();
		if ( ! text ) {
			out.push(
				finding(
					'heading-empty',
					'warning',
					b.clientId,
					__(
						'This heading is empty. Screen reader users navigate by headings; remove it or add text.',
						'page-builder-sandwich'
					),
					{ type: 'select' }
				)
			);
		}
		if ( level === 1 ) {
			out.push(
				finding(
					'heading-h1',
					'warning',
					b.clientId,
					__(
						'The page title is already the H1; use H2 for the top level of the content.',
						'page-builder-sandwich'
					),
					{ type: 'heading-level', level: 2 }
				)
			);
			prev = 2;
			continue;
		}
		if ( level > prev + 1 ) {
			out.push(
				finding(
					'heading-skip',
					'error',
					b.clientId,
					sprintf(
						/* translators: 1: this heading's level, 2: the level before it, 3: the level it should be. */
						__(
							'This H%1$d skips a level; the one before it is H%2$d. Make it an H%3$d.',
							'page-builder-sandwich'
						),
						level,
						prev,
						prev + 1
					),
					{ type: 'heading-level', level: prev + 1 }
				)
			);
			prev += 1;
			continue;
		}
		prev = level;
	}
	return out;
}

/**
 * Image alt text (WCAG 1.1.1): an image carries an alt text, or is marked decorative on purpose
 * (`pbs.decorative`, set by the fix: alt stays "" and the finding stops).
 *
 * @param {Array} blocks Block tree.
 * @return {Array} Findings.
 */
export function checkImages( blocks ) {
	const out = [];
	for ( const b of flatten( blocks ) ) {
		if ( b.name !== 'core/image' || ! b.attributes?.url ) {
			continue;
		}
		const alt = String( b.attributes.alt || '' ).trim();
		if ( ! alt && ! b.attributes.pbs?.decorative ) {
			out.push(
				finding(
					'image-alt',
					'error',
					b.clientId,
					__(
						'This image has no alt text. Describe it, or mark it as decorative if it adds nothing.',
						'page-builder-sandwich'
					),
					{ type: 'image-alt' }
				)
			);
		}
	}
	return out;
}

/**
 * Run every rule.
 *
 * @param {Object}   args
 * @param {Array}    args.blocks   Block tree.
 * @param {Element}  args.root     Canvas root element (may be null: DOM rules are skipped).
 * @param {Function} args.getStyle `( el ) => CSSStyleDeclaration`.
 * @return {Array} Findings, errors first.
 */
export function runChecks( { blocks, root, getStyle } ) {
	const found = [ ...checkHeadings( blocks ), ...checkImages( blocks ) ];
	if ( root ) {
		found.push(
			...checkContrast( root, getStyle ),
			...checkLinks( root ),
			...checkKeyboard( root ),
			...checkFormLabels( root )
		);
	}
	// Custom HTML is checked from its source too: the editor may show it as code, or preview it
	// in a frame that has not loaded yet, so the canvas alone can miss it.
	const raw = rawHtmlRoot( blocks );
	if ( raw ) {
		found.push(
			...checkLinks( raw ),
			...checkKeyboard( raw ),
			...checkFormLabels( raw )
		);
	}
	const seen = new Set();
	const unique = found.filter( ( f ) => {
		const key = `${ f.rule }|${ f.clientId }|${ f.message }`;
		if ( seen.has( key ) ) {
			return false;
		}
		seen.add( key );
		return true;
	} );
	const rank = { error: 0, warning: 1 };
	return unique.sort( ( a, b ) => rank[ a.severity ] - rank[ b.severity ] );
}

/**
 * The Custom HTML blocks' source, parsed into one detached document (scripts never run there),
 * each wrapped in `[data-block=<clientId>]` so the DOM rules attribute what they find.
 *
 * @param {Array} blocks Block tree.
 * @return {Element|null} Root, or null when there is no Custom HTML.
 */
export function rawHtmlRoot( blocks ) {
	const html = flatten( blocks ).filter(
		( b ) => b.name === 'core/html' && b.attributes?.content
	);
	if (
		! html.length ||
		typeof window === 'undefined' ||
		! window.DOMParser
	) {
		return null;
	}
	const doc = new window.DOMParser().parseFromString(
		'<!doctype html><body></body>',
		'text/html'
	);
	for ( const b of html ) {
		const wrap = doc.createElement( 'div' );
		wrap.setAttribute( 'data-block', b.clientId );
		wrap.innerHTML = String( b.attributes.content );
		doc.body.appendChild( wrap );
	}
	return doc.body;
}
