/**
 * pbs/dual-heading editor: the front end's own heading, both parts edited in place.
 */
import { __ } from '@wordpress/i18n';
import {
	InspectorControls,
	RichText,
	useBlockProps,
} from '@wordpress/block-editor';
import { PanelBody, SelectControl, ToggleControl } from '@wordpress/components';

import { cls } from '../shared/cls';
import { justifyOptions } from '../badge/edit';

export default function Edit( { attributes, setAttributes } ) {
	const { first, second, level, look, stacked, justify } = attributes;
	const n = Math.max( 1, Math.min( 6, level || 2 ) );
	const Tag = `h${ n }`;
	const blockProps = useBlockProps( {
		className: cls(
			'dual-heading',
			`dual-heading--${ look }`,
			`dual-heading--${ justify }`,
			stacked && 'dual-heading--stacked'
		),
	} );
	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Dual heading', 'page-builder-sandwich' ) }
				>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Heading level', 'page-builder-sandwich' ) }
						value={ String( n ) }
						options={ [ 1, 2, 3, 4, 5, 6 ].map( ( v ) => ( {
							value: String( v ),
							label: `H${ v }`,
						} ) ) }
						onChange={ ( v ) =>
							setAttributes( { level: Number( v ) } )
						}
						help={ __(
							'Use H1 once per page, for its main title.',
							'page-builder-sandwich'
						) }
					/>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __(
							'Second part look',
							'page-builder-sandwich'
						) }
						value={ look }
						options={ [
							{
								value: 'accent',
								label: __(
									'Accent colour',
									'page-builder-sandwich'
								),
							},
							{
								value: 'gradient',
								label: __(
									'Gradient',
									'page-builder-sandwich'
								),
							},
							{
								value: 'outline',
								label: __( 'Outline', 'page-builder-sandwich' ),
							},
							{
								value: 'underline',
								label: __(
									'Underline',
									'page-builder-sandwich'
								),
							},
						] }
						onChange={ ( v ) => setAttributes( { look: v } ) }
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Second part on its own line',
							'page-builder-sandwich'
						) }
						checked={ stacked }
						onChange={ ( v ) => setAttributes( { stacked: v } ) }
					/>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Alignment', 'page-builder-sandwich' ) }
						value={ justify }
						options={ justifyOptions() }
						onChange={ ( v ) => setAttributes( { justify: v } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<Tag { ...blockProps }>
				<RichText
					tagName="span"
					className={ cls( 'dual-heading__first' ) }
					value={ first }
					onChange={ ( v ) => setAttributes( { first: v } ) }
					placeholder={ __( 'First part', 'page-builder-sandwich' ) }
					allowedFormats={ [ 'core/italic' ] }
				/>{ ' ' }
				<RichText
					tagName="span"
					className={ cls( 'dual-heading__second' ) }
					value={ second }
					onChange={ ( v ) => setAttributes( { second: v } ) }
					placeholder={ __( 'second part', 'page-builder-sandwich' ) }
					allowedFormats={ [ 'core/italic' ] }
				/>
			</Tag>
		</>
	);
}
