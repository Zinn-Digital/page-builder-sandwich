/**
 * The visual grid editor (pbs-d1): drawn over a grid section/container in the canvas while it (or
 * one of its children) is selected. It shows every column and row track, lets the author drag a
 * column boundary or a row's end to resize, add and remove tracks, and drag a selected child's
 * corner across cells to set how many columns and rows it spans. Every change is written to the
 * breakpoint being edited, as ONE undo step when the drag ends.
 */
import { __ } from '@wordpress/i18n';
import { useEffect, useRef, useState } from '@wordpress/element';
import { useDispatch, useSelect, useRegistry } from '@wordpress/data';
import { Button } from '@wordpress/components';
import { store as blockEditorStore } from '@wordpress/block-editor';

import { STORE } from '../../design/store';
import { withValue, takenIds } from '../../design/editor/use-design';
import { newId } from '../../design/ids';
import {
	offsets,
	parseTracks,
	resizeColumns,
	resizeRows,
	spanTo,
	stepColumns,
	trackAt,
} from './grid-math';

/**
 * Measure the grid: track sizes and gaps from computed style, plus whether it is right-to-left.
 *
 * @param {HTMLElement} el Layout element.
 * @return {Object} Geometry.
 */
function measure( el ) {
	const cs = el.ownerDocument.defaultView.getComputedStyle( el );
	const cols = parseTracks( cs.gridTemplateColumns );
	const rows = parseTracks( cs.gridTemplateRows );
	const colGap = parseFloat( cs.columnGap ) || 0;
	const rowGap = parseFloat( cs.rowGap ) || 0;
	return {
		cols,
		rows,
		colGap,
		rowGap,
		colStarts: offsets( cols, colGap ),
		rowStarts: offsets( rows, rowGap ),
		padInline: parseFloat( cs.paddingInlineStart ) || 0,
		padBlock: parseFloat( cs.paddingBlockStart ) || 0,
		rtl: cs.direction === 'rtl',
	};
}

/**
 * A point in the layout element's content box, in logical coordinates.
 *
 * @param {HTMLElement} el Layout element.
 * @param {Object}      g  Geometry.
 * @param {number}      x  clientX.
 * @param {number}      y  clientY.
 * @return {{i:number,b:number}} Inline and block offsets.
 */
function logical( el, g, x, y ) {
	const r = el.getBoundingClientRect();
	return {
		i: ( g.rtl ? r.right - x : x - r.left ) - g.padInline,
		b: y - r.top - g.padBlock,
	};
}

/**
 * @param {Object}                 props
 * @param {string}                 props.clientId  The grid block.
 * @param {{current: HTMLElement}} props.layoutRef Its layout element.
 * @param {Object}                 props.design    useDesign() for the grid block.
 * @return {Element|null} Overlay.
 */
