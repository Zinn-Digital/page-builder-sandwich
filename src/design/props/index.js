/**
 * The design prop registry (pbs-p4), in output order. Twin of includes/design/class-props.php,
 * which lists the same props in the same order (PbsDesignTest asserts it).
 *
 * ⭐ To add a prop: add its file here and ONE import + ONE entry below (and the PHP twin).
 */
import displayProp from './display';
import flexDirectionProp from './flex-direction';
import flexWrapProp from './flex-wrap';
import justifyContentProp from './justify-content';
import alignItemsProp from './align-items';
import gridColumnsProp from './grid-columns';
import gridRowsProp from './grid-rows';
import gridAreasProp from './grid-areas';
import placeItemsProp from './place-items';
import gapProp from './gap';
import contentWidthProp from './content-width';
import alignSelfProp from './align-self';
import colSpanProp from './col-span';
import rowSpanProp from './row-span';
import gridAreaProp from './grid-area';
import flexGrowProp from './flex-grow';
import flexShrinkProp from './flex-shrink';
import flexBasisProp from './flex-basis';
import orderProp from './order';
import paddingProp from './padding';
import marginProp from './margin';
import widthProp from './width';
import minWidthProp from './min-width';
import maxWidthProp from './max-width';
import heightProp from './height';
import minHeightProp from './min-height';
import maxHeightProp from './max-height';
import aspectRatioProp from './aspect-ratio';
import fontFamilyProp from './font-family';
import fontSizeProp from './font-size';
import fontWeightProp from './font-weight';
import fontStyleProp from './font-style';
import lineHeightProp from './line-height';
import letterSpacingProp from './letter-spacing';
import textTransformProp from './text-transform';
import textDecorationProp from './text-decoration';
import textAlignProp from './text-align';
import colorProp from './color';
import backgroundColorProp from './background-color';
import backgroundImageProp from './background-image';
import borderProp from './border';
import radiusProp from './radius';
import boxShadowProp from './box-shadow';
import textShadowProp from './text-shadow';
import positionProp from './position';
import insetProp from './inset';
import zIndexProp from './z-index';
import overflowProp from './overflow';
import opacityProp from './opacity';
import transitionProp from './transition';
import cursorProp from './cursor';

export const PROPS = [
	displayProp,
	flexDirectionProp,
	flexWrapProp,
	justifyContentProp,
	alignItemsProp,
	gridColumnsProp,
	gridRowsProp,
	gridAreasProp,
	placeItemsProp,
	gapProp,
	contentWidthProp,
	alignSelfProp,
	colSpanProp,
	rowSpanProp,
	gridAreaProp,
	flexGrowProp,
	flexShrinkProp,
	flexBasisProp,
	orderProp,
	paddingProp,
	marginProp,
	widthProp,
	minWidthProp,
	maxWidthProp,
	heightProp,
	minHeightProp,
	maxHeightProp,
	aspectRatioProp,
	fontFamilyProp,
	fontSizeProp,
	fontWeightProp,
	fontStyleProp,
	lineHeightProp,
	letterSpacingProp,
	textTransformProp,
	textDecorationProp,
	textAlignProp,
	colorProp,
	backgroundColorProp,
	backgroundImageProp,
	borderProp,
	radiusProp,
	boxShadowProp,
	textShadowProp,
	positionProp,
	insetProp,
	zIndexProp,
	overflowProp,
	opacityProp,
	transitionProp,
	cursorProp,
];

/** Key → prop. */
export const PROP_MAP = Object.fromEntries(
	PROPS.map( ( p ) => [ p.key, p ] )
);

/**
 * Add props from another bundle (the Pro effects, pbs-r14) AFTER the free ones, as the PHP
 * registry does through the `pbsw_design_props` filter. A key already present is ignored.
 *
 * Bundles are separate webpack entries, so they meet through one global: a bundle that loads
 * first queues its props in `window.pbswDesignProps`; this registry drains the queue when it loads
 * and replaces `push` so anything queued later registers at once.
 *
 * @param {Object[]} list Props.
 */
export function registerProps( list ) {
	for ( const prop of list || [] ) {
		if ( prop && prop.key && ! PROP_MAP[ prop.key ] ) {
			PROPS.push( prop );
			PROP_MAP[ prop.key ] = prop;
		}
	}
}

if ( typeof window !== 'undefined' ) {
	const queued = Array.isArray( window.pbswDesignProps )
		? window.pbswDesignProps
		: [];
	queued.forEach( ( list ) => registerProps( list ) );
	window.pbswDesignProps = { push: ( list ) => registerProps( list ) };
}
