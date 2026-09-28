/**
 * pbs/code editor: the code typed straight into the canvas (monospace, tab key inserts a tab), the
 * language, file name and options in the sidebar.
 */
import { __ } from '@wordpress/i18n';
import {
	InspectorControls,
	PlainText,
	useBlockProps,
} from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';

import { cls } from '../shared/cls';
import { LANGUAGES } from './languages';

export default function Edit( { attributes, setAttributes } ) {
	const { code, language, filename, lineNumbers, copy, wrap } = attributes;
	const blockProps = useBlockProps( {
		className: cls( 'code', wrap && 'code--wrap' ),
	} );
	const label = filename || LANGUAGES[ language ] || '';
	const lines = ( code || '' ).split( '\n' ).length;

	return (
		<figure { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ __( 'Code', 'page-builder-sandwich' ) }>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Language', 'page-builder-sandwich' ) }
						value={ language }
						options={ [
							{
								value: 'auto',
								label: __(
									'Detect automatically',
									'page-builder-sandwich'
								),
							},
							{
								value: 'none',
								label: __(
									'No highlighting',
									'page-builder-sandwich'
								),
							},
							...Object.entries( LANGUAGES ).map(
								( [ value, name ] ) => ( {
									value,
									label: name,
								} )
							),
						] }
						onChange={ ( v ) => setAttributes( { language: v } ) }
					/>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'File name', 'page-builder-sandwich' ) }
						help={ __(
							'Shown above the code, for example functions.php.',
							'page-builder-sandwich'
						) }
						value={ filename }
						onChange={ ( v ) => setAttributes( { filename: v } ) }
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Line numbers', 'page-builder-sandwich' ) }
						checked={ lineNumbers }
						onChange={ ( v ) =>
							setAttributes( { lineNumbers: v } )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Copy button', 'page-builder-sandwich' ) }
						checked={ copy }
						onChange={ ( v ) => setAttributes( { copy: v } ) }
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Wrap long lines',
							'page-builder-sandwich'
						) }
						checked={ wrap }
						onChange={ ( v ) => setAttributes( { wrap: v } ) }
					/>
				</PanelBody>
			</InspectorControls>
			{ ( label || copy ) && (
				<figcaption className={ cls( 'code__bar' ) }>
					{ label && (
						<span className={ cls( 'code__file' ) }>{ label }</span>
					) }
					{ copy && (
						<span
							className={ cls( 'code__copy' ) }
							aria-hidden="true"
						>
							{ __( 'Copy code', 'page-builder-sandwich' ) }
						</span>
					) }
				</figcaption>
			) }
			<div className={ cls( 'code__body' ) }>
				{ lineNumbers && (
					<span className={ cls( 'code__lines' ) } aria-hidden="true">
						{ Array.from(
							{ length: lines },
							( _, i ) => i + 1
						).join( '\n' ) }
					</span>
				) }
				<pre className={ cls( 'code__pre' ) }>
					<PlainText
						__experimentalVersion={ 2 }
						tagName="code"
						className={ cls( 'code__code' ) }
						value={ code }
						onChange={ ( v ) => setAttributes( { code: v } ) }
						placeholder={ __(
							'Write or paste code…',
							'page-builder-sandwich'
						) }
						aria-label={ __( 'Code', 'page-builder-sandwich' ) }
					/>
				</pre>
			</div>
		</figure>
	);
}
