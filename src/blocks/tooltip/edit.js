/**
 * pbs/tooltip editor: the trigger as visitors see it, with the tip shown under it while the
 * block is selected (on the page it shows on hover and keyboard focus).
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
	TextareaControl,
} from '@wordpress/components';

import { cls } from '../shared/cls';

export default function Edit( { attributes, setAttributes, isSelected } ) {
	const { text, tip, placement } = attributes;
	const blockProps = useBlockProps( { className: cls( 'tooltip' ) } );
	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Tooltip', 'page-builder-sandwich' ) }>
					<TextareaControl
						label={ __( 'Tooltip text', 'page-builder-sandwich' ) }
						help={ __(
							'Keep it short: one or two sentences. Screen readers read it with the word it explains.',
							'page-builder-sandwich'
						) }
						value={ tip }
						onChange={ ( value ) =>
							setAttributes( { tip: value } )
						}
					/>
					<SelectControl
						__next40pxDefaultSize
						label={ __( 'Show it', 'page-builder-sandwich' ) }
						value={ placement }
						options={ [
							{
								value: 'top',
								label: __( 'Above', 'page-builder-sandwich' ),
							},
							{
								value: 'bottom',
								label: __( 'Below', 'page-builder-sandwich' ),
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { placement: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<span
					className={ cls(
						'tooltip__wrap',
						`tooltip__wrap--${ placement }`,
						isSelected && 'is-previewed'
					) }
				>
					<RichText
						tagName="span"
						className={ cls( 'tooltip__trigger' ) }
						value={ text }
						onChange={ ( value ) =>
							setAttributes( { text: value } )
						}
						placeholder={ __(
							'Word or phrase',
							'page-builder-sandwich'
						) }
						allowedFormats={ [ 'core/italic', 'core/bold' ] }
					/>
					{ tip && (
						<span
							className={ cls( 'tooltip__tip' ) }
							role="tooltip"
						>
							{ tip }
						</span>
					) }
				</span>
			</div>
		</>
	);
}