export default function GridOverlay( { clientId, layoutRef, design } ) {
	const registry = useRegistry();
	const { updateBlockAttributes } = useDispatch( blockEditorStore );
	const bp = useSelect( ( select ) => select( STORE ).getBreakpoint(), [] );
	const selectedChild = useSelect(
		( select ) => {
			const be = select( blockEditorStore );
			const id = be.getSelectedBlockClientId();
			return id && be.getBlockRootClientId( id ) === clientId ? id : null;
		},
		[ clientId ]
	);
	const [ geo, setGeo ] = useState( null );
	const [ tick, setTick ] = useState( 0 );
	const drag = useRef( null );

	// Re-measure whenever the grid or its content changes size.
	useEffect( () => {
		const el = layoutRef.current;
		if ( ! el ) {
			return undefined;
		}
		const update = () => setGeo( measure( el ) );
		update();
		const view = el.ownerDocument.defaultView;
		const ro = new view.ResizeObserver( update );
		ro.observe( el );
		for ( const child of el.children ) {
			ro.observe( child );
		}
		return () => ro.disconnect();
	}, [ layoutRef, bp, tick, design.pbs ] );

	if ( ! geo || ! geo.cols.length ) {
		return null;
	}
	const el = layoutRef.current;

	const writeChild = ( childId, values ) => {
		const be = registry.select( blockEditorStore );
		let pbs = be.getBlockAttributes( childId )?.pbs;
		const make = () => newId( takenIds( be.getBlocks() ) );
		for ( const [ k, v ] of Object.entries( values ) ) {
			pbs = withValue( pbs, design.stateKey, k, v, make );
		}
		updateBlockAttributes( childId, { pbs } );
	};

	const start = ( e, kind, index ) => {
		e.preventDefault();
		e.stopPropagation();
		e.currentTarget.setPointerCapture?.( e.pointerId );
		drag.current = {
			kind,
			index,
			from: logical( el, geo, e.clientX, e.clientY ),
			geo,
		};
	};

	const move = ( e ) => {
		const d = drag.current;
		if ( ! d ) {
			return;
		}
		const at = logical( el, d.geo, e.clientX, e.clientY );
		if ( d.kind === 'col' ) {
			d.value = resizeColumns( d.geo.cols, d.index, at.i - d.from.i );
			el.style.gridTemplateColumns = d.value;
		} else if ( d.kind === 'row' ) {
			d.value = resizeRows( d.geo.rows, d.index, at.b - d.from.b );
			el.style.gridTemplateRows = d.value;
		} else if ( d.kind === 'span' ) {
			const child = el.ownerDocument.getElementById(
				`block-${ selectedChild }`
			);
			d.value = {
				colSpan: spanTo(
					d.geo.colStarts,
					d.geo.cols,
					d.first.col,
					at.i
				),
				rowSpan: spanTo(
					d.geo.rowStarts,
					d.geo.rows,
					d.first.row,
					at.b
				),
			};
			if ( child ) {
				child.style.gridColumn = `span ${ d.value.colSpan }`;
				child.style.gridRow = `span ${ d.value.rowSpan }`;
			}
		}
	};

	const end = () => {
		const d = drag.current;
		drag.current = null;
		if ( ! d || d.value === undefined ) {
			return;
		}
		// Editor-only inline previews go; the stored value takes over.
		el.style.gridTemplateColumns = '';
		el.style.gridTemplateRows = '';
		if ( d.kind === 'col' ) {
			design.set( 'gridColumns', d.value );
		} else if ( d.kind === 'row' ) {
			design.set( 'gridRows', d.value );
		} else if ( d.kind === 'span' ) {
			const child = el.ownerDocument.getElementById(
				`block-${ selectedChild }`
			);
			if ( child ) {
				child.style.gridColumn = '';
				child.style.gridRow = '';
			}
			writeChild( selectedChild, {
				colSpan: d.value.colSpan > 1 ? d.value.colSpan : undefined,
				rowSpan: d.value.rowSpan > 1 ? d.value.rowSpan : undefined,
			} );
		}
		setTick( ( t ) => t + 1 );
	};

	// The selected child's first cell, for the span handle.
	let childBox = null;
	if ( selectedChild ) {
		const child = el.ownerDocument.getElementById(
			`block-${ selectedChild }`
		);
		if ( child ) {
			const r = child.getBoundingClientRect();
			const a = logical( el, geo, geo.rtl ? r.right : r.left, r.top );
			const b = logical( el, geo, geo.rtl ? r.left : r.right, r.bottom );
			childBox = {
				col: trackAt( geo.colStarts, geo.cols, a.i + 1 ),
				row: trackAt( geo.rowStarts, geo.rows, a.b + 1 ),
				end: b,
			};
		}
	}

	const P = ( inline, block, extra = {} ) => ( {
		insetInlineStart: `${ geo.padInline + inline }px`,
		insetBlockStart: `${ geo.padBlock + block }px`,
		...extra,
	} );
	const height = geo.rowStarts.at( -1 ) + geo.rows.at( -1 );
	const width = geo.colStarts.at( -1 ) + geo.cols.at( -1 );

	return (
		<div
			className="pbsw-grid-overlay"
			onPointerMove={ move }
			onPointerUp={ end }
			onPointerCancel={ end }
			contentEditable={ false }
		>
			{ geo.cols.map( ( w, i ) => (
				<div
					key={ `c${ i }` }
					className="pbsw-grid-overlay__col"
					style={ P( geo.colStarts[ i ], 0, {
						inlineSize: `${ w }px`,
						blockSize: `${ height }px`,
					} ) }
				/>
			) ) }
			{ geo.rows.map( ( h, i ) => (
				<div
					key={ `r${ i }` }
					className="pbsw-grid-overlay__row"
					style={ P( 0, geo.rowStarts[ i ], {
						inlineSize: `${ width }px`,
						blockSize: `${ h }px`,
					} ) }
				/>
			) ) }
			{ geo.cols.slice( 0, -1 ).map( ( w, i ) => (
				<button
					type="button"
					key={ `ch${ i }` }
					className="pbsw-grid-overlay__handle is-col"
					aria-label={ __(
						'Drag to resize these columns',
						'page-builder-sandwich'
					) }
					style={ P( geo.colStarts[ i ] + w + geo.colGap / 2 - 4, 0, {
						blockSize: `${ height }px`,
					} ) }
					onPointerDown={ ( e ) => start( e, 'col', i ) }
				/>
			) ) }
			{ geo.rows.map( ( h, i ) => (
				<button
					type="button"
					key={ `rh${ i }` }
					className="pbsw-grid-overlay__handle is-row"
					aria-label={ __(
						'Drag to resize this row',
						'page-builder-sandwich'
					) }
					style={ P( 0, geo.rowStarts[ i ] + h - 4, {
						inlineSize: `${ width }px`,
					} ) }
					onPointerDown={ ( e ) => start( e, 'row', i ) }
				/>
			) ) }
			{ childBox && (
				<button
					type="button"
					className="pbsw-grid-overlay__span"
					aria-label={ __(
						'Drag across cells to set how many columns and rows this block spans',
						'page-builder-sandwich'
					) }
					style={ P( childBox.end.i - 8, childBox.end.b - 8 ) }
					onPointerDown={ ( e ) => {
						start( e, 'span', 0 );
						drag.current.first = {
							col: childBox.col,
							row: childBox.row,
						};
					} }
				/>
			) }
			<div className="pbsw-grid-overlay__tools" style={ P( 0, -34 ) }>
				<Button
					size="small"
					variant="secondary"
					onClick={ () =>
						design.set(
							'gridColumns',
							stepColumns(
								design.get( 'gridColumns' ),
								geo.cols,
								1
							)
						)
					}
				>
					{ __( '+ Column', 'page-builder-sandwich' ) }
				</Button>
				<Button
					size="small"
					variant="secondary"
					disabled={ geo.cols.length < 2 }
					onClick={ () =>
						design.set(
							'gridColumns',
							stepColumns(
								design.get( 'gridColumns' ),
								geo.cols,
								-1
							)
						)
					}
				>
					{ __( '− Column', 'page-builder-sandwich' ) }
				</Button>
				<Button
					size="small"
					variant="secondary"
					onClick={ () =>
						design.set( 'gridRows', geo.rows.length + 1 )
					}
				>
					{ __( '+ Row', 'page-builder-sandwich' ) }
				</Button>
				<Button
					size="small"
					variant="secondary"
					disabled={ geo.rows.length < 2 }
					onClick={ () =>
						design.set(
							'gridRows',
							geo.rows.length > 2
								? geo.rows.length - 1
								: undefined
						)
					}
				>
					{ __( '− Row', 'page-builder-sandwich' ) }
				</Button>
			</div>
		</div>
	);
}
