/**
 * pbs/checklist editor: the front end's own list; each line's text edited in place and its
 * done / to-do state toggled from its own check button (keyboard reachable, labelled).
 */
import { __, sprintf } from '@wordpress/i18n';
import {
	InspectorControls,
	RichText,
	useBlockProps,
} from '@wordpress/block-editor';
import { Button, PanelBody } from '@wordpress/components';

import { cls } from '../shared/cls';
import { AccentControl, Icon, ItemTools, Sr, listOps } from '../shared/kit';

export default function Edit( { attributes, setAttributes, isSelected } ) {
	const { items, accent } = attributes;
	const list = items.length ? items : [ { text: '', done: false } ];
	const set = ( next ) => setAttributes( { items: next } );
	const blockProps = useBlockProps( {
		className: cls( 'checklist', `accent--${ accent }` ),
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Checklist', 'page-builder-sandwich' ) }>
					<AccentControl
						label={ __( 'Tick colour', 'page-builder-sandwich' ) }
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
					<li
						key={ i }
						className={ cls(
							'checklist__item',
							item.done
								? 'checklist__item--done'
								: 'checklist__item--todo'
						) }
					>
						<Button
							className={ cls( 'checklist__mark' ) }
							aria-pressed={ !! item.done }
							label={ __( 'Done', 'page-builder-sandwich' ) }
							onClick={ () =>
								set(
									listOps.update( list, i, {
										done: ! item.done,
									} )
								)
							}
						>
							<Icon builtin={ item.done ? 'check' : 'circle' } />
						</Button>
						<Sr>
							{ item.done
								? __( 'Done:', 'page-builder-sandwich' )
								: __( 'To do:', 'page-builder-sandwich' ) }
						</Sr>{ ' ' }
						<RichText
							tagName="span"
							className={ cls( 'checklist__text' ) }
							value={ item.text || '' }
							onChange={ ( v ) =>
								set( listOps.update( list, i, { text: v } ) )
							}
							placeholder={ __(
								'Task…',
								'page-builder-sandwich'
							) }
							allowedFormats={ [
								'core/bold',
								'core/italic',
								'core/link',
							] }
						/>
					</li>
				) ) }
				{ isSelected && (
					<li className="pbsw-kit-add">
						<Button
							variant="secondary"
							size="compact"
							onClick={ () =>
								set(
									listOps.add( list, {
										text: '',
										done: false,
									} )
								)
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
