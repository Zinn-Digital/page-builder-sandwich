/**
 * pbs/feature-list editor: the front end's own grid, each feature's heading and text edited in
 * place; icons, columns and colour in the sidebar.
 */
import { __, sprintf } from '@wordpress/i18n';
import {
	InspectorControls,
	RichText,
	useBlockProps,
} from '@wordpress/block-editor';
import {
	Button,
	PanelBody,
	RangeControl,
	SelectControl,
} from '@wordpress/components';

import { cls } from '../shared/cls';
import {
	AccentControl,
	Icon,
	IconButton,
	ItemTools,
	LevelControl,
	iconLabels,
	itemIcon,
	listOps,
} from '../shared/kit';

export default function Edit( { attributes, setAttributes, isSelected } ) {
	const { items, icon, columns, level, accent } = attributes;
	const list = items.length ? items : [ { title: '', text: '' } ];
	const set = ( next ) => setAttributes( { items: next } );
	const labels = iconLabels();
	const Tag = `h${ Math.max( 2, Math.min( 6, level || 2 ) ) }`;
	const cols = Math.max( 1, Math.min( 4, columns || 3 ) );
	const blockProps = useBlockProps( {
		className: cls(
			'feature-list',
			`feature-list--cols-${ cols }`,
			`accent--${ accent }`
		),
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Feature list', 'page-builder-sandwich' ) }
				>
					<RangeControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Columns', 'page-builder-sandwich' ) }
						min={ 1 }
						max={ 4 }
						value={ cols }
						onChange={ ( v ) => setAttributes( { columns: v } ) }
						help={ __(
							'On phones the features always stack.',
							'page-builder-sandwich'
						) }
					/>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Icon', 'page-builder-sandwich' ) }
						value={ icon }
						options={ [
							'check',
							'star',
							'info',
							'arrow',
							'dot',
							'circle',
						].map( ( v ) => ( { value: v, label: labels[ v ] } ) ) }
						onChange={ ( v ) => setAttributes( { icon: v } ) }
					/>
					<AccentControl
						value={ accent }
						onChange={ ( v ) => setAttributes( { accent: v } ) }
					/>
					<LevelControl
						value={ level }
						onChange={ ( v ) => setAttributes( { level: v } ) }
					/>
				</PanelBody>
				<PanelBody
					title={ __( 'Features', 'page-builder-sandwich' ) }
					initialOpen={ false }
				>
					{ list.map( ( item, i ) => {
						const name =
							item.title ||
							sprintf(
								/* translators: %d: feature number. */
								__( 'Feature %d', 'page-builder-sandwich' ),
								i + 1
							);
						return (
							<div key={ i } className="pbsw-kit-row">
								<strong>
									{ name.replace( /<[^>]*>/g, '' ) }
								</strong>
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
									name={ name.replace( /<[^>]*>/g, '' ) }
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
					<li key={ i } className={ cls( 'feature-list__item' ) }>
						<span className={ cls( 'feature-list__icon' ) }>
							<Icon svg={ item.icon } builtin={ icon } />
						</span>
						<div className={ cls( 'feature-list__body' ) }>
							<RichText
								tagName={ Tag }
								className={ cls( 'feature-list__title' ) }
								value={ item.title || '' }
								onChange={ ( v ) =>
									set(
										listOps.update( list, i, { title: v } )
									)
								}
								placeholder={ __(
									'Feature…',
									'page-builder-sandwich'
								) }
								allowedFormats={ [ 'core/italic' ] }
							/>
							<RichText
								tagName="p"
								className={ cls( 'feature-list__text' ) }
								value={ item.text || '' }
								onChange={ ( v ) =>
									set(
										listOps.update( list, i, { text: v } )
									)
								}
								placeholder={ __(
									'Describe it…',
									'page-builder-sandwich'
								) }
							/>
						</div>
					</li>
				) ) }
				{ isSelected && (
					<li className="pbsw-kit-add">
						<Button
							variant="secondary"
							size="compact"
							onClick={ () =>
								set(
									listOps.add( list, { title: '', text: '' } )
								)
							}
						>
							{ __( 'Add feature', 'page-builder-sandwich' ) }
						</Button>
					</li>
				) }
			</ul>
		</>
	);
}
