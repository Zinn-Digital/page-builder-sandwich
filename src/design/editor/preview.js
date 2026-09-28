/**
 * The canvas preview (pbs-p4): each styled block's rules for the breakpoint being edited, applied
 * without media queries (so the canvas shows what a visitor at that width sees, whatever width the
 * canvas is), in a <style> inside the canvas document — the editor iframe when there is one.
 * Editor only: the front end gets the same rules from the page sheet.
 */
import { useEffect } from '@wordpress/element';
import { useSelect } from '@wordpress/data';

import { STORE, config } from '../store';
import { cascade } from '../breakpoints';
import { previewCss } from '../compiler';

/**
 * The documents the block canvas may live in.
 *
 * @return {Document[]} Documents.
 */
export function canvasDocuments() {
	const docs = [];
	document
		.querySelectorAll( 'iframe[name="editor-canvas"]' )
		.forEach( ( frame ) => {
			try {
				if ( frame.contentDocument?.head ) {
					docs.push( frame.contentDocument );
				}
			} catch {
				// A cross-origin frame is not ours.
			}
		} );
	return docs.length ? docs : [ document ];
}

/**
 * Put (or remove, when css is '') one block's preview style in every canvas document.
 *
 * @param {string} clientId Block client id.
 * @param {string} css      CSS.
 */
export function applyPreview( clientId, css ) {
	for ( const doc of canvasDocuments() ) {
		let el = doc.head.querySelector(
			`style[data-pbsw-design="${ clientId }"]`
		);
		if ( ! css ) {
			el?.remove();
			continue;
		}
		if ( ! el ) {
			el = doc.createElement( 'style' );
			el.setAttribute( 'data-pbsw-design', clientId );
			doc.head.appendChild( el );
		}
		if ( el.textContent !== css ) {
			el.textContent = css;
		}
	}
}

/**
 * The preview CSS for a block (hidden-here blocks are dimmed, not removed, so they stay
 * selectable).
 *
 * @param {Object}  block {name, attributes}.
 * @param {string}  bp    Breakpoint being previewed.
 * @param {boolean} hover Show hover values as if hovered.
 * @return {string} CSS.
 */
export function blockPreviewCss( block, bp, hover ) {
	const { prefix, breakpoints } = config();
	const pbs = block.attributes?.pbs;
	if ( ! pbs?.id ) {
		return '';
	}
	let css = previewCss(
		{ blockName: block.name, attrs: block.attributes },
		prefix,
		cascade( bp, breakpoints ),
		hover
	);
	if ( Array.isArray( pbs.hide ) && pbs.hide.includes( bp ) ) {
		css += `.${ prefix }-s-${ pbs.id }{opacity:.35!important;outline:1px dashed currentColor}`;
	}
	return css;
}

/**
 * Renders nothing; keeps the block's preview style current.
 *
 * @param {Object}  props
 * @param {string}  props.clientId   Block client id.
 * @param {string}  props.name       Block name.
 * @param {Object}  props.attributes Attributes.
 * @param {boolean} props.isSelected Selected.
 * @return {null} Nothing.
 */
export default function DesignPreview( {
	clientId,
	name,
	attributes,
	isSelected,
} ) {
	const { bp, hover } = useSelect(
		( select ) => ( {
			bp: select( STORE ).getBreakpoint(),
			hover: select( STORE ).isHover(),
		} ),
		[]
	);
	const css = blockPreviewCss(
		{ name, attributes },
		bp,
		hover && isSelected
	);
	useEffect( () => {
		applyPreview( clientId, css );
	}, [ clientId, css ] );
	useEffect( () => () => applyPreview( clientId, '' ), [ clientId ] );
	return null;
}
