/**
 * pbs/steps editor: the front end's own numbered list, each step's heading and text edited in
 * place; layout and colour in the sidebar.
 */
import { __, sprintf } from '@wordpress/i18n';
import {
	InspectorControls,
	RichText,
	useBlockProps,
} from '@wordpress/block-editor';
import { Button, PanelBody, SelectControl } from '@wordpress/components';

import { cls } from '../shared/cls';
import { AccentControl, ItemTools, LevelControl, listOps } from '../shared/kit';

export default function Edit( { attributes, setAttributes, isSelected } ) {
	const { items, layout, level, accent } = attributes;
	const list = items.length ? items : [ { title: '', text: '' } ];
	const set = ( next ) => setAttributes( { items: next } );
	const Tag = `h${ Math.max( 2, Math.min( 6, level || 2 ) ) }`;
	const blockProps = useBlockProps( {
		className: cls( 'steps', `steps--${ layout }`, `accent--${ accent }` ),
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Steps', 'page-builder-sandwich' ) }>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Layout', 'page-builder-sandwich' ) }
						value={ layout }
						options={ [
							{
								value: 'vertical',
								label: __(
									'Down the page',
									'page-builder-sandwich'
								),
							},
							{
								value: 'horizontal',
								label: __(
									'Side by side (stacks on phones)',
									'page-builder-sandwich'
								),
							},
						] }
						onChange={ ( v ) => setAttributes( { layout: v } ) }
					/>
					<AccentControl
						label={ __( 'Number colour', 'page-builder-sandwich' ) }
						value={ accent }
						onChange={ ( v ) => setAttributes( { accent: v } ) }
					/>
					<LevelControl
						value={ level }
						onChange={ ( v ) => setAttributes( { level: v } ) }
					/>
				</PanelBody>
				<PanelBody
					title={ __(
						'Order of the steps',
						'page-builder-sandwich'
					) }
					initialOpen={ false }
				>
					{ list.map( ( item, i ) => {
						const name = sprintf(
							/* translators: %d: step number. */
							__( 'Step %d', 'page-builder-sandwich' ),
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
			<ol { ...blockProps }>
				{ list.map( ( item, i ) => (
					<li key={ i } className={ cls( 'steps__item' ) }>
						<span
							className={ cls( 'steps__number' ) }
							aria-hidden="true"
						>
							{ i + 1 }
						</span>
						<div className={ cls( 'steps__body' ) }>
							<RichText
								tagName={ Tag }
								className={ cls( 'steps__title' ) }
								value={ item.title || '' }
								onChange={ ( v ) =>
									set(
										listOps.update( list, i, { title: v } )
									)
								}
								placeholder={ __(
									'Step…',
									'page-builder-sandwich'
								) }
								allowedFormats={ [ 'core/italic' ] }
							/>
							<RichText
								tagName="p"
								className={ cls( 'steps__text' ) }
								value={ item.text || '' }
								onChange={ ( v ) =>
									set(
										listOps.update( list, i, { text: v } )
									)
								}
								placeholder={ __(
									'What to do…',
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
							{ __( 'Add step', 'page-builder-sandwich' ) }
						</Button>
					</li>
				) }
			</ol>
		</>
	);
}
