/**
 * pbs/comparison-table saved markup: the footprint-free fallback — a real table with header
 * cells, ✓ / ✗ for included / not included (symbols, not words: save() must not depend on the
 * editor's language). The live HTML is rebuilt by Blocks\Marketing::render_comparison_table().
 */
import { RichText } from '@wordpress/block-editor';

export default function save( { attributes } ) {
	const columns = attributes.columns || [];
	const rows = ( attributes.rows || [] ).filter( ( r ) => r && r.label );
	if ( ! columns.length || ! rows.length ) {
		return null;
	}
	return (
		<table>
			{ attributes.caption && (
				<RichText.Content
					tagName="caption"
					value={ attributes.caption }
				/>
			) }
			<thead>
				<tr>
					<td></td>
					{ columns.map( ( col, i ) => (
						<RichText.Content
							key={ i }
							tagName="th"
							scope="col"
							value={ col.title || '' }
						/>
					) ) }
				</tr>
			</thead>
			<tbody>
				{ rows.map( ( row, r ) => (
					<tr key={ r }>
						<RichText.Content
							tagName="th"
							scope="row"
							value={ row.label }
						/>
						{ columns.map( ( col, c ) => {
							const cell = ( row.cells || [] )[ c ] || {};
							if ( cell.type === 'yes' ) {
								return <td key={ c }>✓</td>;
							}
							if ( cell.type === 'no' ) {
								return <td key={ c }>✗</td>;
							}
							return (
								<RichText.Content
									key={ c }
									tagName="td"
									value={ cell.text || '' }
								/>
							);
						} ) }
					</tr>
				) ) }
			</tbody>
		</table>
	);
}
