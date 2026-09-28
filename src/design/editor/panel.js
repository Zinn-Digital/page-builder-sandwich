/**
 * The Style tab (pbs-p4): every design prop of the selected block, grouped into sections, edited
 * per breakpoint and per state (normal / hover), with inherited values shown greyed and a reset
 * per prop. Rendered into the block inspector's "Styles" tab, in the block editor and Studio.
 */
import { __, sprintf } from '@wordpress/i18n';
import { useSelect, useDispatch } from '@wordpress/data';
import {
	InspectorControls,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import {
	Button,
	CheckboxControl,
	PanelBody,
	Slot,
	Tooltip,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis -- no stable export exists (WordPress 6.8-7.1); core's own panels use it.
	__experimentalToggleGroupControl as ToggleGroupControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis -- no stable export exists (WordPress 6.8-7.1); core's own panels use it.
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
} from '@wordpress/components';
import { reset } from '@wordpress/icons';

import { PROPS } from '../props';
import { STORE, config } from '../store';
import { CONTROLS } from './controls';
import { TextPropControl } from './controls/basic';
import { useDesign, withValue } from './use-design';
import { inherited as inheritedFrom } from '../breakpoints';
import { layoutMode, visibleProps } from './visibility';
import { switcherItems } from './breakpoint-items';

export { switcherItems };

/** Section id → title, in panel order. */
export const SECTIONS = () => [
	[ 'layout', __( 'Layout', 'page-builder-sandwich' ) ],
	[ 'spacing', __( 'Spacing', 'page-builder-sandwich' ) ],
	[ 'size', __( 'Size', 'page-builder-sandwich' ) ],
	[ 'typography', __( 'Typography', 'page-builder-sandwich' ) ],
	[ 'colour', __( 'Colours & background', 'page-builder-sandwich' ) ],
	[ 'border', __( 'Border', 'page-builder-sandwich' ) ],
	[ 'shadow', __( 'Shadow', 'page-builder-sandwich' ) ],
	[ 'position', __( 'Position', 'page-builder-sandwich' ) ],
	[ 'effects', __( 'Effects', 'page-builder-sandwich' ) ],
	[ 'advanced', __( 'Advanced', 'page-builder-sandwich' ) ],
];

/**
 * The width a breakpoint covers, for its tooltip.
 *
 * @param {Object} bp Breakpoint.
 * @return {string} Text.
 */
function widthText( bp ) {
	if ( bp.max !== undefined ) {
		/* translators: %d: a screen width in pixels. */
		return sprintf( __( 'up to %d px', 'page-builder-sandwich' ), bp.max );
	}
	if ( bp.min !== undefined ) {
		/* translators: %d: a screen width in pixels. */
		return sprintf( __( 'from %d px', 'page-builder-sandwich' ), bp.min );
	}
	return __( 'all widths', 'page-builder-sandwich' );
}

/**
 * The breakpoint and state switcher at the top of the tab.
 *
 * @param {Object} props
 * @param {Object} props.design useDesign() result.
 * @return {Element} Switcher.
 */
export function Switcher( { design } ) {
	const { setBreakpoint, setHover } = useDispatch( STORE );
	const items = switcherItems();
	const set = ( id ) =>
		Object.keys( design.pbs?.s || {} ).some(
			( k ) => k === id || k === `${ id }:hover`
		);
	return (
		<div className="pbsw-design-switcher">
			<div
				className="pbsw-design-switcher__bps"
				role="group"
				aria-label={ __( 'Screen size', 'page-builder-sandwich' ) }
			>
				{ items.map( ( bp ) => (
					<Tooltip
						key={ bp.id }
						text={ `${ bp.label } (${ widthText( bp ) })` }
					>
						<Button
							size="compact"
							icon={ bp.icon }
							isPressed={ design.bp === bp.id }
							aria-label={ `${ bp.label } (${ widthText( bp ) })` }
							onClick={ () => setBreakpoint( bp.id ) }
							className={
								set( bp.id ) ? 'has-values' : undefined
							}
						>
							{ bp.icon ? undefined : bp.label }
						</Button>
					</Tooltip>
				) ) }
			</div>
			<ToggleGroupControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				isBlock
				hideLabelFromVision
				label={ __( 'State', 'page-builder-sandwich' ) }
				value={ design.hover ? 'hover' : 'normal' }
				onChange={ ( v ) => setHover( v === 'hover' ) }
			>
				<ToggleGroupControlOption
					value="normal"
					label={ __( 'Normal', 'page-builder-sandwich' ) }
				/>
				<ToggleGroupControlOption
					value="hover"
					label={ __( 'Hover', 'page-builder-sandwich' ) }
				/>
			</ToggleGroupControl>
			{ design.pbs?.s?.[ design.stateKey ] && (
				<Button
					variant="link"
					size="small"
					onClick={ design.resetState }
					className="pbsw-design-switcher__reset"
				>
					{ sprintf(
						/* translators: %s: a screen size such as "Tablet". */
						__(
							'Reset every value set for %s',
							'page-builder-sandwich'
						),
						items.find( ( b ) => b.id === design.bp )?.label ||
							design.bp
					) }
				</Button>
			) }
		</div>
	);
}

/**
 * One prop: its control, where an unset value comes from, and a reset.
 *
 * @param {Object} props
 * @param {Object} props.prop      Prop.
 * @param {Object} props.design    useDesign() result.
 * @param {string} props.blockName Block name.
 * @return {Element} Row.
 */
export function PropRow( { prop, design, blockName } ) {
	const Control = CONTROLS[ prop.control ] || TextPropControl;
	const value = design.get( prop.key );
	const inherited = design.inherited( prop.key );
	const from = inherited
		? switcherItems().find( ( b ) => b.id === inherited.from )?.label
		: null;
	return (
		<div
			className={ `pbsw-design-prop${
				value !== undefined ? ' is-set' : ''
			}${ value === undefined && inherited ? ' is-inherited' : '' }` }
			data-prop={ prop.key }
		>
			<Control
				prop={ prop }
				value={ value }
				placeholder={ inherited?.value }
				blockName={ blockName }
				onChange={ ( v ) => design.set( prop.key, v ) }
			/>
			<div className="pbsw-design-prop__meta">
				{ value === undefined && from && (
					<span className="pbsw-design-prop__from">
						{ sprintf(
							/* translators: %s: the screen size a value is inherited from, e.g. "Desktop". */
							__( 'Inherited from %s', 'page-builder-sandwich' ),
							from
						) }
					</span>
				) }
				{ value !== undefined && (
					<Button
						size="small"
						icon={ reset }
						label={ sprintf(
							/* translators: %s: a setting's name, e.g. "Padding". */
							__( 'Reset %s', 'page-builder-sandwich' ),
							prop.label
						) }
						onClick={ () => design.set( prop.key, undefined ) }
					/>
				) }
			</div>
		</div>
	);
}

/**
 * "Hide on" toggles.
 *
 * @param {Object} props
 * @param {Object} props.design useDesign() result.
 * @return {Element} Control.
 */
function HideOn( { design } ) {
	const hide = design.pbs?.hide || [];
	return (
		<fieldset className="pbsw-design-hide">
			<legend className="pbsw-design-sides__legend">
				{ __( 'Hide on', 'page-builder-sandwich' ) }
			</legend>
			{ switcherItems().map( ( bp ) => (
				<CheckboxControl
					__nextHasNoMarginBottom
					key={ bp.id }
					label={ `${ bp.label } (${ widthText( bp ) })` }
					checked={ hide.includes( bp.id ) }
					onChange={ ( on ) =>
						design.setHide(
							on
								? [ ...hide, bp.id ]
								: hide.filter( ( h ) => h !== bp.id )
						)
					}
				/>
			) ) }
		</fieldset>
	);
}

/**
 * The grouped sections of props.
 *
 * @param {Object}        props
 * @param {Object}        props.design    A design API (useDesign() or designFromValue()).
 * @param {Array<Object>} props.shown     Props to show.
 * @param {string}        props.blockName Block name ('' for a global class).
 * @param {Element}       props.advanced  Extra content of the Advanced section.
 * @return {Element} Sections.
 */
export function Sections( { design, shown, blockName, advanced = null } ) {
	return SECTIONS().map( ( [ group, title ] ) => {
		const props = shown.filter( ( p ) => p.group === group );
		if ( ! props.length && ( group !== 'advanced' || ! advanced ) ) {
			return null;
		}
		const count = props.filter(
			( p ) => design.get( p.key ) !== undefined
		).length;
		return (
			<PanelBody
				key={ group }
				title={
					count
						? sprintf(
								/* translators: 1: section title, 2: how many values are set in it. */
								__(
									'%1$s (%2$d set)',
									'page-builder-sandwich'
								),
								title,
								count
							)
						: title
				}
				initialOpen={ group === 'layout' }
				className={ `pbsw-design-section pbsw-design-section--${ group }` }
			>
				{ props.map( ( p ) => (
					<PropRow
						key={ p.key }
						prop={ p }
						design={ design }
						blockName={ blockName }
					/>
				) ) }
				{ group === 'advanced' && advanced }
			</PanelBody>
		);
	} );
}

/**
 * A design API over a bare `pbs.s` map (no block): what a global class edits (Pro, d5).
 *
 * @param {Object}   value    `pbs.s` map.
 * @param {Function} onChange Receives the new map.
 * @param {string}   bp       Breakpoint.
 * @param {boolean}  hover    Hover state.
 * @return {Object} Design API.
 */
export function designFromValue( value, onChange, bp, hover ) {
	const s = value && typeof value === 'object' ? value : {};
	const state = hover ? ':hover' : '';
	const stateKey = bp + state;
	const write = ( next ) => onChange( next );
	return {
		pbs: { s },
		bp,
		hover,
		stateKey,
		get: ( key ) => s[ stateKey ]?.[ key ],
		inherited: ( key ) =>
			inheritedFrom( s, key, bp, config().breakpoints, state ),
		set: ( key, v ) =>
			write( withValue( { s }, stateKey, key, v, () => 'x' )?.s || {} ),
		setMany: ( values ) => {
			let next = { s };
			for ( const [ k, v ] of Object.entries( values ) ) {
				next = withValue( next, stateKey, k, v, () => 'x' ) || {};
			}
			write( next.s || {} );
		},
		resetState: () => {
			const next = { ...s };
			delete next[ stateKey ];
			write( next );
		},
	};
}

/**
 * The Style editor without a block: the premium global-class editor renders it
 * (`pbsw.design.stylePanel` filter). Every prop that is not tied to a block type is offered.
 *
 * @param {Object}   props
 * @param {Object}   props.value    `pbs.s` map.
 * @param {Function} props.onChange Change.
 * @return {Element} Editor.
 */
export function StylePanel( { value, onChange } ) {
	const { bp, hover } = useSelect(
		( select ) => ( {
			bp: select( STORE ).getBreakpoint(),
			hover: select( STORE ).isHover(),
		} ),
		[]
	);
	const design = designFromValue( value, onChange, bp, hover );
	const shown = PROPS.filter( ( p ) => ! p.blocks.length );
	return (
		<div className="pbsw-design pbsw-design--class">
			<Switcher design={ design } />
			<Sections design={ design } shown={ shown } blockName="" />
		</div>
	);
}

/**
 * The Style tab for one block.
 *
 * @param {Object} props
 * @param {string} props.clientId Block client id.
 * @param {string} props.name     Block name.
 * @return {Element} Panel.
 */
export default function DesignPanel( { clientId, name } ) {
	const design = useDesign( clientId );
	const list = config().breakpoints;
	const { attributes, parent } = useSelect(
		( select ) => {
			const be = select( blockEditorStore );
			const rootId = be.getBlockRootClientId( clientId );
			return {
				attributes: be.getBlockAttributes( clientId ),
				parent: rootId
					? {
							name: be.getBlockName( rootId ),
							attributes: be.getBlockAttributes( rootId ),
						}
					: null,
			};
		},
		[ clientId ]
	);
	const mode = layoutMode( name, attributes, design.bp, list );
	const parentMode = parent
		? layoutMode( parent.name, parent.attributes, design.bp, list )
		: 'block';
	const position =
		design.get( 'position' ) ?? design.inherited( 'position' )?.value;
	const shown = visibleProps( PROPS, {
		name,
		attributes,
		mode,
		parentMode,
		position,
	} );

	return (
		<InspectorControls group="styles">
			<div className="pbsw-design">
				<Switcher design={ design } />
				<Sections
					design={ design }
					shown={ shown }
					blockName={ name }
					advanced={
						<>
							<HideOn design={ design } />
							{ /* Global classes and custom CSS (Pro) render here. */ }
							<Slot
								name="PbswDesignAdvanced"
								fillProps={ { clientId, design } }
							/>
						</>
					}
				/>
			</div>
		</InspectorControls>
	);
}
