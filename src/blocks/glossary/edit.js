/**
 * pbs/glossary editor: terms and definitions edited in place, in the order entered (the page
 * sorts them A–Z when "Sort A to Z" is on, as the sidebar says).
 */
import { __, sprintf } from '@wordpress/i18n';
import {
	InspectorControls,
	RichText,
	useBlockProps,
} from '@wordpress/block-editor';
import { Button, PanelBody, ToggleControl } from '@wordpress/components';

import { cls } from '../shared/cls';
import { ItemTools, listOps } from '../shared/kit';

export default function Edit( { attributes, setAttributes, isSelected } ) {
	const { items, sort, showIndex, autoLink } = attributes;
	const list = items.length ? items : [ { term: '', definition: '' } ];
	const set = ( next ) => setAttributes( { items: next } );
	const blockProps = useBlockProps( { className: cls( 'glossary' ) } );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Glossary', 'page-builder-sandwich' ) }>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Sort A to Z', 'page-builder-sandwich' ) }
						checked={ sort }
						onChange={ ( v ) => setAttributes( { sort: v } ) }
						help={ __(
							'The page shows the terms in alphabetical order; here they stay in the order you wrote them.',
							'page-builder-sandwich'
						) }
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Show an A–Z index',
							'page-builder-sandwich'
						) }
						checked={ showIndex }
						onChange={ ( v ) => setAttributes( { showIndex: v } ) }
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Link terms used elsewhere on this page',
							'page-builder-sandwich'
						) }
						checked={ autoLink }
						onChange={ ( v ) => setAttributes( { autoLink: v } ) }
						help={ __(
							'The first time each term appears in the page text it links to its definition. Headings, buttons, code and existing links are left alone.',
							'page-builder-sandwich'
						) }
					/>
				</PanelBody>
				<PanelBody
					title={ __( 'Terms', 'page-builder-sandwich' ) }
					initialOpen={ false }
				>
					{ list.map( ( item, i ) => {
						const name =
							( item.term || '' ).replace( /<[^>]*>/g, '' ) ||
							sprintf(
								/* translators: %d: term number. */
								__( 'Term %d', 'page-builder-sandwich' ),
								i + 1
							);
						return (
							<div key={ i } className="pbsw-kit-row">
								<strong>{ name }</strong>
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
			<div { ...blockProps }>
				<dl className={ cls( 'glossary__list' ) }>
					{ list.map( ( item, i ) => (
						<div key={ i } className={ cls( 'glossary__entry' ) }>
							<RichText
								tagName="dt"
								className={ cls( 'glossary__term' ) }
								value={ item.term || '' }
								onChange={ ( v ) =>
									set(
										listOps.update( list, i, { term: v } )
									)
								}
								placeholder={ __(
									'Term',
									'page-builder-sandwich'
								) }
								allowedFormats={ [ 'core/italic' ] }
							/>
							<RichText
								tagName="dd"
								className={ cls( 'glossary__definition' ) }
								value={ item.definition || '' }
								onChange={ ( v ) =>
									set(
										listOps.update( list, i, {
											definition: v,
										} )
									)
								}
								placeholder={ __(
									'What it means…',
									'page-builder-sandwich'
								) }
							/>
						</div>
					) ) }
				</dl>
				{ isSelected && (
					<Button
						className="pbsw-kit-add"
						variant="secondary"
						size="compact"
						onClick={ () =>
							set(
								listOps.add( list, {
									term: '',
									definition: '',
								} )
							)
						}
					>
						{ __( 'Add term', 'page-builder-sandwich' ) }
					</Button>
				) }
			</div>
		</>
	);
}
