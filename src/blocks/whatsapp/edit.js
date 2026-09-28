/**
 * pbs/whatsapp editor: the button shown in place (not floating, so it does not cover the editor),
 * its number, message, text and corner in the sidebar.
 */
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	Notice,
	PanelBody,
	SelectControl,
	TextControl,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';

import { cls } from '../shared/cls';
import { contactHref } from '../contact-buttons/links';

export default function Edit( { attributes, setAttributes } ) {
	const { phone, message, label, showLabel, position } = attributes;
	const ok = !! contactHref( 'whatsapp', phone );
	const text =
		label || __( 'Chat with us on WhatsApp', 'page-builder-sandwich' );
	const blockProps = useBlockProps( { className: 'pbsw-whatsapp-edit' } );
	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ __( 'WhatsApp', 'page-builder-sandwich' ) }>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __(
							'WhatsApp number, with country code',
							'page-builder-sandwich'
						) }
						help={ __(
							'For example +44 7700 900123.',
							'page-builder-sandwich'
						) }
						value={ phone }
						onChange={ ( v ) => setAttributes( { phone: v } ) }
					/>
					<TextareaControl
						__nextHasNoMarginBottom
						label={ __(
							'Message to start with (optional)',
							'page-builder-sandwich'
						) }
						value={ message }
						onChange={ ( v ) => setAttributes( { message: v } ) }
					/>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Button text', 'page-builder-sandwich' ) }
						placeholder={ __(
							'Chat with us on WhatsApp',
							'page-builder-sandwich'
						) }
						value={ label }
						onChange={ ( v ) => setAttributes( { label: v } ) }
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Show the text next to the icon',
							'page-builder-sandwich'
						) }
						checked={ !! showLabel }
						onChange={ ( v ) => setAttributes( { showLabel: v } ) }
					/>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Corner', 'page-builder-sandwich' ) }
						value={ position }
						options={ [
							{
								value: 'end',
								label: __(
									'Bottom end (right in English)',
									'page-builder-sandwich'
								),
							},
							{
								value: 'start',
								label: __(
									'Bottom start (left in English)',
									'page-builder-sandwich'
								),
							},
						] }
						onChange={ ( v ) => setAttributes( { position: v } ) }
					/>
				</PanelBody>
			</InspectorControls>
			{ ! ok && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'Add your WhatsApp number in the sidebar. Until then the button is not shown on the page.',
						'page-builder-sandwich'
					) }
				</Notice>
			) }
			<p className="pbsw-whatsapp-edit__hint">
				{ __(
					'On the page, this button floats in a bottom corner of the screen.',
					'page-builder-sandwich'
				) }
			</p>
			<span
				className={ cls(
					'whatsapp',
					showLabel && 'whatsapp--labelled'
				) }
				style={ { position: 'static' } }
			>
				<svg
					xmlns="http://www.w3.org/2000/svg"
					viewBox="0 0 24 24"
					width="28"
					height="28"
					fill="currentColor"
					aria-hidden="true"
					focusable="false"
				>
					<path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2z" />
				</svg>
				{ showLabel && (
					<span className={ cls( 'whatsapp__label' ) }>{ text }</span>
				) }
			</span>
		</div>
	);
}
