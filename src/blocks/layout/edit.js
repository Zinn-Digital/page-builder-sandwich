/**
 * The editor of pbs/section and pbs/container (pbs-d1): the same markup the server renders, the
 * block's own settings (tag, width, link) in the Settings tab with quick layout controls that write
 * the Style tab's values for the breakpoint being edited, and the visual grid editor.
 */
import { __, sprintf } from '@wordpress/i18n';
import { useRef } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import {
	BlockControls,
	InnerBlocks,
	InspectorControls,
	store as blockEditorStore,
	useBlockProps,
	useInnerBlocksProps,
} from '@wordpress/block-editor';
import {
	Notice,
	PanelBody,
	SelectControl,
	TextControl,
	ToggleControl,
	ToolbarButton,
	ToolbarGroup,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis -- no stable export exists (WordPress 6.8-7.1); core's own panels use it.
	__experimentalNumberControl as NumberControl,
} from '@wordpress/components';
import { useMergeRefs } from '@wordpress/compose';
import { applyFilters } from '@wordpress/hooks';
import { grid, stack, row } from '@wordpress/icons';

import { STORE } from '../../design/store';
import { useDesign } from '../../design/editor/use-design';
import { layoutMode } from '../../design/editor/visibility';
import { LengthControl } from '../../design/editor/controls/length';
import { switcherItems } from '../../design/editor/breakpoint-items';
import GridOverlay from './grid-overlay';

const prefix = () => window.pbswDesign?.prefix || 'zd';

/** HTML tags an author may choose, with what each means. */
const TAGS = () => ( {
	section: __( 'section — a thematic section', 'page-builder-sandwich' ),
	div: __( 'div — no meaning', 'page-builder-sandwich' ),
	header: __( 'header — introductory content', 'page-builder-sandwich' ),
	footer: __( 'footer — closing content', 'page-builder-sandwich' ),
	article: __( 'article — self-contained content', 'page-builder-sandwich' ),
	aside: __( 'aside — related content', 'page-builder-sandwich' ),
	main: __(
		'main — the main content (once per page)',
		'page-builder-sandwich'
	),
	nav: __( 'nav — navigation links', 'page-builder-sandwich' ),
} );

/**
 * @param {Object} props      Block edit props.
 * @param {string} props.kind `section` or `container`.
 * @return {Element} Editor.
 */
