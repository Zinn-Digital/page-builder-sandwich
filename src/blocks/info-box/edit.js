/**
 * pbs/info-box editor: the front end's own markup, the heading and text edited in place, the
 * icon, link and layout in the sidebar.
 */
import { __ } from '@wordpress/i18n';
import {
	InspectorControls,
	RichText,
	useBlockProps,
} from '@wordpress/block-editor';
import {
	BaseControl,
	PanelBody,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';

import { cls } from '../shared/cls';
import {
	AccentControl,
	Icon,
	IconButton,
	LevelControl,
	iconLabels,
} from '../shared/kit';

export default function Edit( { attributes, setAttributes } ) {
	const {
		svg,
		iconRef,
		icon,
		showIcon,
		title,
		content,
		linkUrl,
		linkText,
		newTab,
		align,
		level,
		accent,
	} = attributes;
	const labels = iconLabels();
	const Tag = `h${ Math.max( 2, Math.min( 6, level || 2 ) ) }`;
	const blockProps = useBlockProps( {
		className: cls(
			'info-box',
			`info-box--${ align }`,
			`accent--${ accent }`
		),
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Info box', 'page-builder-sandwich' ) }>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Show icon', 'page-builder-sandwich' ) }
						checked={ showIcon }
						onChange={ ( v ) => setAttributes( { showIcon: v } ) }
					/>
					{ showIcon && (
						<>
							<SelectControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								label={ __( 'Icon', 'page-builder-sandwich' ) }
								value={ icon }
								options={ [
									'info',
									'star',
									'check',
									'arrow',
									'dot',
									'circle',
								].map( ( v ) => ( {
									value: v,
									label: labels[ v ],
								} ) ) }
								onChange={ ( v ) =>
									setAttributes( { icon: v } )
								}
								help={ __(
									'Or choose any icon from the library:',
									'page-builder-sandwich'
								) }
							/>
							<BaseControl __nextHasNoMarginBottom>
								<IconButton
									svg={ svg }
									iconRef={ iconRef }
									onChange={ ( patch ) =>
										setAttributes( patch )
									}
								/>
							</BaseControl>
							<AccentControl
								value={ accent }
								onChange={ ( v ) =>
									setAttributes( { accent: v } )
								}
							/>
						</>
					) }
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Alignment', 'page-builder-sandwich' ) }
						value={ align }
						options={ [
							{
								value: 'start',
								label: __( 'Start', 'page-builder-sandwich' ),
							},
							{
								value: 'center',
								label: __( 'Centre', 'page-builder-sandwich' ),
							},
						] }
						onChange={ ( v ) => setAttributes( { align: v } ) }
					/>
					<LevelControl
						value={ level }
						onChange={ ( v ) => setAttributes( { level: v } ) }
					/>
				</PanelBody>
				<PanelBody
					title={ __( 'Link', 'page-builder-sandwich' ) }
					initialOpen={ false }
				>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						type="url"
						label={ __( 'Link address', 'page-builder-sandwich' ) }
						value={ linkUrl }
						onChange={ ( v ) => setAttributes( { linkUrl: v } ) }
					/>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Link text', 'page-builder-sandwich' ) }
						value={ linkText }
						onChange={ ( v ) => setAttributes( { linkText: v } ) }
						help={ __(
							'Say where the link goes ("See our prices"), not "Read more".',
							'page-builder-sandwich'
						) }
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Open in a new tab',
							'page-builder-sandwich'
						) }
						checked={ newTab }
						onChange={ ( v ) => setAttributes( { newTab: v } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				{ showIcon && (
					<span className={ cls( 'info-box__icon' ) }>
						<Icon svg={ svg } builtin={ icon } />
					</span>
				) }
				<RichText
					tagName={ Tag }
					className={ cls( 'info-box__title' ) }
					value={ title }
					onChange={ ( v ) => setAttributes( { title: v } ) }
					placeholder={ __( 'Heading…', 'page-builder-sandwich' ) }
					allowedFormats={ [ 'core/italic' ] }
				/>
				<RichText
					tagName="p"
					className={ cls( 'info-box__text' ) }
					value={ content }
					onChange={ ( v ) => setAttributes( { content: v } ) }
					placeholder={ __(
						'A sentence or two…',
						'page-builder-sandwich'
					) }
				/>
				{ linkUrl && linkText && (
					<p className={ cls( 'info-box__more' ) }>
						<span className={ cls( 'info-box__link' ) }>
							{ linkText }
						</span>
					</p>
				) }
			</div>
		</>
	);
}
