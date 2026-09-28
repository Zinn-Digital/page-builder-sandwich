/**
 * Editor UIs for the core blocks. wp-admin only, so strings may name the plugin; every string is
 * translatable (text domain page-builder-sandwich).
 */
import { __ } from '@wordpress/i18n';
import {
	InspectorControls,
	RichText,
	useBlockProps,
	useInnerBlocksProps,
} from '@wordpress/block-editor';
import {
	Button,
	PanelBody,
	SelectControl,
	TextControl,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import ServerSideRender from '@wordpress/server-side-render';

import IconField, { IconPreview } from '../design/icons/IconField';
import '../design/icons/picker.scss';
import { styleObject } from './style';
import { COLUMN_STYLE, ROW_STYLE } from './save';

/** Data the server adds for the editor (registered widgets and widget areas). */
const editorData = () => window.pbswCore || { widgets: [], sidebars: [] };

function StylePanel( { attributes, setAttributes, legacy = true } ) {
	return (
		<PanelBody
			title={ __( 'Advanced styling', 'page-builder-sandwich' ) }
			initialOpen={ false }
		>
			<TextareaControl
				label={ __( 'Inline CSS', 'page-builder-sandwich' ) }
				help={ __(
					'CSS declarations, for example: min-height: 380px; background-color: #333',
					'page-builder-sandwich'
				) }
				value={ attributes.style }
				onChange={ ( style ) => setAttributes( { style } ) }
			/>
			{ legacy && (
				<TextControl
					__next40pxDefaultSize
					label={ __(
						'Effects (legacy classes)',
						'page-builder-sandwich'
					) }
					help={ __(
						'Space-separated effect names carried over from older versions, for example: hover-shadow',
						'page-builder-sandwich'
					) }
					value={ attributes.legacyClass }
					onChange={ ( legacyClass ) =>
						setAttributes( { legacyClass } )
					}
				/>
			) }
		</PanelBody>
	);
}

export function RowEdit( props ) {
	const { attributes, setAttributes } = props;
	const blockProps = useBlockProps( {
		style: {
			...styleObject( ROW_STYLE ),
			...styleObject( attributes.style ),
		},
	} );
	const innerBlocksProps = useInnerBlocksProps( blockProps, {
		allowedBlocks: [ 'pbs/column' ],
		template: [ [ 'pbs/column' ], [ 'pbs/column' ] ],
		orientation: 'horizontal',
	} );
	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Row', 'page-builder-sandwich' ) }>
					<SelectControl
						__next40pxDefaultSize
						label={ __( 'Width', 'page-builder-sandwich' ) }
						value={ attributes.width }
						options={ [
							{
								label: __(
									'Content width',
									'page-builder-sandwich'
								),
								value: '',
							},
							{
								label: __(
									'Full width',
									'page-builder-sandwich'
								),
								value: 'full-width',
							},
							{
								label: __(
									'Full width, content kept in the middle',
									'page-builder-sandwich'
								),
								value: 'full-width-retain-content',
							},
						] }
						onChange={ ( width ) => setAttributes( { width } ) }
					/>
				</PanelBody>
				<StylePanel { ...props } />
			</InspectorControls>
			<div { ...innerBlocksProps } />
		</>
	);
}

export function ColumnEdit( props ) {
	const { attributes } = props;
	const blockProps = useBlockProps( {
		style: {
			...styleObject( COLUMN_STYLE ),
			...styleObject( attributes.style ),
		},
	} );
	const innerBlocksProps = useInnerBlocksProps( blockProps, {
		templateLock: false,
	} );
	return (
		<>
			<InspectorControls>
				<StylePanel { ...props } />
			</InspectorControls>
			<div { ...innerBlocksProps } />
		</>
	);
}

export function ButtonEdit( props ) {
	const { attributes, setAttributes } = props;
	const blockProps = useBlockProps( {
		style: attributes.align ? { textAlign: attributes.align } : undefined,
	} );
	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Link', 'page-builder-sandwich' ) }>
					<TextControl
						__next40pxDefaultSize
						type="url"
						label={ __( 'Link address', 'page-builder-sandwich' ) }
						value={ attributes.url }
						onChange={ ( url ) => setAttributes( { url } ) }
					/>
					<ToggleControl
						label={ __(
							'Open in a new tab',
							'page-builder-sandwich'
						) }
						checked={ '_blank' === attributes.target }
						onChange={ ( on ) =>
							setAttributes( { target: on ? '_blank' : '' } )
						}
					/>
					<TextControl
						__next40pxDefaultSize
						label={ __(
							'Link relationship',
							'page-builder-sandwich'
						) }
						value={ attributes.rel }
						onChange={ ( rel ) => setAttributes( { rel } ) }
					/>
					<SelectControl
						__next40pxDefaultSize
						label={ __( 'Alignment', 'page-builder-sandwich' ) }
						value={ attributes.align }
						options={ [
							{
								label: __( 'None', 'page-builder-sandwich' ),
								value: '',
							},
							{
								label: __( 'Left', 'page-builder-sandwich' ),
								value: 'left',
							},
							{
								label: __( 'Centre', 'page-builder-sandwich' ),
								value: 'center',
							},
							{
								label: __( 'Right', 'page-builder-sandwich' ),
								value: 'right',
							},
						] }
						onChange={ ( align ) => setAttributes( { align } ) }
					/>
				</PanelBody>
				<StylePanel { ...props } />
			</InspectorControls>
			<div { ...blockProps }>
				<RichText
					tagName="a"
					style={ styleObject( attributes.style ) }
					aria-label={ __( 'Button text', 'page-builder-sandwich' ) }
					placeholder={ __( 'Add text…', 'page-builder-sandwich' ) }
					allowedFormats={ [ 'core/bold', 'core/italic' ] }
					value={ attributes.text }
					onChange={ ( text ) => setAttributes( { text } ) }
					withoutInteractiveFormatting
				/>
			</div>
		</>
	);
}

