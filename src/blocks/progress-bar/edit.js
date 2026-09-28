/**
 * pbs/progress-bar editor: the same labelled bar the page shows (Blocks\Data::render_progress_bar()).
 */
import { __, sprintf } from '@wordpress/i18n';
import {
	InspectorControls,
	RichText,
	useBlockProps,
} from '@wordpress/block-editor';
import { PanelBody, RangeControl, ToggleControl } from '@wordpress/components';

import { cls } from '../shared/cls';

export default function Edit( { attributes, setAttributes } ) {
	const max = Math.max( 1, attributes.max || 100 );
	const value = Math.max( 0, Math.min( max, attributes.value || 0 ) );
	const percent = Math.round( ( value / max ) * 100 );
	/* translators: %s: a percentage number, e.g. 70. */
	const text = sprintf( __( '%s%%', 'page-builder-sandwich' ), percent );
	const blockProps = useBlockProps( { className: cls( 'progress-bar' ) } );
	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Progress bar', 'page-builder-sandwich' ) }
				>
					<RangeControl
						__next40pxDefaultSize
						label={ __( 'Value', 'page-builder-sandwich' ) }
						value={ value }
						min={ 0 }
						max={ max }
						onChange={ ( v ) => setAttributes( { value: v } ) }
					/>
					<RangeControl
						__next40pxDefaultSize
						label={ __( 'Maximum', 'page-builder-sandwich' ) }
						help={ __(
							'100 for a percentage; any other number for a count, such as 8 of 10.',
							'page-builder-sandwich'
						) }
						value={ max }
						min={ 1 }
						max={ 1000 }
						onChange={ ( v ) => setAttributes( { max: v } ) }
					/>
					<ToggleControl
						label={ __(
							'Show the percentage',
							'page-builder-sandwich'
						) }
						checked={ attributes.showValue }
						onChange={ ( v ) => setAttributes( { showValue: v } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<div className={ cls( 'progress-bar__head' ) }>
					<RichText
						tagName="span"
						className={ cls( 'progress-bar__label' ) }
						value={ attributes.label }
						onChange={ ( label ) => setAttributes( { label } ) }
						placeholder={ __( 'Label', 'page-builder-sandwich' ) }
						allowedFormats={ [] }
					/>
					{ attributes.showValue && (
						<span className={ cls( 'progress-bar__value' ) }>
							{ text }
						</span>
					) }
				</div>
				<progress
					className={ cls( 'progress-bar__bar' ) }
					max={ max }
					value={ value }
					aria-label={
						attributes.label ||
						__( 'Progress', 'page-builder-sandwich' )
					}
				>
					{ text }
				</progress>
			</div>
		</>
	);
}
