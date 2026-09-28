import { __ } from '@wordpress/i18n';
import { registerBlockType } from '@wordpress/blocks';
import {
	InspectorControls,
	PanelColorSettings,
	useBlockProps,
} from '@wordpress/block-editor';
import {
	PanelBody,
	RangeControl,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

import metadata from '../../blocks/language-switcher/block.json';

/*
 * Page Builder Sandwich's language switcher element (Tranzly tz-l2). The server renders it through
 * Tranzly's `tranzly_language_switcher()` (class-language-switcher.php), so the editor previews
 * exactly what a visitor gets; this file only edits the element's settings.
 */

/**
 * A style variable set or cleared.
 *
 * @param {Object}                  vars  Current variables.
 * @param {string}                  key   Name.
 * @param {string|number|undefined} value New value ('' or undefined clears it).
 * @return {Object} New variables.
 */
export function withVar( vars, key, value ) {
	const next = { ...( vars || {} ) };
	if ( value || 0 === value ) {
		next[ key ] = String( value );
	} else {
		delete next[ key ];
	}
	return next;
}

function Empty() {
	return (
		<p>
			{ __(
				'Install Tranzly and list two or more languages, and the switcher appears here.',
				'page-builder-sandwich'
			) }
		</p>
	);
}

function Edit( { attributes, setAttributes } ) {
	const vars = attributes.vars || {};
	const setVar = ( key, value ) =>
		setAttributes( { vars: withVar( vars, key, value ) } );
	const px = ( key ) =>
		vars[ key ] ? parseInt( vars[ key ], 10 ) || 0 : undefined;
	return (
		<div { ...useBlockProps() }>
			<InspectorControls>
				<PanelBody title={ __( 'Switcher', 'page-builder-sandwich' ) }>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Design', 'page-builder-sandwich' ) }
						value={ attributes.design }
						options={ [
							{
								value: 'list',
								label: __( 'List', 'page-builder-sandwich' ),
							},
							{
								value: 'pills',
								label: __( 'Pills', 'page-builder-sandwich' ),
							},
							{
								value: 'buttons',
								label: __( 'Buttons', 'page-builder-sandwich' ),
							},
							{
								value: 'dropdown',
								label: __(
									'Dropdown',
									'page-builder-sandwich'
								),
							},
							{
								value: 'codes',
								label: __(
									'Language codes',
									'page-builder-sandwich'
								),
							},
						] }
						onChange={ ( design ) => setAttributes( { design } ) }
					/>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __(
							'Show each language as',
							'page-builder-sandwich'
						) }
						value={ attributes.display }
						options={ [
							{
								value: 'name',
								label: __(
									'Language name',
									'page-builder-sandwich'
								),
							},
							{
								value: 'code',
								label: __(
									'Language code',
									'page-builder-sandwich'
								),
							},
							{
								value: 'name_code',
								label: __(
									'Name and code',
									'page-builder-sandwich'
								),
							},
						] }
						onChange={ ( display ) => setAttributes( { display } ) }
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Flags', 'page-builder-sandwich' ) }
						help={ __(
							'Off by default: a flag is a country, not a language.',
							'page-builder-sandwich'
						) }
						checked={ attributes.flags }
						onChange={ ( flags ) => setAttributes( { flags } ) }
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Include the current language',
							'page-builder-sandwich'
						) }
						checked={ attributes.showCurrent }
						onChange={ ( showCurrent ) =>
							setAttributes( { showCurrent } )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Hide languages this page is not translated into',
							'page-builder-sandwich'
						) }
						checked={ attributes.hideMissing }
						onChange={ ( hideMissing ) =>
							setAttributes( { hideMissing } )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Stack vertically',
							'page-builder-sandwich'
						) }
						checked={ attributes.vertical }
						onChange={ ( vertical ) =>
							setAttributes( { vertical } )
						}
					/>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __(
							'Accessible label',
							'page-builder-sandwich'
						) }
						value={ attributes.label }
						onChange={ ( label ) => setAttributes( { label } ) }
					/>
				</PanelBody>
				<PanelColorSettings
					title={ __( 'Colours', 'page-builder-sandwich' ) }
					initialOpen={ false }
					colorSettings={ [
						[ 'color', __( 'Text', 'page-builder-sandwich' ) ],
						[
							'background',
							__( 'Background', 'page-builder-sandwich' ),
						],
						[
							'activeColor',
							__(
								'Current language text',
								'page-builder-sandwich'
							),
						],
						[
							'activeBg',
							__(
								'Current language background',
								'page-builder-sandwich'
							),
						],
						[
							'borderColor',
							__( 'Border', 'page-builder-sandwich' ),
						],
					].map( ( [ key, label ] ) => ( {
						label,
						value: vars[ key ],
						onChange: ( value ) => setVar( key, value ),
					} ) ) }
				/>
				<PanelBody
					title={ __( 'Size and spacing', 'page-builder-sandwich' ) }
					initialOpen={ false }
				>
					{ [
						[
							'radius',
							__( 'Corner radius', 'page-builder-sandwich' ),
							40,
						],
						[
							'gap',
							__(
								'Space between languages',
								'page-builder-sandwich'
							),
							48,
						],
						[
							'fontSize',
							__( 'Text size', 'page-builder-sandwich' ),
							48,
						],
					].map( ( [ key, label, max ] ) => (
						<RangeControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							key={ key }
							label={ label }
							value={ px( key ) }
							min={ 0 }
							max={ max }
							allowReset
							onChange={ ( value ) =>
								setVar(
									key,
									undefined === value ? '' : value + 'px'
								)
							}
						/>
					) ) }
				</PanelBody>
			</InspectorControls>
			<ServerSideRender
				block={ metadata.name }
				attributes={ attributes }
				EmptyResponsePlaceholder={ Empty }
			/>
		</div>
	);
}

registerBlockType( metadata.name, { edit: Edit, save: () => null } );
