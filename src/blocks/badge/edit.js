/**
 * pbs/badge editor: the front end's own markup, the label edited in place.
 */
import { __ } from '@wordpress/i18n';
import {
	InspectorControls,
	RichText,
	useBlockProps,
} from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';

import { cls } from '../shared/cls';

export const justifyOptions = () => [
	{ value: 'start', label: __( 'Start', 'page-builder-sandwich' ) },
	{ value: 'center', label: __( 'Centre', 'page-builder-sandwich' ) },
	{ value: 'end', label: __( 'End', 'page-builder-sandwich' ) },
];

export default function Edit( { attributes, setAttributes } ) {
	const { content, variant, justify } = attributes;
	const blockProps = useBlockProps( {
		className: cls( 'badge', `badge--${ justify }` ),
	} );
	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Badge', 'page-builder-sandwich' ) }>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Colour', 'page-builder-sandwich' ) }
						value={ variant }
						options={ [
							{
								value: 'accent',
								label: __(
									'Theme accent',
									'page-builder-sandwich'
								),
							},
							{
								value: 'neutral',
								label: __( 'Neutral', 'page-builder-sandwich' ),
							},
							{
								value: 'info',
								label: __(
									'Information',
									'page-builder-sandwich'
								),
							},
							{
								value: 'success',
								label: __( 'Success', 'page-builder-sandwich' ),
							},
							{
								value: 'warning',
								label: __( 'Warning', 'page-builder-sandwich' ),
							},
							{
								value: 'error',
								label: __( 'Error', 'page-builder-sandwich' ),
							},
						] }
						onChange={ ( v ) => setAttributes( { variant: v } ) }
					/>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Position', 'page-builder-sandwich' ) }
						value={ justify }
						options={ justifyOptions() }
						onChange={ ( v ) => setAttributes( { justify: v } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<p { ...blockProps }>
				<RichText
					tagName="span"
					className={ cls(
						'badge__label',
						`badge__label--${ variant }`
					) }
					value={ content }
					onChange={ ( v ) => setAttributes( { content: v } ) }
					placeholder={ __( 'Label', 'page-builder-sandwich' ) }
					allowedFormats={ [] }
					withoutInteractiveFormatting
				/>
			</p>
		</>
	);
}