export default function LayoutEdit( props ) {
	const { kind, attributes, setAttributes, clientId, isSelected } = props;
	const p = prefix();
	const boxed =
		( attributes.width ?? ( kind === 'section' ? 'boxed' : 'full' ) ) ===
		'boxed';
	const design = useDesign( clientId );
	const list = window.pbswDesign?.breakpoints || [];
	const mode = layoutMode( props.name, attributes, design.bp, list );
	const { hasChildren, childSelected } = useSelect(
		( select ) => {
			const be = select( blockEditorStore );
			return {
				hasChildren: be.getBlockCount( clientId ) > 0,
				childSelected: be.hasSelectedInnerBlock( clientId, false ),
			};
		},
		[ clientId ]
	);
	useSelect( ( select ) => select( STORE ).getBreakpoint(), [] );

	const layoutRef = useRef();
	const outerClass = `${ p }-${ kind }${ boxed ? ` ${ p }-${ kind }--boxed` : '' }`;
	const blockProps = useBlockProps( { className: outerClass } );
	const innerBlocksProps = useInnerBlocksProps(
		boxed ? { className: `${ p }-${ kind }__in` } : blockProps,
		{
			renderAppender: hasChildren
				? InnerBlocks.DefaultBlockAppender
				: InnerBlocks.ButtonBlockAppender,
			orientation: mode === 'flex' ? undefined : 'vertical',
		}
	);
	const { children, ref: innerRef, ...innerRest } = innerBlocksProps;
	const mergedInnerRef = useMergeRefs( [ innerRef, layoutRef ] );

	const showGrid = mode === 'grid' && ( isSelected || childSelected );
	/**
	 * Background and divider layers drawn in the canvas (the premium effects add them: shape
	 * dividers, background video, slideshow). They sit where the front end puts them: right inside
	 * the block's own element, before its content.
	 *
	 * @type {Element|null}
	 */
	const layers = applyFilters( 'pbsw.layout.canvasLayers', null, {
		clientId,
		name: props.name,
		attributes,
	} );
	const layoutEl = (
		<div { ...innerRest } ref={ mergedInnerRef }>
			{ ! boxed && layers }
			{ children }
			{ showGrid && (
				<GridOverlay
					clientId={ clientId }
					layoutRef={ layoutRef }
					design={ design }
				/>
			) }
		</div>
	);

	const bpLabel =
		switcherItems().find( ( b ) => b.id === design.bp )?.label || design.bp;
	const display = design.get( 'display' );
	const setMode = ( v ) => design.set( 'display', v );
	const tags = TAGS();
	const link = attributes.link || {};

	return (
		<>
			<BlockControls>
				<ToolbarGroup>
					<ToolbarButton
						icon={ stack }
						label={ __(
							'Stack (flexbox, column)',
							'page-builder-sandwich'
						) }
						isPressed={
							mode === 'flex' &&
							(
								design.get( 'flexDirection' ) ?? 'column'
							).startsWith( 'column' )
						}
						onClick={ () =>
							design.setMany( {
								display: 'flex',
								flexDirection: 'column',
							} )
						}
					/>
					<ToolbarButton
						icon={ row }
						label={ __( 'Row (flexbox)', 'page-builder-sandwich' ) }
						isPressed={
							mode === 'flex' &&
							( design.get( 'flexDirection' ) ?? '' ).startsWith(
								'row'
							)
						}
						onClick={ () =>
							design.setMany( {
								display: 'flex',
								flexDirection: 'row',
							} )
						}
					/>
					<ToolbarButton
						icon={ grid }
						label={ __( 'Grid', 'page-builder-sandwich' ) }
						isPressed={ mode === 'grid' }
						onClick={ () =>
							design.setMany( {
								display: 'grid',
								gridColumns: design.get( 'gridColumns' ) ?? 2,
							} )
						}
					/>
				</ToolbarGroup>
			</BlockControls>
			<InspectorControls>
				<PanelBody title={ __( 'Layout', 'page-builder-sandwich' ) }>
					<p className="pbsw-layout-bp">
						{ sprintf(
							/* translators: %s: a screen size such as "Tablet". */
							__( 'Editing: %s', 'page-builder-sandwich' ),
							bpLabel
						) }
					</p>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __(
							'Lay out children as',
							'page-builder-sandwich'
						) }
						value={ display ?? '' }
						options={ [
							{
								value: '',
								label: sprintf(
									/* translators: %s: the layout in effect, e.g. "flex". */
									__(
										'Inherited (%s)',
										'page-builder-sandwich'
									),
									mode
								),
							},
							{
								value: 'flex',
								label: __( 'Flexbox', 'page-builder-sandwich' ),
							},
							{
								value: 'grid',
								label: __( 'Grid', 'page-builder-sandwich' ),
							},
							{
								value: 'block',
								label: __(
									'Stacked blocks',
									'page-builder-sandwich'
								),
							},
						] }
						onChange={ ( v ) => setMode( v || undefined ) }
					/>
					{ mode === 'grid' && (
						<NumberControl
							__next40pxDefaultSize
							label={ __( 'Columns', 'page-builder-sandwich' ) }
							min={ 1 }
							max={ 24 }
							value={
								typeof design.get( 'gridColumns' ) === 'number'
									? design.get( 'gridColumns' )
									: ''
							}
							placeholder={ __(
								'Drag in the canvas, or type',
								'page-builder-sandwich'
							) }
							onChange={ ( v ) =>
								design.set(
									'gridColumns',
									v === '' || v === undefined
										? undefined
										: parseInt( v, 10 )
								)
							}
						/>
					) }
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Content width', 'page-builder-sandwich' ) }
						value={ boxed ? 'boxed' : 'full' }
						options={ [
							{
								value: 'boxed',
								label: __(
									'Boxed (the theme’s content width)',
									'page-builder-sandwich'
								),
							},
							{
								value: 'full',
								label: __(
									'Full width',
									'page-builder-sandwich'
								),
							},
						] }
						onChange={ ( width ) => setAttributes( { width } ) }
					/>
					<LengthControl
						label={ __(
							'Minimum height',
							'page-builder-sandwich'
						) }
						value={ design.get( 'minHeight' ) }
						placeholder={ design.inherited( 'minHeight' )?.value }
						opts={ {
							keywords: [ 'auto', 'fit-content' ],
							tokens: [ 'var' ],
						} }
						onChange={ ( v ) => design.set( 'minHeight', v ) }
					/>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'HTML tag', 'page-builder-sandwich' ) }
						value={
							attributes.tag ||
							( kind === 'section' ? 'section' : 'div' )
						}
						options={ Object.entries( tags ).map(
							( [ value, label ] ) => ( { value, label } )
						) }
						onChange={ ( tag ) => setAttributes( { tag } ) }
					/>
				</PanelBody>
				<PanelBody
					title={ __( 'Link', 'page-builder-sandwich' ) }
					initialOpen={ !! link.url }
				>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						type="url"
						label={ __(
							'Make the whole block a link to',
							'page-builder-sandwich'
						) }
						value={ link.url || '' }
						onChange={ ( url ) =>
							setAttributes( {
								link: url ? { ...link, url } : undefined,
							} )
						}
					/>
					{ link.url && (
						<>
							<TextControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								label={ __(
									'Link text for screen readers',
									'page-builder-sandwich'
								) }
								help={ __(
									'What the link does, for visitors who cannot see the block.',
									'page-builder-sandwich'
								) }
								value={ link.label || '' }
								onChange={ ( label ) =>
									setAttributes( {
										link: {
											...link,
											label: label || undefined,
										},
									} )
								}
							/>
							<ToggleControl
								__nextHasNoMarginBottom
								label={ __(
									'Open in a new tab',
									'page-builder-sandwich'
								) }
								checked={ !! link.newTab }
								onChange={ ( newTab ) =>
									setAttributes( {
										link: {
											...link,
											newTab: newTab || undefined,
										},
									} )
								}
							/>
							<Notice status="warning" isDismissible={ false }>
								{ __(
									'Do not put links or buttons inside a linked block: a link inside a link does not work in browsers.',
									'page-builder-sandwich'
								) }
							</Notice>
						</>
					) }
				</PanelBody>
			</InspectorControls>
			{ boxed ? (
				<div { ...blockProps }>
					{ layers }
					{ layoutEl }
				</div>
			) : (
				layoutEl
			) }
		</>
	);
}
