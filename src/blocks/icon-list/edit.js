/**
 * pbs/icon-list editor: the front end's own markup and classes, each line edited in place; the
 * icon, layout and colour in the sidebar, and a per-line icon from the library.
 */
import { __, sprintf } from '@wordpress/i18n';
import {
	InspectorControls,
	RichText,
	useBlockProps,
} from '@wordpress/block-editor';
import { Button, PanelBody, SelectControl } from '@wordpress/components';

import { cls } from '../shared/cls';
import {
	AccentControl,
	Icon,
	IconButton,
	ItemTools,
	iconLabels,
	itemIcon,
	listOps,
} from '../shared/kit';

export default function Edit( { attributes, setAttributes, isSelected } ) {
	const { items, icon, layout, accent } = attributes;
	const list = items.length ? items : [ { text: '' } ];
	const set = ( next ) => setAttributes( { items: next } );
	const labels = iconLabels();
	const blockProps = useBlockProps( {
		className: cls(
			'icon-list',
			`icon-list--${ layout }`,
			`accent--${ accent }`
		),
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Icon list', 'page-builder-sandwich' ) }>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Icon', 'page-builder-sandwich' ) }
						value={ icon }
						options={ [
							'check',
							'dot',
							'arrow',
							'star',
							'circle',
						].map( ( v ) => ( { value: v, label: labels[ v ] } ) ) }
						onChange={ ( v ) => setAttributes( { icon: v } ) }
					/>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Layout', 'page-builder-sandwich' ) }
						value={ layout }
						options={ [
							{
								value: 'stacked',
								label: __(
									'One below the other',
									'page-builder-sandwich'
								),
							},
							{
								value: 'inline',
								label: __(
									'Side by side',
									'page-builder-sandwich'
								),
							},
						] }
						onChange={ ( v ) => setAttributes( { layout: v } ) }
					/>
					<AccentControl
						value={ accent }
						onChange={ ( v ) => setAttributes( { accent: v } ) }
					/>
				</PanelBody>
				<PanelBody
					title={ __( 'Lines', 'page-builder-sandwich' ) }
					initialOpen={ false }
				>
					{ list.map( ( item, i ) => {
						const name = sprintf(
							/* translators: %d: line number. */
							__( 'Line %d', 'page-builder-sandwich' ),
							i + 1
						);
						return (
							<div key={ i } className="pbsw-kit-row">
								<strong>{ name }</strong>
								<IconButton
									svg={ item.icon }
									iconRef={ item.iconId }
									label={ __(
										'Own icon',
										'page-builder-sandwich'
									) }
									onChange={ ( patch ) =>
										set(
											listOps.update(
												list,
												i,
												itemIcon( patch )
											)
										)
									}
								/>
								<ItemTools
									index={ i }
									count={ list.length }
									name={ name }
									onMove={ ( by ) =>
										set( listOps.move( list, i, by ) )
									}
									onRemove={ () =>
										set( listOps.remove( list, i ) )
									}
								/>
							</div>
						);
					} ) }
				</PanelBody>
			</InspectorControls>
			<ul { ...blockProps }>
				{ list.map( ( item, i ) => (
					<li key={ i } className={ cls( 'icon-list__item' ) }>
						<span className={ cls( 'icon-list__icon' ) }>
							<Icon svg={ item.icon } builtin={ icon } />
						</span>
						<RichText
							tagName="span"
							className={ cls( 'icon-list__text' ) }
							value={ item.text || '' }
							onChange={ ( text ) =>
								set( listOps.update( list, i, { text } ) )
							}
							placeholder={ __(
								'List item…',
								'page-builder-sandwich'
							) }
							allowedFormats={ [
								'core/bold',
								'core/italic',
								'core/link',
							] }
							aria-label={ sprintf(
								/* translators: %d: line number. */
								__( 'Line %d', 'page-builder-sandwich' ),
								i + 1
							) }
						/>
					</li>
				) ) }
				{ isSelected && (
					<li className="pbsw-kit-add">
						<Button
							variant="secondary"
							size="compact"
							onClick={ () =>
								set( listOps.add( list, { text: '' } ) )
							}
						>
							{ __( 'Add line', 'page-builder-sandwich' ) }
						</Button>
					</li>
				) }
			</ul>
		</>
	);
}
