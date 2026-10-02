/**
 * The style compiler in the editor (pbs-p4). Twin of includes/design/class-compiler.php:
 * compile() produces byte-for-byte the CSS the server writes into the page sheet
 * (wp/tests/fixtures/pbs-design/compiler.json holds both sides to it); previewCss() is the
 * editor-only variant that applies one breakpoint's cascade without media queries, so the canvas
 * shows exactly what a visitor at that width sees whatever width the canvas happens to be.
 */
import { PROPS } from './props';
import { appliesTo } from './kinds';
import { media, mediaList, range } from './breakpoints';

export const ID_PATTERN = /^[a-z0-9]{8}$/;

const isObject = ( v ) =>
	v !== null && typeof v === 'object' && ! Array.isArray( v );

/**
 * Declarations for one breakpoint's values, split by target, in registry order.
 *
 * @param {Object} values    Stored values.
 * @param {string} prefix    Prefix.
 * @param {string} blockName Block name.
 * @return {{own:string,layout:string,all:string}} Declarations.
 */
export function declarations( values, prefix, blockName = '' ) {
	const out = { own: [], layout: [], all: [] };
	for ( const prop of PROPS ) {
		if (
			! Object.prototype.hasOwnProperty.call( values, prop.key ) ||
			! appliesTo( prop, blockName )
		) {
			continue;
		}
		const clean = prop.sanitize( values[ prop.key ] );
		if ( clean === null ) {
			continue;
		}
		const target = prop.target === 'layout' ? 'layout' : 'own';
		for ( const [ property, value ] of Object.entries(
			prop.toCss( clean, prefix )
		) ) {
			out[ target ].push( `${ property }:${ value }` );
			out.all.push( `${ property }:${ value }` );
		}
	}
	return {
		own: out.own.join( ';' ),
		layout: out.layout.join( ';' ),
		all: out.all.join( ';' ),
	};
}

/**
 * The selector suffix of the element that lays out a block's children.
 *
 * @param {Object} block  Parsed block {blockName, attrs}.
 * @param {string} prefix Prefix.
 * @return {string} Suffix.
 */
export function layoutSuffix( block, prefix ) {
	const name = block.blockName || '';
	const width =
		block.attrs?.width ?? ( name === 'pbs/section' ? 'boxed' : 'full' );
	if (
		( name === 'pbs/section' || name === 'pbs/container' ) &&
		width === 'boxed'
	) {
		return `>.${ prefix }-${ name.slice( 4 ) }__in`;
	}
	return '';
}

/**
 * A block's own selector: its design class twice (specificity 0,2,0), the twin of
 * Compiler::selector(), so a theme rule scoped to the content area cannot override a Style value.
 *
 * @param {string} prefix Prefix.
 * @param {string} id     Design id.
 * @return {string} Selector.
 */
export function blockSelector( prefix, id ) {
	const cls = `.${ prefix }-s-${ id }`;
	return cls + cls;
}

/**
 * One block's rules per breakpoint (normal then hover), without media wrappers.
 *
 * @param {Object}   block  Parsed block.
 * @param {string}   prefix Prefix.
 * @param {string[]} ids    Known ids.
 * @return {Object} id → rules.
 */
export function blockRules( block, prefix, ids ) {
	const pbs = block.attrs?.pbs;
	const id = isObject( pbs ) && typeof pbs.id === 'string' ? pbs.id : '';
	if ( ! ID_PATTERN.test( id ) || ! isObject( pbs.s ) ) {
		return {};
	}
	const selector = blockSelector( prefix, id );
	const suffix = layoutSuffix( block, prefix );
	const out = {};
	for ( const bp of ids ) {
		let css = '';
		for ( const pseudo of [ '', ':hover' ] ) {
			const values = pbs.s[ bp + pseudo ];
			if ( ! isObject( values ) ) {
				continue;
			}
			const d = declarations( values, prefix, block.blockName || '' );
			if ( suffix === '' ) {
				css += d.all ? `${ selector }${ pseudo }{${ d.all }}` : '';
				continue;
			}
			if ( d.own ) {
				css += `${ selector }${ pseudo }{${ d.own }}`;
			}
			if ( d.layout ) {
				css += `${ selector }${ pseudo }${ suffix }{${ d.layout }}`;
			}
		}
		if ( css ) {
			out[ bp ] = css;
		}
	}
	return out;
}

/**
 * Valid breakpoint ids a block hides on.
 *
 * @param {Object}   pbs `pbs` attribute.
 * @param {string[]} ids Known ids.
 * @return {string[]} Ids.
 */
