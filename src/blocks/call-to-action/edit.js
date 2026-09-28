/**
 * pbs/call-to-action editor: the front end's own markup; heading, text and button labels edited
 * in place, the addresses in the sidebar.
 */
import { __ } from '@wordpress/i18n';
import {
	InspectorControls,
	RichText,
	useBlockProps,
} from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';

import { cls } from '../shared/cls';
import { LevelControl } from '../shared/kit';

function ButtonFields( { label, prefix, attributes, setAttributes } ) {
	return (
		<PanelBody title={ label } initialOpen={ prefix === 'primary' }>
			<TextControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				type="url"
				label={ __( 'Link address', 'page-builder-sandwich' ) }
				value={ attributes[ `${ prefix }Url` ] }
				onChange={ ( v ) =>
					setAttributes( { [ `${ prefix }Url` ]: v } )
				}
				help={ __(
					'The button shows when it has both a label and an address.',
					'page-builder-sandwich'
				) }
			/>
			<ToggleControl
				__nextHasNoMarginBottom
				label={ __( 'Open in a new tab', 'page-builder-sandwich' ) }
				checked={ attributes[ `${ prefix }NewTab` ] }
				onChange={ ( v ) =>
					setAttributes( { [ `${ prefix }NewTab` ]: v } )
				}
			/>
		</PanelBody>
	);
}

export default function Edit( { attributes, setAttributes } ) {
	const { title, content, level, primaryText, secondaryText, layout, align } =
		attributes;
	const Tag = `h${ Math.max( 2, Math.min( 6, level || 2 ) ) }`;
	const blockProps = useBlockProps( {
		className: cls(
			'call-to-action',
			`call-to-action--${ layout }`,
			`call-to-action--${ align }`
		),
	} );
	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Call to action', 'page-builder-sandwich' ) }
				>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Layout', 'page-builder-sandwich' ) }
						value={ layout }
						options={ [
							{
								value: 'stacked',
								label: __(
									'Buttons below',
									'page-builder-sandwich'
								),
							},
							{
								value: 'split',
								label: __(
									'Buttons beside the text',
									'page-builder-sandwich'
								),
							},
						] }
						onChange={ ( v ) => setAttributes( { layout: v } ) }
					/>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Alignment', 'page-builder-sandwich' ) }
						value={ align }
						options={ [
							{
								value: 'center',
								label: __( 'Centre', 'page-builder-sandwich' ),
							},
							{
								value: 'start',
								label: __( 'Start', 'page-builder-sandwich' ),
							},
						] }
						onChange={ ( v ) => setAttributes( { align: v } ) }
					/>
					<LevelControl
						value={ level }
						onChange={ ( v ) => setAttributes( { level: v } ) }
					/>
				</PanelBody>
				<ButtonFields
					label={ __( 'Main button', 'page-builder-sandwich' ) }
					prefix="primary"
					attributes={ attributes }
					setAttributes={ setAttributes }
				/>
				<ButtonFields
					label={ __( 'Second button', 'page-builder-sandwich' ) }
					prefix="secondary"
					attributes={ attributes }
					setAttributes={ setAttributes }
				/>
			</InspectorControls>
			<div { ...blockProps }>
				<div className={ cls( 'call-to-action__body' ) }>
					<RichText
						tagName={ Tag }
						className={ cls( 'call-to-action__title' ) }
						value={ title }
						onChange={ ( v ) => setAttributes( { title: v } ) }
						placeholder={ __(
							'Heading…',
							'page-builder-sandwich'
						) }
						allowedFormats={ [ 'core/italic' ] }
					/>
					<RichText
						tagName="p"
						className={ cls( 'call-to-action__text' ) }
						value={ content }
						onChange={ ( v ) => setAttributes( { content: v } ) }
						placeholder={ __(
							'One sentence on why…',
							'page-builder-sandwich'
						) }
					/>
				</div>
				<div className={ cls( 'call-to-action__buttons' ) }>
					<RichText
						tagName="span"
						className={ cls(
							'call-to-action__button',
							'call-to-action__button--primary'
						) }
						value={ primaryText }
						onChange={ ( v ) =>
							setAttributes( { primaryText: v } )
						}
						placeholder={ __(
							'Main button',
							'page-builder-sandwich'
						) }
						allowedFormats={ [] }
						withoutInteractiveFormatting
					/>
					<RichText
						tagName="span"
						className={ cls(
							'call-to-action__button',
							'call-to-action__button--secondary'
						) }
						value={ secondaryText }
						onChange={ ( v ) =>
							setAttributes( { secondaryText: v } )
						}
						placeholder={ __(
							'Second button (optional)',
							'page-builder-sandwich'
						) }
						allowedFormats={ [] }
						withoutInteractiveFormatting
					/>
				</div>
			</div>
		</>
	);
}