export function IconEdit( props ) {
	const { attributes, setAttributes } = props;
	const blockProps = useBlockProps( {
		style: styleObject( attributes.style ),
	} );
	// Previewed inline through sanitizeSvg() (the twin of the server's Svg::sanitize), so the icon
	// takes the text colour; the server sanitises again before any visitor sees it.
	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Icon', 'page-builder-sandwich' ) }>
					<IconField
						svg={ attributes.svg }
						iconRef={ attributes.iconRef }
						onChange={ setAttributes }
					/>
					<TextControl
						__next40pxDefaultSize
						label={ __(
							'Accessible label',
							'page-builder-sandwich'
						) }
						help={ __(
							'Leave empty for a decorative icon.',
							'page-builder-sandwich'
						) }
						value={ attributes.label }
						onChange={ ( label ) => setAttributes( { label } ) }
					/>
				</PanelBody>
				<StylePanel { ...props } legacy={ false } />
			</InspectorControls>
			<span { ...blockProps }>
				{ attributes.svg ? (
					<IconPreview svg={ attributes.svg } />
				) : (
					__(
						'Choose an icon in the block settings.',
						'page-builder-sandwich'
					)
				) }
			</span>
		</>
	);
}

function InstanceFields( { instance, onChange } ) {
	const [ name, setName ] = useState( '' );
	const keys = Object.keys( instance || {} );
	return (
		<>
			{ keys.map( ( key ) => (
				<TextareaControl
					key={ key }
					label={ key }
					value={ String( instance[ key ] ?? '' ) }
					onChange={ ( value ) =>
						onChange( { ...instance, [ key ]: value } )
					}
				/>
			) ) }
			<TextControl
				__next40pxDefaultSize
				label={ __( 'New setting name', 'page-builder-sandwich' ) }
				help={ __(
					'For example: title or text',
					'page-builder-sandwich'
				) }
				value={ name }
				onChange={ setName }
			/>
			<Button
				variant="secondary"
				disabled={ ! /^[a-z0-9_]+$/.test( name ) }
				onClick={ () => {
					onChange( { ...instance, [ name ]: '' } );
					setName( '' );
				} }
			>
				{ __( 'Add setting', 'page-builder-sandwich' ) }
			</Button>
		</>
	);
}

export function WidgetEdit( { attributes, setAttributes, name } ) {
	const { widgets } = editorData();
	return (
		<div { ...useBlockProps() }>
			<InspectorControls>
				<PanelBody title={ __( 'Widget', 'page-builder-sandwich' ) }>
					<SelectControl
						__next40pxDefaultSize
						label={ __( 'Widget', 'page-builder-sandwich' ) }
						value={ attributes.widget }
						options={ [
							{
								label: __(
									'Choose a widget',
									'page-builder-sandwich'
								),
								value: '',
							},
							...widgets.map( ( w ) => ( {
								label: w.name,
								value: w.class,
							} ) ),
						] }
						onChange={ ( widget ) => setAttributes( { widget } ) }
					/>
					<InstanceFields
						instance={ attributes.instance }
						onChange={ ( instance ) =>
							setAttributes( { instance } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<ServerSideRender block={ name } attributes={ attributes } />
		</div>
	);
}

export function SidebarEdit( { attributes, setAttributes, name } ) {
	const { sidebars } = editorData();
	return (
		<div { ...useBlockProps() }>
			<InspectorControls>
				<PanelBody
					title={ __( 'Widget area', 'page-builder-sandwich' ) }
				>
					<SelectControl
						__next40pxDefaultSize
						label={ __( 'Widget area', 'page-builder-sandwich' ) }
						value={ attributes.sidebar }
						options={ [
							{
								label: __(
									'Choose a widget area',
									'page-builder-sandwich'
								),
								value: '',
							},
							...sidebars.map( ( s ) => ( {
								label: s.name,
								value: s.id,
							} ) ),
						] }
						onChange={ ( sidebar ) => setAttributes( { sidebar } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<ServerSideRender block={ name } attributes={ attributes } />
		</div>
	);
}