export function hides( pbs, ids ) {
	const hide = Array.isArray( pbs?.hide ) ? pbs.hide : [];
	return [
		...new Set(
			hide.filter( ( b ) => typeof b === 'string' && ids.includes( b ) )
		),
	];
}

/**
 * Compile a parsed block tree, exactly as Compiler::css() does (custom element CSS is Pro and
 * compiled by the server only).
 *
 * @param {Array<Object>} blocks Parsed blocks {blockName, attrs, innerBlocks}.
 * @param {string}        prefix Prefix.
 * @param {Object}        config Breakpoints option value.
 * @return {string} CSS.
 */
export function compile( blocks, prefix, config ) {
	const list = mediaList( config );
	const ids = [ 'base', ...list.map( ( b ) => b.id ) ];
	const rules = Object.fromEntries( ids.map( ( id ) => [ id, '' ] ) );
	const hidden = new Set();
	const seen = new Set();

	const walk = ( tree ) => {
		for ( const block of tree || [] ) {
			if ( ! isObject( block ) ) {
				continue;
			}
			const pbs = block.attrs?.pbs;
			if ( isObject( pbs ) ) {
				hides( pbs, ids ).forEach( ( b ) => hidden.add( b ) );
				const id = typeof pbs.id === 'string' ? pbs.id : '';
				if ( ID_PATTERN.test( id ) && ! seen.has( id ) ) {
					seen.add( id );
					for ( const [ bp, css ] of Object.entries(
						blockRules( block, prefix, ids )
					) ) {
						rules[ bp ] += css;
					}
				}
			}
			walk( block.innerBlocks );
		}
	};
	walk( blocks );

	let css = rules.base;
	for ( const bp of list ) {
		if ( rules[ bp.id ] ) {
			css += `@media ${ media( bp ) }{${ rules[ bp.id ] }}`;
		}
	}
	for ( const id of ids ) {
		if ( hidden.has( id ) ) {
			const r = range( id, list ) || '';
			const rule = `.${ prefix }-hide-${ id }{display:none!important}`;
			css += r === '' ? rule : `@media ${ r }{${ rule }}`;
		}
	}
	if ( css.includes( 'transition-duration:' ) ) {
		css += `@media (prefers-reduced-motion:reduce){[class*="${ prefix }-s-"]{transition:none!important}}`;
	}
	return css;
}

/**
 * Does a block have anything compile() would write for it?
 *
 * @param {Object}   block  Parsed block.
 * @param {string}   prefix Prefix.
 * @param {string[]} ids    Known ids.
 * @return {boolean} Has styles.
 */
export const hasStyles = ( block, prefix, ids ) =>
	Object.keys( blockRules( block, prefix, ids ) ).length > 0;

/**
 * Editor preview for one block: the cascade of `bpIds` (base first) with no media queries, hover
 * rules kept on :hover — and, when `hover` is true (the panel is editing the hover state), also
 * applied without it, so the author sees what they are editing.
 *
 * @param {Object}   block  Parsed block.
 * @param {string}   prefix Prefix.
 * @param {string[]} bpIds  cascade() output.
 * @param {boolean}  hover  Show hover values as if hovered.
 * @return {string} CSS.
 */
export function previewCss( block, prefix, bpIds, hover = false ) {
	const pbs = block.attrs?.pbs;
	if ( ! isObject( pbs ) || ! ID_PATTERN.test( pbs.id || '' ) ) {
		return '';
	}
	const selector = blockSelector( prefix, pbs.id );
	const suffix = layoutSuffix( block, prefix );
	let css = '';
	for ( const pseudo of hover
		? [ '', ':hover', 'forced' ]
		: [ '', ':hover' ] ) {
		for ( const bp of bpIds ) {
			const values =
				pbs.s?.[ bp + ( pseudo === 'forced' ? ':hover' : pseudo ) ];
			if ( ! isObject( values ) ) {
				continue;
			}
			const sel = selector + ( pseudo === ':hover' ? ':hover' : '' );
			const d = declarations( values, prefix, block.blockName || '' );
			if ( suffix === '' ) {
				css += d.all ? `${ sel }{${ d.all }}` : '';
			} else {
				css += d.own ? `${ sel }{${ d.own }}` : '';
				css += d.layout ? `${ sel }${ suffix }{${ d.layout }}` : '';
			}
		}
	}
	return css;
}

/**
 * A block-editor block ({name, attributes, innerBlocks}) in parse_blocks() shape.
 *
 * @param {Object} block Editor block.
 * @return {Object} Parsed-shape block.
 */
export const toParsed = ( block ) => ( {
	blockName: block.name,
	attrs: block.attributes || {},
	innerBlocks: ( block.innerBlocks || [] ).map( toParsed ),
} );
