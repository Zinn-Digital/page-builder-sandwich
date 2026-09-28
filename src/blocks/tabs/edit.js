/**
 * pbs/tabs editor: the tab row as visitors see it (titles edited in place, a "+" to add one),
 * and the panel of the tab being edited. Selecting anything inside a tab switches to it.
 */
import { __, sprintf } from '@wordpress/i18n';
import {
	InspectorControls,
	RichText,
	useBlockProps,
	useInnerBlocksProps,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import {
	Button,
	PanelBody,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { createBlock } from '@wordpress/blocks';
import { useDispatch, useSelect } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';
import { plus } from '@wordpress/icons';

import { cls } from '../shared/cls';

const TEMPLATE = [
	[ 'pbs/tab', {}, [ [ 'core/paragraph' ] ] ],
	[ 'pbs/tab', {}, [ [ 'core/paragraph' ] ] ],
];

export default function Edit( { attributes, setAttributes, clientId } ) {
	const { label, vertical, selected } = attributes;
	const { tabs, activeFromSelection } = useSelect(
		( select ) => {
			const store = select( blockEditorStore );
			const children = store.getBlocks( clientId );
			const sel = store.getSelectedBlockClientId();
			const chain = sel ? [ sel, ...store.getBlockParents( sel ) ] : [];
			return {
				tabs: children,
				activeFromSelection: children.findIndex( ( t ) =>
					chain.includes( t.clientId )
				),
			};
		},
		[ clientId ]
	);
	const [ active, setActive ] = useState( selected || 0 );
	useEffect( () => {
		if ( activeFromSelection >= 0 ) {
			setActive( activeFromSelection );
		}
	}, [ activeFromSelection ] );
	const current = Math.min( active, Math.max( 0, tabs.length - 1 ) );

	const { updateBlockAttributes, insertBlock } =
		useDispatch( blockEditorStore );
	const blockProps = useBlockProps( {
		className: cls( 'tabs', vertical && 'tabs--vertical' ),
	} );
	const innerBlocksProps = useInnerBlocksProps(
		{ className: cls( 'tabs__panels' ) },
		{
			allowedBlocks: [ 'pbs/tab' ],
			template: TEMPLATE,
			renderAppender: false,
		}
	);
	const titleOf = ( i ) =>
		/* translators: %d: the number of a tab (1, 2, 3…). */
		sprintf( __( 'Tab %d', 'page-builder-sandwich' ), i + 1 );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Tabs', 'page-builder-sandwich' ) }>
					<TextControl
						__next40pxDefaultSize
						label={ __(
							'Name for screen readers',
							'page-builder-sandwich'
						) }
						help={ __(
							'Optional. Says what the tabs are about, for example "Plans".',
							'page-builder-sandwich'
						) }
						value={ label }
						onChange={ ( value ) =>
							setAttributes( { label: value } )
						}
					/>
					<SelectControl
						__next40pxDefaultSize
						label={ __(
							'Open on page load',
							'page-builder-sandwich'
						) }
						value={ String( selected ) }
						options={ tabs.map( ( t, i ) => ( {
							value: String( i ),
							label:
								( t.attributes.title || '' ).replace(
									/<[^>]+>/g,
									''
								) || titleOf( i ),
						} ) ) }
						onChange={ ( value ) =>
							setAttributes( { selected: Number( value ) } )
						}
					/>
					<ToggleControl
						label={ __(
							'Tabs down the side',
							'page-builder-sandwich'
						) }
						checked={ vertical }
						onChange={ ( on ) => setAttributes( { vertical: on } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<div
					className={ cls( 'tabs__list' ) }
					role="tablist"
					aria-orientation={ vertical ? 'vertical' : undefined }
				>
					{ tabs.map( ( t, i ) => (
						<div
							key={ t.clientId }
							role="tab"
							tabIndex={ -1 }
							aria-selected={ i === current }
							className={ cls( 'tabs__tab' ) }
							onClick={ () => setActive( i ) }
							onKeyDown={ () => {} }
						>
							<RichText
								tagName="span"
								value={ t.attributes.title }
								allowedFormats={ [
									'core/italic',
									'core/bold',
								] }
								placeholder={ titleOf( i ) }
								aria-label={ titleOf( i ) }
								onChange={ ( value ) =>
									updateBlockAttributes( t.clientId, {
										title: value,
									} )
								}
							/>
						</div>
					) ) }
					<Button
						className={ cls( 'tabs__add' ) }
						icon={ plus }
						label={ __( 'Add a tab', 'page-builder-sandwich' ) }
						onClick={ () => {
							insertBlock(
								createBlock( 'pbs/tab', {}, [
									createBlock( 'core/paragraph' ),
								] ),
								tabs.length,
								clientId
							);
							setActive( tabs.length );
						} }
					/>
				</div>
				<div { ...innerBlocksProps } />
				<style>
					{ `#block-${ clientId } > .${ cls(
						'tabs__panels'
					) } > .wp-block:not(:nth-child(${
						current + 1
					})){display:none}` }
				</style>
			</div>
		</>
	);
}
