/**
 * pbs/qr-code editor: type what the code should hold; the QR code is drawn here, in the browser,
 * and saved with the block (no QR service is contacted).
 */
import { __ } from '@wordpress/i18n';
import { RawHTML } from '@wordpress/element';
import {
	InspectorControls,
	RichText,
	useBlockProps,
} from '@wordpress/block-editor';
import {
	Notice,
	PanelBody,
	Placeholder,
	TextControl,
} from '@wordpress/components';

import { cls } from '../shared/cls';
import { qrSvg } from './qr';

export default function Edit( { attributes, setAttributes } ) {
	const { text, svg, alt, caption } = attributes;
	const blockProps = useBlockProps( { className: cls( 'qr-code' ) } );
	const setText = ( value ) =>
		setAttributes( { text: value, svg: qrSvg( value.trim() ) } );
	const tooLong = text && ! svg;

	const field = (
		<TextControl
			__next40pxDefaultSize
			__nextHasNoMarginBottom
			label={ __( 'Web address or text', 'page-builder-sandwich' ) }
			value={ text }
			onChange={ setText }
		/>
	);

	return (
		<figure { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ __( 'QR code', 'page-builder-sandwich' ) }>
					{ field }
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __(
							'Description for screen readers',
							'page-builder-sandwich'
						) }
						help={ __(
							'Leave empty to use "QR code for" and the text.',
							'page-builder-sandwich'
						) }
						value={ alt }
						onChange={ ( v ) => setAttributes( { alt: v } ) }
					/>
				</PanelBody>
			</InspectorControls>
			{ svg ? (
				<span className={ cls( 'qr-code__image' ) }>
					<RawHTML>{ svg }</RawHTML>
				</span>
			) : (
				<Placeholder
					icon="screenoptions"
					label={ __( 'QR code', 'page-builder-sandwich' ) }
					instructions={ __(
						'Type a web address, a phone link (tel:) or any text.',
						'page-builder-sandwich'
					) }
				>
					{ field }
					{ tooLong && (
						<Notice status="warning" isDismissible={ false }>
							{ __(
								'This is too long for a QR code. Shorten it.',
								'page-builder-sandwich'
							) }
						</Notice>
					) }
				</Placeholder>
			) }
			{ svg && (
				<RichText
					tagName="figcaption"
					className={ cls( 'qr-code__caption' ) }
					value={ caption }
					onChange={ ( v ) => setAttributes( { caption: v } ) }
					placeholder={ __(
						'Add a caption',
						'page-builder-sandwich'
					) }
				/>
			) }
		</figure>
	);
}
