/**
 * The "HTML attributes" control in a block's Advanced section: `data-*` and `aria-*` attributes
 * added to the block's outer tag on the page (includes/design/class-html-attributes.php).
 */
import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { Button, TextControl } from '@wordpress/components';

import {
	MAX,
	MAX_VALUE,
	allowed,
	normalName,
	toRows,
	toStored,
} from '../html-attributes';

/**
 * Control.
 *
 * @param {Object} props
 * @param {Object} props.design A design API (useDesign()).
 * @return {Element} Control.
 */
export function HtmlAttributes( { design } ) {
	const [ rows, setRows ] = useState( () => toRows( design.pbs?.attrs ) );

	const update = ( next ) => {
		setRows( next );
		design.setField( 'attrs', toStored( next ) );
	};

	return (
		<fieldset className="pbsw-design-attrs">
			<legend className="pbsw-design-sides__legend">
				{ __( 'HTML attributes', 'page-builder-sandwich' ) }
			</legend>
			<p className="pbsw-design-target__help">
				{ __(
					'Added to this block’s outer tag on the page. Names must start with data- or aria-, for example data-track or aria-label.',
					'page-builder-sandwich'
				) }
			</p>
			{ rows.map( ( row, i ) => {
				const name = normalName( row.name );
				const bad = '' !== name && ! allowed( name );
				return (
					<div className="pbsw-design-attrs__row" key={ i }>
						<TextControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Name', 'page-builder-sandwich' ) }
							value={ row.name }
							help={
								bad
									? __(
											'Not added: only data-… and aria-… names are allowed.',
											'page-builder-sandwich'
										)
									: undefined
							}
							onChange={ ( v ) =>
								update(
									rows.map( ( r, j ) =>
										j === i ? { ...r, name: v } : r
									)
								)
							}
						/>
						<TextControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Value', 'page-builder-sandwich' ) }
							value={ row.value }
							maxLength={ MAX_VALUE }
							onChange={ ( v ) =>
								update(
									rows.map( ( r, j ) =>
										j === i ? { ...r, value: v } : r
									)
								)
							}
						/>
						<Button
							variant="link"
							isDestructive
							onClick={ () =>
								update( rows.filter( ( _, j ) => j !== i ) )
							}
						>
							{ __( 'Remove', 'page-builder-sandwich' ) }
						</Button>
					</div>
				);
			} ) }
			{ rows.length < MAX && (
				<Button
					variant="secondary"
					onClick={ () =>
						setRows( [ ...rows, { name: 'data-', value: '' } ] )
					}
				>
					{ __( 'Add attribute', 'page-builder-sandwich' ) }
				</Button>
			) }
		</fieldset>
	);
}
