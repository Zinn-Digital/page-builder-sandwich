/**
 * pbs/comparison-table editor: the front end's own table, every heading and text cell edited in
 * place. Each cell has a small button that switches it between text, "included" (a tick) and
 * "not included" (a cross); rows and columns are added, moved and removed in the sidebar.
 */
import { __, sprintf } from '@wordpress/i18n';
import {
	InspectorControls,
	RichText,
	useBlockProps,
} from '@wordpress/block-editor';
import { Button, PanelBody, ToggleControl } from '@wordpress/components';

import { cls } from '../shared/cls';
import { Icon, ItemTools, Sr, listOps } from '../shared/kit';

const NEXT = { text: 'yes', yes: 'no', no: 'text' };

/**
 * The row's cells padded to the number of columns.
 *
 * @param {Object} row   Row.
 * @param {number} count Columns.
 * @return {Object[]} Cells.
 */
export function cellsOf( row, count ) {
	const cells = Array.isArray( row.cells ) ? row.cells.slice( 0, count ) : [];
	while ( cells.length < count ) {
		cells.push( { type: 'text', text: '' } );
	}
	return cells;
}

export default function Edit( { attributes, setAttributes, isSelected } ) {
	const { caption, columns, rows } = attributes;
	const cols = columns.length ? columns : [ { title: '' }, { title: '' } ];
	const body = rows.length ? rows : [ { label: '', cells: [] } ];
	const setCols = ( next ) => setAttributes( { columns: next } );
	const setRows = ( next ) => setAttributes( { rows: next } );
	const setCell = ( r, c, patch ) => {
		const cells = cellsOf( body[ r ], cols.length );
		cells[ c ] = { ...cells[ c ], ...patch };
		setRows( listOps.update( body, r, { cells } ) );
	};
	const typeLabel = {
		text: __( 'Text', 'page-builder-sandwich' ),
		yes: __( 'Included', 'page-builder-sandwich' ),
		no: __( 'Not included', 'page-builder-sandwich' ),
	};
	const blockProps = useBlockProps( {
		className: cls( 'comparison-table' ),
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Columns', 'page-builder-sandwich' ) }>
					{ cols.map( ( col, i ) => {
						const name =
							( col.title || '' ).replace( /<[^>]*>/g, '' ) ||
							sprintf(
								/* translators: %d: column number. */
								__( 'Column %d', 'page-builder-sandwich' ),
								i + 1
							);
						return (
							<div key={ i } className="pbsw-kit-row">
								<ToggleControl
									__nextHasNoMarginBottom
									label={ sprintf(
										/* translators: %s: column name. */
										__(
											'Highlight %s',
											'page-builder-sandwich'
										),
										name
									) }
									checked={ !! col.highlight }
									onChange={ ( v ) =>
										setCols(
											listOps.update( cols, i, {
												highlight: v,
											} )
										)
									}
								/>
								<ItemTools
									index={ i }
									count={ cols.length }
									name={ name }
									onMove={ ( by ) => {
										setCols( listOps.move( cols, i, by ) );
										setRows(
											body.map( ( row ) => ( {
												...row,
												cells: listOps.move(
													cellsOf( row, cols.length ),
													i,
													by
												),
											} ) )
										);
									} }
									onRemove={ () => {
										setCols( listOps.remove( cols, i ) );
										setRows(
											body.map( ( row ) => ( {
												...row,
												cells: listOps.remove(
													cellsOf( row, cols.length ),
													i
												),
											} ) )
										);
									} }
								/>
							</div>
						);
					} ) }
					{ cols.length < 8 && (
						<Button
							variant="secondary"
							onClick={ () =>
								setCols( listOps.add( cols, { title: '' } ) )
							}
						>
							{ __( 'Add column', 'page-builder-sandwich' ) }
						</Button>
					) }
				</PanelBody>
				<PanelBody
					title={ __( 'Rows', 'page-builder-sandwich' ) }
					initialOpen={ false }
				>
					{ body.map( ( row, i ) => {
						const name =
							( row.label || '' ).replace( /<[^>]*>/g, '' ) ||
							sprintf(
								/* translators: %d: row number. */
								__( 'Row %d', 'page-builder-sandwich' ),
								i + 1
							);
						return (
							<div key={ i } className="pbsw-kit-row">
								<strong>{ name }</strong>
								<ItemTools
									index={ i }
									count={ body.length }
									name={ name }
									onMove={ ( by ) =>
										setRows( listOps.move( body, i, by ) )
									}
									onRemove={ () =>
										setRows( listOps.remove( body, i ) )
									}
								/>
							</div>
						);
					} ) }
					<Button
						variant="secondary"
						onClick={ () =>
							setRows(
								listOps.add( body, { label: '', cells: [] } )
							)
						}
					>
						{ __( 'Add row', 'page-builder-sandwich' ) }
					</Button>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<table className={ cls( 'comparison-table__table' ) }>
					<RichText
						tagName="caption"
						className={ cls( 'comparison-table__caption' ) }
						value={ caption }
						onChange={ ( v ) => setAttributes( { caption: v } ) }
						placeholder={ __(
							'Caption (what is being compared)',
							'page-builder-sandwich'
						) }
						allowedFormats={ [] }
					/>
					<thead>
						<tr>
							<td></td>
							{ cols.map( ( col, i ) => (
								<RichText
									key={ i }
									tagName="th"
									scope="col"
									className={ cls(
										'comparison-table__plan',
										col.highlight &&
											'comparison-table__plan--highlight'
									) }
									value={ col.title || '' }
									onChange={ ( v ) =>
										setCols(
											listOps.update( cols, i, {
												title: v,
											} )
										)
									}
									placeholder={ __(
										'Plan',
										'page-builder-sandwich'
									) }
									allowedFormats={ [] }
								/>
							) ) }
						</tr>
					</thead>
					<tbody>
						{ body.map( ( row, r ) => (
							<tr key={ r }>
								<RichText
									tagName="th"
									scope="row"
									className={ cls(
										'comparison-table__feature'
									) }
									value={ row.label || '' }
									onChange={ ( v ) =>
										setRows(
											listOps.update( body, r, {
												label: v,
											} )
										)
									}
									placeholder={ __(
										'Feature',
										'page-builder-sandwich'
									) }
									allowedFormats={ [ 'core/italic' ] }
								/>
								{ cellsOf( row, cols.length ).map(
									( cell, c ) => {
										const type = NEXT[ cell.type ]
											? cell.type
											: 'text';
										return (
											<td
												key={ c }
												className={ cls(
													'comparison-table__cell',
													`comparison-table__cell--${ type }`,
													cols[ c ].highlight &&
														'comparison-table__cell--highlight'
												) }
											>
												{ type === 'text' ? (
													<RichText
														tagName="span"
														value={
															cell.text || ''
														}
														onChange={ ( v ) =>
															setCell( r, c, {
																text: v,
															} )
														}
														placeholder="—"
														allowedFormats={ [
															'core/bold',
														] }
													/>
												) : (
													<>
														<Icon
															builtin={
																type === 'yes'
																	? 'check'
																	: 'cross'
															}
														/>
														<Sr>
															{
																typeLabel[
																	type
																]
															}
														</Sr>
													</>
												) }
												{ isSelected && (
													<Button
														className="pbsw-kit-cell-type"
														size="small"
														variant="tertiary"
														label={ sprintf(
															/* translators: %s: current cell type (Text, Included, Not included). */
															__(
																'Cell type: %s. Change',
																'page-builder-sandwich'
															),
															typeLabel[ type ]
														) }
														showTooltip
														onClick={ () =>
															setCell( r, c, {
																type: NEXT[
																	type
																],
															} )
														}
													>
														⇄
													</Button>
												) }
											</td>
										);
									}
								) }
							</tr>
						) ) }
					</tbody>
				</table>
			</div>
		</>
	);
}
