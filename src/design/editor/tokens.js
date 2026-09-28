/**
 * Token choices for the pickers (pbs-p4): the theme's theme.json presets, plus the Pro design
 * tokens from `pbsw_tokens` when the premium layer has saved any (read defensively: a list of
 * `{slug, label?, value?}` or an object `slug → value`).
 */
import {
	store as blockEditorStore,
	useSettings,
} from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';

import { config } from '../store';

/**
 * Normalise a preset or token list.
 *
 * @param {unknown} list Presets.
 * @return {Array<{slug:string,label:string,value:string}>} Items.
 */
export function normalise( list ) {
	if ( ! list ) {
		return [];
	}
	if ( ! Array.isArray( list ) && typeof list === 'object' ) {
		// theme.json origins: { default: [...], theme: [...], custom: [...] }.
		if ( list.theme || list.default || list.custom ) {
			return [
				...normalise( list.custom ),
				...normalise( list.theme ),
				...normalise( list.default ),
			];
		}
		return Object.entries( list ).map( ( [ slug, value ] ) => ( {
			slug,
			label: slug,
			value: String( value ),
		} ) );
	}
	return list
		.filter( ( p ) => p && typeof p.slug === 'string' )
		.map( ( p ) => ( {
			slug: p.slug,
			label: p.name || p.label || p.slug,
			value: String(
				p.color ?? p.size ?? p.value ?? p.fontFamily ?? p.shadow ?? ''
			),
		} ) );
}

const seenOnce = ( items ) => {
	const seen = new Set();
	return items.filter( ( i ) =>
		seen.has( i.slug ) ? false : seen.add( i.slug ) && true
	);
};

/**
 * The Pro design data (`pbswDesign`: tokens, variables, classes) the server adds to the editor
 * settings. On the post editor screen it stays on core/editor: core copies only a fixed list of
 * keys from there into core/block-editor. The site editor and the Site design page put it on
 * core/block-editor. So read core/editor first, then core/block-editor.
 *
 * @param {(name: string) => Object|undefined} select Registry select.
 * @return {Object|undefined} The data, if the premium layer sent any.
 */
export function designData( select ) {
	return (
		select( 'core/editor' )?.getEditorSettings?.()?.pbswDesign ||
		select( blockEditorStore )?.getSettings?.()?.pbswDesign
	);
}

/**
 * Token options for the given types, as `{ t, v, label, value }`.
 *
 * @param {string[]} types Token types the control accepts.
 * @return {Array<Object>} Options.
 */
export function useTokenOptions( types ) {
	const [ palette, fontSizes, spacing, families, shadows ] = useSettings(
		'color.palette',
		'typography.fontSizes',
		'spacing.spacingSizes',
		'typography.fontFamilies',
		'shadow.presets'
	);
	// Pro tokens and variables: the live editor setting the Site design panel refreshes
	// (W2, `pbswDesign`), else what the page was printed with (`pbsw_tokens`).
	const live = useSelect( designData, [] );
	const pro = live?.tokens || config().tokens || {};
	const out = [];
	const add = ( t, items ) =>
		seenOnce( items ).forEach( ( i ) =>
			out.push( { t, v: i.slug, label: i.label, value: i.value } )
		);
	if ( types.includes( 'color' ) ) {
		add( 'color', normalise( palette ) );
	}
	if ( types.includes( 'size' ) ) {
		add( 'size', normalise( fontSizes ) );
	}
	if ( types.includes( 'space' ) ) {
		add( 'space', [ ...normalise( pro.space ), ...normalise( spacing ) ] );
	}
	if ( types.includes( 'font' ) ) {
		add( 'font', [ ...normalise( pro.font ), ...normalise( families ) ] );
	}
	if ( types.includes( 'shadow' ) ) {
		add( 'shadow', [
			...normalise( pro.shadow ),
			...normalise( shadows ),
		] );
	}
	if ( types.includes( 'radius' ) ) {
		add( 'radius', normalise( pro.radius ) );
	}
	if ( types.includes( 'var' ) ) {
		add( 'var', normalise( live?.variables ) );
	}
	return out.filter( ( o ) => /^[a-z0-9][a-z0-9-]{0,63}$/.test( o.v ) );
}

/**
 * Is a value a token reference?
 *
 * @param {unknown} v Value.
 * @return {boolean} Token.
 */
export const isToken = ( v ) =>
	!! v && typeof v === 'object' && ! Array.isArray( v ) && 't' in v;
